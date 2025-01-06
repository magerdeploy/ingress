<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Async\Monolog;

use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use function Amp\ByteStream\getStdout;
use function Amp\File\createDirectoryRecursively;
use function Amp\File\exists;
use function Amp\File\openFile;

final class LoggerFactory
{
    public static function create(): LoggerInterface
    {
        $level = match ((int) getenv('SHELL_VERBOSITY')) {
            -2 => LogLevel::EMERGENCY,
            -1 => LogLevel::ERROR,
            2 => LogLevel::NOTICE,
            3 => LogLevel::DEBUG,
            default => LogLevel::INFO,
        };

        $logger = new Logger('swarm-ingress');
        $stdOut = new StreamHandler(getStdout(), $level);
        $stdOut->setFormatter(new ConsoleFormatter());

        if (!exists(__CWD__.'/log/swarm-ingress.log')) {
            createDirectoryRecursively(__CWD__.'/data/log', 0755);
            touch(__CWD__.'/data/log/swarm-ingress.log');
        }
        $file = new StreamHandler(openFile(__CWD__.'/data/log/swarm-ingress.log', 'w'), $level);
        $file->setFormatter(new LineFormatter());

        $logger->pushHandler($file);
        $logger->pushHandler($stdOut);

        return $logger;
    }
}
