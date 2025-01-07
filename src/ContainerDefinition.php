<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress;

use AcmePhp\Core\AcmeClientInterface;
use AcmePhp\Ssl\Generator\KeyPairGenerator;
use AcmePhp\Ssl\KeyPair;
use AcmePhp\Ssl\PrivateKey;
use AcmePhp\Ssl\PublicKey;
use GuzzleHttp\ClientInterface;
use PRSW\Docker\Client;
use PRSW\SwarmIngress\Async\Acme\ClientFactory as AcmeClientFactory;
use PRSW\SwarmIngress\Async\Guzzle\HttpClientFactory;
use PRSW\SwarmIngress\Async\Monolog\LoggerFactory;
use PRSW\SwarmIngress\Async\Twig\AsyncFileSystemLoader;
use PRSW\SwarmIngress\Cache\ConfigTable;
use PRSW\SwarmIngress\Lock\LockInterface;
use PRSW\SwarmIngress\Lock\MutexLock;
use PRSW\SwarmIngress\Registry\Nginx\Registry;
use PRSW\SwarmIngress\Registry\RegistryInterface;
use PRSW\SwarmIngress\Registry\RegistryManager;
use PRSW\SwarmIngress\Registry\RegistryManagerInterface;
use PRSW\SwarmIngress\Store\FileStorage;
use PRSW\SwarmIngress\Store\StorageInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

final class ContainerDefinition
{
    /**
     * @return array<string, mixed>
     */
    public static function getDefinition(): array
    {
        return [
            'registry' => $_ENV['registry'] ?? 'nginx',
            'storage' => $_ENV['storage'] ?? 'file',
            'storage.options' => [
                'path' => __DIR__.'/../data',
            ],
            'docker.client.options' => [
                'max_duration' => -1,
                'timeout' => -1,
            ],
            'service.table.options' => [
                'table_row_size' => 1024,
                'upstream_size' => 512000,
            ],
            'nginx.options' => [
                'nginx_conf_path' => $_ENV['NGINX_CONF_PATH'] ?? '/etc/nginx/nginx.conf',
                'nginx_vhost_dir' => $_ENV['NGINX_VHOST_DIR'] ?? '/etc/nginx/sites-enabled',
                'nginx_vhost_ssl_key_path' => $_ENV['NGINX_VHOST_SSL_KEY_PATH'] ?? '/etc/nginx/ssl/%s/private-key.pem',
                'nginx_vhost_ssl_certificate_path' => $_ENV['NGINX_VHOST_SSL_CERTIFICATE_PATH'] ?? '/app/nginx/ssl/%s/fullchain.pem',
                'options' => [
                    'client_max_body_size' => $_ENV['NGINX_CLIENT_MAX_BODY_SIZE'] ?? '16M',
                    'worker_connections' => $_ENV['NGINX_CLIENT_MAX_CONNECTIONS'] ?? 65535,
                ],
            ],
            'self_signed.options' => [
                'ca' => $_ENV['SELF_SIGNED_CA'],
            ],
            'acme.options' => [
                'email' => $_ENV['ACME_EMAIL'] ?? 'admin@localhost',
                'directory_url' => $_ENV['ACME_DIRECTORY_URL'] ?? 'https://acme-v02.api.letsencrypt.org/directory',
                'external_account' => [
                    'id' => $_ENV['ACME_EXTERNAL_ACCOUNT_ID'],
                    'key' => $_ENV['ACME_EXTERNAL_ACCOUNT_KEY'],
                ],
                'max_sanity_check_tries' => $_ENV['ACME_MAX_SANITY_CHECK_MAX_TRIES'] ?? 5,
                'sanity_check_interval' => $_ENV['ACME_SANITY_CHECK_INTERVAL'] ?? 60,
            ],
            // @phpstan-ignore-next-line
            RegistryInterface::class => static fn (ContainerInterface $c) => match (true) {
                'nginx' === $c->get('registry') => $c->get(Registry::class)
            },
            // @phpstan-ignore-next-line
            StorageInterface::class => static fn (ContainerInterface $c) => match (true) {
                'file' === $c->get('storage') => $c->get(FileStorage::class)
            },
            // @phpstan-ignore-next-line
            LockInterface::class => static fn (ContainerInterface $c) => match (true) {
                'file' === $c->get('storage') => MutexLock::class
            },
            ClientInterface::class => static fn () => HttpClientFactory::create(),
            AcmeClientInterface::class => static fn (ContainerInterface $c) => AcmeClientFactory::create($c),
            RegistryManagerInterface::class => static fn (ContainerInterface $c) => $c->get(RegistryManager::class),
            LoggerInterface::class => static fn () => LoggerFactory::create(),
            Client::class => static fn (ContainerInterface $c) => Client::withHttpClient(options: $c->get('docker.client.options')),
            Environment::class => static function (ContainerInterface $c) {
                $loader = new AsyncFileSystemLoader(__DIR__.'/../templates');

                return new Environment($loader);
            },
            KeyPair::class => static function (ContainerInterface $c) {
                /** @var ConfigTable $config */
                $config = $c->get(ConfigTable::class);

                if (!$config->exist('acme.private_key') || !$config->exist('acme.public_key')) {
                    $k = new KeyPairGenerator();
                    $pair = $k->generateKeyPair();
                    $config->set('acme.private_key', $pair->getPrivateKey()->getDER());
                    $config->set('acme.public_key', $pair->getPublicKey()->getDER());

                    return $pair;
                }

                return new KeyPair(
                    PublicKey::fromDER($config->get('acme.private_key')),
                    PrivateKey::fromDER($config->get('acme.public_key'))
                );
            },
        ];
    }
}
