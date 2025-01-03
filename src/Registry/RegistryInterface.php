<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

interface RegistryInterface
{
    public function addService(string $domain, string $path, string $upstream): void;

    public function removeService(string $domain, string $path, string $upstream): void;

    public function refresh(string $domain, string $path): void;
}
