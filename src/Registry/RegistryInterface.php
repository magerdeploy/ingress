<?php

declare(strict_types=1);

namespace PRSW\Ingress\Registry;

interface RegistryInterface
{
    public function addService(Service $service): void;

    public function removeService(Service $service): void;

    public function refresh(Service $service): void;
}
