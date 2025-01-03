<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Acme;

interface AcmeInterface
{
    public function createNewCertificate(string $domain): void;

    public function renew(string $domain): void;
}
