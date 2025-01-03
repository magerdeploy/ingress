<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Store;

use DI\Attribute\Inject;
use PRSW\SwarmIngress\Lock\LockFactory;

final readonly class FileStorage implements StorageInterface
{
    /**
     * @param array<string,string> $options
     */
    public function __construct(
        private LockFactory $lockFactory,
        #[Inject('storage.options')]
        private array $options,
    ) {}

    public function load(string $prefix): array
    {
        $fileName = $this->options['path'].'/'.$prefix;
        $dir = dirname($fileName);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = file_get_contents($fileName);
        if ('' === $data || '0' === $data || false === $data) {
            return [];
        }

        return json_decode($data, true);
    }

    public function set(string $prefix, string $key, array $value): bool
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

    public function get(string $prefix, string $key): array
    {
        $data = $this->load($prefix);

        return $data[$key];
    }

    private function writeToFile(string $prefix, string $data): bool
    {
        $lock = $this->lockFactory->create('file');
        $lock->lock();
        defer(static fn () => $lock->release());

        return (bool) file_put_contents($this->options['path'].'/'.$prefix, $data);
    }
}
