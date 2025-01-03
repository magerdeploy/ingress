<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

interface Initializer
{
    public function init(): void;
}
