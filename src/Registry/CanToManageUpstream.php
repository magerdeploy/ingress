<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use PRSW\SwarmIngress\Ingress\Service;

interface CanToManageUpstream
{
    public function addUpstream(Service $service): void;

    public function removeUpstream(Service $service): void;
}
