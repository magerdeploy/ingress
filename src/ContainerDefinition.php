<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress;

use AcmePhp\Core\AcmeClient;
use AcmePhp\Core\AcmeClientInterface;
use AcmePhp\Core\Http\Base64SafeEncoder;
use AcmePhp\Core\Http\SecureHttpClientFactory;
use AcmePhp\Core\Http\ServerErrorHandler;
use AcmePhp\Core\Protocol\ExternalAccount;
use AcmePhp\Ssl\Generator\KeyPairGenerator;
use AcmePhp\Ssl\KeyPair;
use AcmePhp\Ssl\Parser\KeyParser;
use AcmePhp\Ssl\PrivateKey;
use AcmePhp\Ssl\PublicKey;
use AcmePhp\Ssl\Signer\DataSigner;
use Amp\Http\Client\GuzzleAdapter\GuzzleHandlerAdapter;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use PRSW\Docker\Client;
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
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

use function Amp\ByteStream\getStdout;
use function Amp\File\createDirectoryRecursively;
use function Amp\File\exists;
use function Amp\File\openFile;

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
                'nginx_conf_path' => $_ENV['NGINX_CONF_PATH'] ?? '/app/data/nginx/nginx.conf',
                'nginx_vhost_dir' => $_ENV['NGINX_VHOST_DIR'] ?? '/app/data/nginx/sites-enabled',
                'nginx_vhost_ssl_key_path' => $_ENV['NGINX_VHOST_SSL_KEY_PATH'] ?? '/app/data/nginx/ssl/%s/private-key.pem',
                'nginx_vhost_ssl_certificate_path' => $_ENV['NGINX_VHOST_SSL_CERTIFICATE_PATH'] ?? '/app/data/nginx/ssl/%s/fullchain.pem',
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
            ClientInterface::class => static function (ContainerInterface $c) {
                return new GuzzleHttpClient([
                    'handler' => HandlerStack::create(new GuzzleHandlerAdapter()),
                ]);
            },
            AcmeClientInterface::class => static function (ContainerInterface $c) {
                $options = $c->get('acme.options');
                $factory = new SecureHttpClientFactory(
                    $c->get(ClientInterface::class),
                    new Base64SafeEncoder(),
                    new KeyParser(),
                    new DataSigner(),
                    new ServerErrorHandler()
                );
                $httpClient = $factory->createSecureHttpClient($c->get(KeyPair::class));
                $acme = new AcmeClient($httpClient, $options['directory_url']);
                $externalAccount = null;
                if (null !== $options['external_account']['id'] && null !== $options['external_account']['key']) {
                    $externalAccount = new ExternalAccount($options['external_account']['id'], $options['external_account']['key']);
                }

                $acme->registerAccount($options['email'], $externalAccount);

                return $acme;
            },
            RegistryManagerInterface::class => static fn (ContainerInterface $c) => $c->get(RegistryManager::class),
            LoggerInterface::class => static function () {
                $level = match ((int) getenv('SHELL_VERBOSITY')) {
                    -2 => LogLevel::EMERGENCY,
                    -1 => LogLevel::ERROR,
                    2 => LogLevel::INFO,
                    3 => LogLevel::DEBUG,
                    default => LogLevel::NOTICE,
                };

                $logger = new Logger('swarm-ingress');
                $stdOut = new StreamHandler(getStdout(), $level);
                $stdOut->setFormatter(new ConsoleFormatter());

                if (!exists(__DIR__.'/../data/log/swarm-ingress.log')) {
                    createDirectoryRecursively(__DIR__.'/../data/log', 0755);
                    touch(__DIR__.'/../data/log/swarm-ingress.log');
                }
                $file = new StreamHandler(openFile(__DIR__.'/../data/log/swarm-ingress.log', 'w'), $level);
                $file->setFormatter(new LineFormatter());

                $logger->pushHandler($file);
                $logger->pushHandler($stdOut);

                return $logger;
            },
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
