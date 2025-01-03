<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

interface Reloadable
{
    public function reload(): void;
}
