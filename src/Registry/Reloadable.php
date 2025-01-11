<?php

declare(strict_types=1);

namespace PRSW\Ingress\Registry;

interface Reloadable
{
    public function reload(): void;
}
