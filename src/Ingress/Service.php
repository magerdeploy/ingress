<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Ingress;

use PRSW\Docker\Generated\Model\ContainersIdJsonGetResponse200 as DockerContainer;

final class Service implements \Stringable
{
    public string $name;
    public string $domain;
    public string $path;
    public string $port;
    public string $type;
    public string $upstream;

    public function __toString(): string
    {
        return sprintf(
            '%s - %s - %s - %s',
            $this->name,
            $this->upstream,
            $this->domain,
            $this->path
        );
    }

    public static function fromDockerContainer(DockerContainer $container): self
    {
        $obj = new self();

        /** @var \ArrayObject<string, string> $labels */
        $labels = $container->getConfig()->getLabels();

        $isEnable = $labels['ingress.enable'] ?? null;
        if ('true' !== $isEnable) {
            throw new \InvalidArgumentException(sprintf('Ingress service not enabled for this container %s', $container->getName()));
        }

        $obj->type = 'container';

        if ($labels->offsetExists('com.docker.compose.project')) {
            $obj->name = $labels['com.docker.compose.project'].'-'.$labels['com.docker.compose.service'];
            $obj->type = 'docker-compose';
        } else {
            $obj->name = str_replace('/', '', $container->getName());
        }

        $obj->domain = $labels['ingress.domain'];
        $obj->path = $labels['ingress.path'] ?? '/';
        $obj->port = $labels['ingress.port'] ?? '8000';
        $obj->upstream = $obj->name.':'.$obj->port;

        return $obj;
    }

    public function getTableKey(): string
    {
        return $this->domain;
    }
}
