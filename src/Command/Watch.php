<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Command;

use PRSW\Docker\Client;
use PRSW\Docker\Generated\Model\EventMessage;
use PRSW\Docker\Model\Stream;
use PRSW\SwarmIngress\Ingress\ServiceBuilder;
use PRSW\SwarmIngress\Registry\RegistryManagerInterface;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Watch extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Client $docker,
        private readonly RegistryManagerInterface $registryManager,
        private readonly ServiceBuilder $serviceBuilder,
    ) {
        parent::__construct('watch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filters = json_encode([
            'type' => [
                'container', 'service',
            ],
            'event' => [
                'start', 'kill',
                'create', 'remove',
            ],
        ]);

        $this->logger->info('Docker Watcher Started');

        $eventStream = $this->docker->systemEvents(['filters' => $filters], Client::FETCH_STREAM);

        $this->registryManager->init();

        EventLoop::repeat(5, function() use ($io) {
            $io->writeln((string) memory_get_usage(true));
        });

        if ($eventStream instanceof Stream) {
            /** @var EventMessage $event */
            foreach ($eventStream->stream() as $event) {
                try {
                    $service = $this->serviceBuilder->build($event);

                    $eventName = sprintf(
                        'on%s%s',
                        ucfirst($event->getType()),
                        ucfirst($event->getAction())
                    );

                    if (!method_exists($this->registryManager, $eventName)) {
                        throw new \InvalidArgumentException('invalid docker event');
                    }

                    $this->registryManager->{$eventName}($service);
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }
        }

        return Command::SUCCESS;
    }
}
