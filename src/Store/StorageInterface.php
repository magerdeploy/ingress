<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Store;

interface StorageInterface
{
    /**
     * @param array<string, float|int|string> $value
     */
    public function set(string $prefix, string $key, array $value): bool;

    public function del(string $prefix, string $key): bool;

    /**
     * @return array<string, float|int|string>
     */
    public function get(string $prefix, string $key): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function load(string $prefix): array;
}
