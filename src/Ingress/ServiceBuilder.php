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
            return match(true) {
                'remove' === $event->getAction() => Service::fromServiceId($event->getActor()->getID()),
                default => Service::fromDockerService($this->docker->serviceInspect($event->getActor()->getID()))
            };
        }

        throw new \InvalidArgumentException('unknown event type');
    }
}
