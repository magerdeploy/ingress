<?php

declare(strict_types=1);

namespace PRSW\Ingress;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

final class ConsoleApplication extends Application
{
    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        parent::__construct($name, $version);
    }

    public function configureVerbosityLevel(): void
    {
        $this->configureIO(
            new ArgvInput(),
            new ConsoleOutput(),
        );
    }
}
