<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

interface CanToManageUpstream
{
    public function addUpstream(string $domain, string $path, string $upstream): void;

    public function removeUpstream(string $domain, string $path, string $upstream): void;
}
