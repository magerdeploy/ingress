<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

interface AcmeHttpChallenge
{
    public function serveHttpChallenge(string $domain, string $token, string $payload): void;

    public function cleanup(string $domain): void;
}
