<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Ingress;

use PRSW\Docker\Generated\Model\ContainersIdJsonGetResponse200 as DockerContainer;
use PRSW\Docker\Generated\Model\Service as DockerService;

final class Service
{
    public const string TYPE_CONTAINER = 'container';
    public const string TYPE_SERVICE = 'service';
    public const string TYPE_DOCKER_COMPOSE = 'docker-compose';

    public const string AUTO_TLS_SELF_SIGNED = 'self-signed';
    public const string AUTO_TLS_ACME = 'acme';

    public string $id;
    public string $name;
    public string $domain;
    public string $path;
    public string $port;
    public string $type;
    public string $upstream;
    public ?string $autoTls = null;

    public static function fromDockerService(DockerService $service): self
    {
        $obj = new self();

        /** @var \ArrayObject<string, string> $labels */
        $labels = $service->getSpec()->getLabels();

        $isEnable = $labels['ingress.enable'] ?? null;
        if ('true' !== $isEnable) {
            throw new \InvalidArgumentException(
                sprintf('service not enabled for this container %s', $service->getSpec()->getName())
            );
        }

        $obj->id = $service->getId();
        $obj->name = $service->getSpec()->getName();
        $obj->type = self::TYPE_SERVICE;

        return self::setCommonData($labels, $obj);
    }

    public static function fromDockerContainer(DockerContainer $container): self
    {
        $obj = new self();

        /** @var \ArrayObject<string, string> $labels */
        $labels = $container->getConfig()->getLabels();

        $isEnable = $labels['ingress.enable'] ?? null;
        if ('true' !== $isEnable) {
            throw new \InvalidArgumentException(sprintf('service not enabled for this container %s', $container->getName()));
        }

        $obj->type = self::TYPE_CONTAINER;
        $obj->id = $container->getId();

        if ($labels->offsetExists('com.docker.compose.project')) {
            $obj->name = $labels['com.docker.compose.project'].'-'.$labels['com.docker.compose.service'];
            $obj->type = self::TYPE_DOCKER_COMPOSE;
        } else {
            $obj->name = str_replace('/', '', $container->getName());
        }

        return self::setCommonData($labels, $obj);
    }

    public static function fromServiceId(string $id): self
    {
        $obj = new self();
        $obj->id = $id;
        $obj->type = self::TYPE_SERVICE;

        return $obj;
    }

    public function getIdentifier(): string
    {
        if (self::TYPE_CONTAINER === $this->type) {
            return $this->domain;
        }

        return $this->id;
    }

    /**
     * @param \ArrayObject<string, string> $labels
     */
    private static function setCommonData(\ArrayObject $labels, Service $obj): Service
    {
        $obj->domain = $labels['ingress.domain'];
        $obj->path = $labels['ingress.path'] ?? '/';
        $obj->port = $labels['ingress.port'] ?? '8000';
        $obj->autoTls = $labels['ingress.auto_tls'] ?? null;
        $obj->upstream = $obj->name.':'.$obj->port;

        return $obj;
    }
}
