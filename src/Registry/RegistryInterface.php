<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use PRSW\SwarmIngress\Ingress\Service;

interface RegistryInterface
{
    public function addService(Service $service): void;

    public function removeService(Service $service): void;

    public function refresh(Service $service): void;
}
