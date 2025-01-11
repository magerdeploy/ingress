<?php

declare(strict_types=1);

namespace PRSW\Ingress\Store;

use DI\Attribute\Inject;
use Psr\Log\LoggerInterface;

use function Amp\File\createDirectoryRecursively;
use function Amp\File\exists;
use function Amp\File\read;
use function Amp\File\write;

final readonly class FileStorage implements StorageInterface
{
    /**
     * @param array<string,string> $options
     */
    public function __construct(
        private LoggerInterface $logger,
        #[Inject('storage.options')]
        private array $options,
    ) {}

    /**
     * @return array<int|string, mixed>
     */
    public function load(string $prefix): array
    {
        $fileName = $this->options['path'].'/'.$prefix;
        $dir = dirname($fileName);
        if (!exists($dir)) {
            createDirectoryRecursively($dir, 0755);
        }

        if (!exists($fileName)) {
            write($fileName, '');
        }

        $data = read($fileName);
        if ('' === $data) {
            return [];
        }

        return json_decode($data, true);
    }

    public function set(string $prefix, string $key, array|string $value): bool
    {
        $data = $this->load($prefix);
        $data[$key] = $value;

        return $this->writeToFile($prefix, json_encode($data));
    }

    public function del(string $prefix, string $key): bool
    {
        $data = $this->load($prefix);
        unset($data[$key]);

        return $this->writeToFile($prefix, json_encode($data));
    }

    public function get(string $prefix, string $key): array|string
    {
        $data = $this->load($prefix);

        return $data[$key];
    }

    private function writeToFile(string $prefix, string $data): bool
    {
        $success = true;

        try {
            write($this->options['path'].'/'.$prefix, $data);
        } catch (\Throwable $e) {
            $this->logger->error('error while writing file {msg}', ['msg' => $e->getMessage()]);
            $success = false;
        }

        return $success;
    }
}
