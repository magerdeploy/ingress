<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

interface RegistryInterface
{
    public function addVirtualHost(string $domain, string $path, string $upstream): void;

    public function removeVirtualHost(string $domain, string $path, string $upstream): void;

    public function refreshSSLCertificate(string $domain): void;
}
