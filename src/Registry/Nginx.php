<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use DI\Attribute\Inject;
use PRSW\SwarmIngress\TableCache\SSLCertificateTable;
use PRSW\SwarmIngress\TableCache\UpstreamTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Twig\Environment;

final readonly class Nginx implements RegistryInterface, Reloadable, Initializer, AcmeHttpChallenge
{
    /**
     * @param array<string,array<string,int|string>|string> $nginxConfig
     */
    public function __construct(
        private LoggerInterface $logger,
        private Environment $twig,
        private UpstreamTable $upstreamTable,
        private SSLCertificateTable $SSLCertificateTable,
        #[Inject('nginx.config')]
        private array $nginxConfig = []
    ) {}

    public function init(): void
    {
        $nginxConfig = $this->twig->render('nginx/nginx-conf.html.twig', $this->nginxConfig['options']);

        $success = file_put_contents($this->nginxConfig['nginx_conf_path'], $nginxConfig);
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

    public function addVirtualHost(string $domain, string $path, string $upstream): void
    {
        mkdir($this->nginxConfig['nginx_vhost_dir'], 755, true);

        $fileName = $this->nginxConfig['nginx_vhost_dir'].'/'.$domain;

        $upstream = $this->upstreamTable->getUpstream($domain);
        $upstream[] = $upstream;

        $vhost = $this->twig->render('nginx/site-conf.html.twig', [
            'upstream' => $upstream,
            'path' => $path,
            'domain' => $domain,
        ] + $this->dumpCertificate($domain));

        file_put_contents($fileName, $vhost);

        $this->logger->info('nginx vhost upstream added {domain} - ', ['domain' => $domain, 'upstream' => $upstream]);
    }

    public function removeVirtualHost(string $domain, string $path, string $upstream): void
    {
        $fileName = $this->nginxConfig['nginx_vhost_dir'].'/'.$domain;
        if (!file_exists($fileName)) {
            $this->logger->error('nginx virtual host config not found');

            return;
        }

        $upstreamList = $this->upstreamTable->getUpstream($domain);
        unset($upstreamList[$upstream]);

        if ([] === $upstreamList) {
            $success = unlink($fileName);
            if (!$success) {
                $this->logger->error('failed to remove nginx vhost config');

                return;
            }
            $this->logger->info('nginx vhost config deleted {domain}', ['domain' => $domain]);

            return;
        }

        $vhost = $this->twig->render('nginx/site-conf.html.twig', [
            'upstream' => $upstreamList,
            'path' => $path,
            'domain' => $domain,
        ] + $this->dumpCertificate($domain));

        file_put_contents($fileName, $vhost);

        $this->logger->info('nginx vhost upstream deleted {domain} - {upstream}', ['domain' => $domain, 'upstream' => $upstream]);
    }

    public function refreshSSLCertificate(string $domain): void
    {
        $fileName = $this->nginxConfig['nginx_vhost_dir'].'/'.$domain;
        $upstream = $this->upstreamTable->getUpstream($domain);

        $vhost = $this->twig->render('nginx/site-conf.html.twig', [
            'upstream' => $upstream,
            'domain' => $domain,
        ] + $this->dumpCertificate($domain));

        file_put_contents($fileName, $vhost);
    }

    public function serveHttpChallenge(string $domain, string $token, string $payload): void
    {
        $fileName = $this->nginxConfig['nginx_vhost_dir'].'/acme_'.$domain;
        $acme = $this->twig->render('nginx/acme-challenge.html.twig', [
            'domain' => $domain,
            'token' => $token,
            'payload' => $payload,
        ]);

        file_put_contents($fileName, $acme);
    }

    public function cleanup(string $domain): void
    {
        $fileName = $this->nginxConfig['nginx_vhost_dir'].'/acme_'.$domain;

        unlink($fileName);
    }

    /**
     * @return array<string,array<string, string>|bool>
     */
    private function dumpCertificate(string $domain): array
    {
        $ssl = $this->SSLCertificateTable->get($domain) ?? null;
        $sslEnabled = null !== $ssl;
        $keyPath = sprintf($this->nginxConfig['nginx_vhost_ssl_key_path'], $domain);
        $certPath = sprintf($this->nginxConfig['nginx_vhost_ssl_certificate_path'], $domain);

        if ($sslEnabled) {
            mkdir(dirname($keyPath), 755, true);
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
