<?php

declare(strict_types=1);

namespace PRSW\Ingress\Registry;

interface CanToManageUpstream
{
    public function addUpstream(Service $service): void;

    public function removeUpstream(Service $service): void;
}
