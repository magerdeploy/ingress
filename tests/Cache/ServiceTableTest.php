<?php

declare(strict_types=1);

namespace PRSW\Ingress\Tests\Cache;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PRSW\Ingress\Cache\ServiceTable;
use PRSW\Ingress\Store\StorageInterface;
use Psl\Hash\Algorithm;

use function Psl\Hash\hash;

class ServiceTableTest extends TestCase
{
    private MockObject|StorageInterface $storage;
    private ServiceTable $serviceTable;

    public function setUp(): void
    {
        $this->storage = $this->createMock(StorageInterface::class);
        $this->serviceTable = new ServiceTable($this->storage);
    }

    public function testAddUpstreamToEmptyList(): void
    {
        $key = 'example.com';
        $path = '/';
        $upstream = 'web:80';

        $pathHash = hash($path, Algorithm::Xxh32);

        $this->storage->expects($this->once())
            ->method('set')
            ->with($this->serviceTable->getName(), $key, [$pathHash => ['path' => $path, 'upstream' => [$upstream => 1]]])
        ;

        $this->serviceTable->addUpstream($key, $path, $upstream);
    }

    public function testAddUpstreamToNonEmptyList(): void
    {
        $key = 'example.com';
        $path = '/';
        $upstream = 'web:80';

        $pathHash = hash($path, Algorithm::Xxh32);

        $this->storage->expects($this->once())
            ->method('load')
            ->with($this->serviceTable->getName())
            ->willReturn([$key => [$pathHash => ['path' => $path, 'upstream' => [$upstream => 1]]]])
        ;

        $this->storage->expects($this->once())
            ->method('set')
            ->with($this->serviceTable->getName(), $key, [$pathHash => ['path' => $path, 'upstream' => [$upstream => 1, 'web-2:80' => 1]]])
        ;

        $this->serviceTable->load();
        $this->serviceTable->addUpstream($key, $path, 'web-2:80');
    }

    public function testRemoveUpstreamUntilEmpty(): void
    {
        $key = 'example.com';
        $path = '/';
        $upstream = 'web:80';

        $pathHash = hash($path, Algorithm::Xxh32);

        $this->storage->expects($this->once())
            ->method('load')
            ->with($this->serviceTable->getName())
            ->willReturn([$key => [$pathHash => ['path' => $path, 'upstream' => [$upstream => 1]]]])
        ;

        $this->serviceTable->load();
        $this->serviceTable->removeUpstream($key, $path, $upstream);
        $this->assertEmpty($this->serviceTable->get($key, $pathHash)['upstream']);
    }

    public function testRemoveUpstreamUntilNotEmpty(): void
    {
        $key = 'example.com';
        $path = '/';
        $upstream1 = 'web:80';
        $upstream2 = 'web-1:80';

        $pathHash = hash($path, Algorithm::Xxh32);

        $this->storage->expects($this->once())
            ->method('load')
            ->with($this->serviceTable->getName())
            ->willReturn([$key => [$pathHash => ['path' => $path, 'upstream' => [$upstream1 => 1, $upstream2 => 2]]]])
        ;

        $this->serviceTable->load();
        $this->serviceTable->removeUpstream($key, $path, $upstream2);
        $this->assertEquals(['path' => $path, 'upstream' => [$upstream1 => 1]], $this->serviceTable->get($key, $pathHash));
    }

    public function testUpstreamWhenDataIsEmpty(): void
    {
        $this->assertEmpty($this->serviceTable->getUpstream('domain.com', '/'));
    }
}
