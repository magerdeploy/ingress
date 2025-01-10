<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Command;

use PRSW\Docker\Client;
use PRSW\Docker\Generated\Model\EventMessage;
use PRSW\Docker\Model\Stream;
use PRSW\SwarmIngress\Cache\SslCertificateTable;
use PRSW\SwarmIngress\Ingress\ServiceBuilder;
use PRSW\SwarmIngress\Registry\RegistryManagerInterface;
use PRSW\SwarmIngress\SslCertificate\CertificateManager;
use Psl\Async\Scheduler;
use Psl\DateTime\Duration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Amp\async;

final class Watch extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Client $docker,
        private readonly RegistryManagerInterface $registryManager,
        private readonly ServiceBuilder $serviceBuilder,
        private readonly SslCertificateTable $sslCertificateTable,
        private readonly CertificateManager $certificateManager,
    ) {
        parent::__construct('watch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filters = json_encode([
            'type' => [
                'container', 'service',
            ],
            'event' => [
                'start', 'kill',
                'create', 'remove',
            ],
        ]);

        $eventStream = $this->docker->systemEvents(['filters' => $filters], Client::FETCH_STREAM);

        $this->registryManager->init();

        Scheduler::repeat(Duration::days(1), function () {
            $this->logger->info('ssl certificate renewal started');
            foreach ($this->sslCertificateTable->listDomains() as $domain => [$auto, $type]) {
                if (!$auto) {
                    continue;
                }

                $this->certificateManager->renew($type, $domain);
            }
            $this->logger->info('ssl certificate renewal finished');
        });

        $this->logger->info('docker watcher started');

        if ($eventStream instanceof Stream) {
            /** @var EventMessage $event */
            foreach ($eventStream->stream() as $event) {
                try {
                    $service = async(fn () => $this->serviceBuilder->build($event))->await();

                    $eventName = sprintf(
                        'on%s%s',
                        ucfirst($event->getType()),
                        ucfirst($event->getAction())
                    );

                    $this->logger->info('event handled', ['event' => $eventName, 'service' => $service]);

                    if (!method_exists($this->registryManager, $eventName)) {
                        throw new \InvalidArgumentException('invalid docker event');
                    }

                    async(fn () => $this->registryManager->{$eventName}($service));
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }
        }

        return Command::SUCCESS;
    }
}
