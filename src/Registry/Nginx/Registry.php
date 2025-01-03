<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry\Nginx;

use DI\Attribute\Inject;
use PRSW\SwarmIngress\Registry\AcmeHttpChallenge;
use PRSW\SwarmIngress\Registry\CanToManageUpstream;
use PRSW\SwarmIngress\Registry\Initializer;
use PRSW\SwarmIngress\Registry\RegistryInterface;
use PRSW\SwarmIngress\Registry\Reloadable;
use PRSW\SwarmIngress\TableCache\ServiceTable;
use PRSW\SwarmIngress\TableCache\SSLCertificateTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Twig\Environment;

final readonly class Registry implements RegistryInterface, Reloadable, Initializer, AcmeHttpChallenge, CanToManageUpstream
{
    /**
     * @param array<string,array<string,int|string>|string> $options
     */
    public function __construct(
        private LoggerInterface $logger,
        private Environment $twig,
        private ServiceTable $serviceTable,
        private SSLCertificateTable $SSLCertificateTable,
        #[Inject('nginx.options')]
        private array $options = []
    ) {}

    public function init(): void
    {
        $nginxConfig = $this->twig->render('nginx/nginx-conf.html.twig', $this->options['options']);

        $success = file_put_contents($this->options['nginx_conf_path'], $nginxConfig);
        if (false === $success) {
            $this->logger->error('failed to initialized nginx config');
        }
    }

    public function reload(): void
    {
        $checkConfig = new Process(['nginx', '-t']);
        $code = $checkConfig->run();
        if (0 !== $code) {
            $this->logger->error('invalid nginx configuration, reload aborted');

            return;
        }

        $reloadNginx = new Process(['nginx', '-s', 'reload']);
        $code = $reloadNginx->run();

        if (0 !== $code) {
            $this->logger->error('nginx reload failed');

            return;
        }

        $this->logger->info('nginx configuration reloaded');
    }

    public function addService(string $domain, string $path, string $upstream): void
    {
        mkdir($this->options['nginx_vhost_dir'], 0777, true);

        $this->serviceTable->addUpstream($domain, $upstream);

        $this->renderVhostConfig($domain, $path);

        $this->logger->info('nginx vhost added {domain}', ['domain' => $domain]);
    }

    public function removeService(string $domain, string $path, string $upstream): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/'.$domain;
        if (!file_exists($fileName)) {
            $this->logger->error('nginx virtual host config not found');

            return;
        }

        $success = unlink($fileName);
        if (!$success) {
            $this->logger->error('failed to remove nginx vhost config');

            return;
        }

        $this->serviceTable->del($domain);
        $this->logger->info('nginx vhost deleted {domain}', ['domain' => $domain]);
    }

    public function serveHttpChallenge(string $domain, string $token, string $payload): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/acme_'.$domain;
        $acme = $this->twig->render('nginx/acme-challenge.html.twig', [
            'domain' => $domain,
            'token' => $token,
            'payload' => $payload,
        ]);

        file_put_contents($fileName, $acme);
    }

    public function cleanup(string $domain): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/acme_'.$domain;

        unlink($fileName);
    }

    public function addUpstream(string $domain, string $path, string $upstream): void
    {
        $this->serviceTable->addUpstream($domain, $upstream);
        $this->renderVhostConfig($domain, $path);
    }

    public function removeUpstream(string $domain, string $path, string $upstream): void
    {
        $this->serviceTable->removeUpstream($domain, $upstream);
        $this->renderVhostConfig($domain, $path);
    }

    private function renderVhostConfig(string $domain, string $path): void
    {
        $fileName = $this->options['nginx_vhost_dir'].'/'.$domain;
        $upstream = $this->serviceTable->getUpstream($domain);

        $vhost = $this->twig->render('nginx/site-conf.html.twig', [
            'upstream' => $upstream,
            'path' => $path,
            'domain' => $domain,
        ] + $this->dumpCertificate($domain));

        file_put_contents($fileName, $vhost);
    }

    /**
     * @return array<string,array<string, string>|bool>
     */
    private function dumpCertificate(string $domain): array
    {
        $ssl = $this->SSLCertificateTable->get($domain);
        $sslEnabled = false !== $ssl;
        $keyPath = sprintf($this->options['nginx_vhost_ssl_key_path'], $domain);
        $certPath = sprintf($this->options['nginx_vhost_ssl_certificate_path'], $domain);

        if ($sslEnabled) {
            mkdir(dirname($keyPath), 0777, true);
            file_put_contents($keyPath, $ssl['private_key']);
            file_put_contents($certPath, $ssl['certificate']);
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
