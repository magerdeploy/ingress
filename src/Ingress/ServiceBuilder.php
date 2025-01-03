<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Ingress;

use PRSW\Docker\Client;
use PRSW\Docker\Generated\Model\EventMessage;

final readonly class ServiceBuilder
{
    public function __construct(private Client $docker) {}

    public function build(EventMessage $event): Service
    {
        if ('container' === $event->getType()) {
            $container = $this->docker->containerInspect($event->getActor()->getID());

            return Service::fromDockerContainer($container);
        }

        if ('service' === $event->getType()) {
            $service = $this->docker->serviceInspect($event->getActor()->getID());

            return Service::fromDockerService($service);
        }

        throw new \InvalidArgumentException('Unknown event type');
    }
}
