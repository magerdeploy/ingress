<?php

declare(strict_types=1);

namespace PRSW\Ingress\Registry\Nginx;

use Amp\Process\Process;
use DI\Attribute\Inject;
use PRSW\Ingress\Cache\ServiceTable;
use PRSW\Ingress\Cache\SslCertificateTable;
use PRSW\Ingress\Registry\AcmeHttpChallenge;
use PRSW\Ingress\Registry\CanToManageUpstream;
use PRSW\Ingress\Registry\Initializer;
use PRSW\Ingress\Registry\RegistryInterface;
use PRSW\Ingress\Registry\Reloadable;
use PRSW\Ingress\Registry\Service;
use Psr\Log\LoggerInterface;
use Twig\Environment;

use function Amp\File\createDirectoryRecursively;
use function Amp\File\deleteFile;
use function Amp\File\exists;
use function Amp\File\write;

final readonly class Registry implements RegistryInterface, Reloadable, Initializer, AcmeHttpChallenge, CanToManageUpstream
{
    /**
     * @param array<string,array<string,int|string>|string> $options
     */
    public function __construct(
        private LoggerInterface $logger,
        private Environment $twig,
        private ServiceTable $serviceTable,
        private SslCertificateTable $SSLCertificateTable,
        #[Inject('nginx.options')]
        private array $options = []
    ) {}

    public function init(): void
    {
        $nginxConfig = $this->twig->render('nginx/nginx-conf.html.twig', $this->options);
        write($this->options['nginx_conf_path'], $nginxConfig);
    }

    public function reload(): void
    {
        if (!$this->checkConfig()) {
            return;
        }

        $reloadNginx = Process::start(['nginx', '-s', 'reload']);
        if (0 !== $reloadNginx->join()) {
            $this->logger->error('nginx reload failed');

            return;
        }

        $this->logger->info('nginx configuration reloaded');
    }

    public function addService(Service $service): void
    {
        if (!exists($this->options['nginx_vhost_dir'])) {
            createDirectoryRecursively($this->options['nginx_vhost_dir'], 0755);
        }

        $this->serviceTable->addUpstream($service->getIdentifier(), $service->upstream);

        $this->refresh($service);

        $this->logger->info('nginx vhost added', ['service' => $service]);
    }

    public function removeService(Service $service): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/'.$service->getIdentifier();
        if (!exists($fileName)) {
            $this->logger->error('nginx virtual host config not found');

            return;
        }

        deleteFile($fileName);

        $this->serviceTable->del($service->getIdentifier());
        $this->logger->info('nginx vhost deleted', ['service' => $service]);
    }

    public function serveHttpChallenge(string $domain, string $token, string $payload): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/acme_'.$domain;
        $acme = $this->twig->render('nginx/acme-challenge.html.twig', [
            'domain' => $domain,
            'token' => $token,
            'payload' => $payload,
        ]);

        write($fileName, $acme);
    }

    public function cleanup(string $domain): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/acme_'.$domain;

        deleteFile($fileName);
    }

    public function addUpstream(Service $service): void
    {
        $this->serviceTable->addUpstream($service->getIdentifier(), $service->upstream);
        $this->refresh($service);
    }

    public function removeUpstream(Service $service): void
    {
        $this->serviceTable->removeUpstream($service->getIdentifier(), $service->upstream);
        $this->refresh($service);
    }

    public function refresh(Service $service): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/'.$service->getIdentifier();
        $upstream = $this->serviceTable->getUpstream($service->getIdentifier());

        $vhost = $this->twig->render('nginx/site-conf.html.twig', [
            'upstream' => $upstream,
            'path' => $service->path,
            'domain' => $service->domain,
        ] + $this->dumpCertificate($service->domain));

        write($fileName, $vhost);
    }

    private function checkConfig(): bool
    {
        $checkConfig = Process::start(['nginx', '-t']);
        if (0 !== $checkConfig->join()) {
            $this->logger->error('invalid nginx configuration, operation aborted');

            return false;
        }

        return true;
    }

    /**
     * @return array<string,array<string, string>|bool>
     */
    private function dumpCertificate(string $domain): array
    {
        $ssl = $this->SSLCertificateTable->get($domain);
        $sslEnabled = null !== $ssl;
        $keyPath = sprintf($this->options['nginx_vhost_ssl_key_path'], $domain);
        $certPath = sprintf($this->options['nginx_vhost_ssl_certificate_path'], $domain);

        if ($sslEnabled) {
            if (!exists(dirname($keyPath))) {
                createDirectoryRecursively(dirname($keyPath), 0755);
            }

            write($keyPath, $ssl['private_key']);
            write($certPath, $ssl['certificate']);
        }

        return [
            'ssl_enabled' => $sslEnabled,
            'certificate' => [
                'cert' => $certPath,
                'key' => $keyPath,
            ],
        ];
    }
}
