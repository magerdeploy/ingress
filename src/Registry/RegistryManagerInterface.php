<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use PRSW\SwarmIngress\Ingress\Service;

interface RegistryManagerInterface
{
    public function init(): void;

    public function onContainerStart(Service $service): void;

    public function onContainerKill(Service $service): void;

    public function onServiceCreate(Service $service): void;

    public function onServiceRemove(Service $service): void;
}
