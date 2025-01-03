<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Command;

use PRSW\Docker\Client;
use PRSW\Docker\Generated\Model\EventMessage;
use PRSW\Docker\Model\Stream;
use PRSW\SwarmIngress\Ingress\Service;
use PRSW\SwarmIngress\Registry\Initializer;
use PRSW\SwarmIngress\Registry\RegistryManager;
use PRSW\SwarmIngress\TableCache\UpstreamTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Watch extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Client $docker,
        private readonly UpstreamTable $upstreamTable,
        private readonly RegistryManager $registryManager
    ) {
        parent::__construct('watch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filters = json_encode([
            'type' => [
                'container',
            ],
            'event' => [
                'start', 'kill',
            ],
        ]);

        $this->logger->info('Docker Watcher Started');

        $eventStream = $this->docker->systemEvents(['filters' => $filters], Client::FETCH_STREAM);

        if ($this->registryManager->registry instanceof Initializer) {
            $this->registryManager->registry->init();
        }

        if ($eventStream instanceof Stream) {
            /** @var EventMessage $event */
            foreach ($eventStream->stream() as $event) {
                try {
                    $container = $this->docker->containerInspect($event->getActor()->getID());
                    $service = Service::fromDockerContainer($container);

                    $func = match (true) {
                        'start' === $event->getAction() => function () use ($service) {
                            $this->registryManager->onContainerStart($service);
                            $this->upstreamTable->addUpstream($service->getTableKey(), $service->upstream);
                            $this->logger->debug('Service Registered {service}', [
                                'service' => (string) $service,
                            ]);
                        },
                        'kill' === $event->getAction() => function () use ($service) {
                            $this->registryManager->onContainerKill($service);
                            $this->upstreamTable->removeUpstream($service->getTableKey(), $service->upstream);
                            $this->logger->debug('Service Deregistered {service}', [
                                'service' => (string) $service,
                            ]);
                        },
                        default => throw new \InvalidArgumentException('Invalid docker event')
                    };

                    $func();
                } catch (\Exception $e) {
                    $io->warning($e->getMessage());

                    continue;
                }
            }
        }

        return Command::SUCCESS;
    }
}
