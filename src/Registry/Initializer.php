<?php

declare(strict_types=1);

namespace PRSW\Ingress\Registry;

interface Initializer
{
    public function init(): void;
}
