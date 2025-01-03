<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Acme;

use AcmePhp\Core\AcmeClientInterface;
use AcmePhp\Core\Challenge\Http\HttpValidator;
use AcmePhp\Core\Challenge\Http\SimpleHttpSolver;
use AcmePhp\Core\Challenge\WaitingValidator;
use AcmePhp\Ssl\Certificate;
use AcmePhp\Ssl\CertificateRequest;
use AcmePhp\Ssl\CertificateResponse;
use AcmePhp\Ssl\DistinguishedName;
use AcmePhp\Ssl\KeyPair;
use AcmePhp\Ssl\Parser\CertificateParser;
use PRSW\SwarmIngress\Lock\LockFactory;
use PRSW\SwarmIngress\Registry\AcmeHttpChallenge;
use PRSW\SwarmIngress\Registry\RegistryInterface;
use PRSW\SwarmIngress\Registry\Reloadable;
use PRSW\SwarmIngress\TableCache\SSLCertificateTable;
use Psr\Log\LoggerInterface;

final readonly class LetsEncrypt implements AcmeInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private SSLCertificateTable $SSLCertificateTable,
        private LockFactory $lockFactory,
        private AcmeClientInterface $acmeClient,
        private LoggerInterface $logger,
        private KeyPair $keyPair,
        private CertificateParser $certificateParser,
    ) {}

    public function createNewCertificate(string $domain): void
    {
        if (!$this->registry instanceof AcmeHttpChallenge) {
            $this->logger->warning('acme http challenge not supported in {registry}', ['registry' => $this->registry::class]);

            return;
        }

        $lock = $this->lockFactory->create($domain);
        $success = $lock->lock(60 * 10);

        if (!$success) {
            $this->logger->error('failed to acquire lock when generating acme certificate');
        }
        defer(static fn () => $lock->release());

        if ($this->SSLCertificateTable->exist($domain)) {
            return;
        }

        $order = $this->acmeClient->requestOrder([$domain]);
        if ('pending' !== $order->getStatus()) {
            return;
        }

        $challenge = null;
        foreach ($order->getAuthorizationChallenges($domain) as $challenge) {
            if ('http-01' === $challenge->getType()) {
                break;
            }
        }

        if (null === $challenge) {
            $this->logger->error('invalid acme authorization challenge {domain}', ['domain' => $domain]);

            return;
        }

        $this->serveHttpChallenge($domain, $challenge->getToken(), $challenge->getPayload());

        $solver = new SimpleHttpSolver();
        $validator = new WaitingValidator(new HttpValidator());
        if (!$validator->supports($challenge, $solver)) {
            $this->logger->error('invalid acme authorization challenge {domain}', ['domain' => $domain]);

            return;
        }

        if (!$validator->isValid($challenge, $solver)) {
            $this->logger->error('failed to pass internal authorization challenge {domain}', ['domain' => $domain]);

            return;
        }

        try {
            $check = $this->acmeClient->challengeAuthorization($challenge);
            if ('valid' !== $check['status']) {
                $this->logger->error('failed to pass CA authorization challenge {domain}', ['domain' => $domain]);

                return;
            }

            $this->cleanup($domain);

            $order = $this->acmeClient->reloadOrder($order);

            $csr = new CertificateRequest(new DistinguishedName($domain), $this->keyPair);
            $response = $this->acmeClient->finalizeOrder($order, $csr);

            $this->save($domain, $response);
        } catch (\Exception $e) {
            $this->logger->error(
                'failed to generate acme certificate {domain}: {$message}',
                ['domain' => $domain, $e->getMessage()]
            );
        }
    }

    public function renew(string $domain): void
    {
        if (!$this->registry instanceof AcmeHttpChallenge) {
            $this->logger->warning('acme http challenge not supported in {registry}', ['registry' => $this->registry::class]);

            return;
        }

        $lock = $this->lockFactory->create($domain);
        $success = $lock->lock(60 * 10);
        if (!$success) {
            $this->logger->error('failed to acquire lock when generating acme certificate');

            return;
        }
        defer(static fn () => $lock->release());

        try {
            $response = $this->acmeClient->requestCertificate(
                $domain,
                new CertificateRequest(new DistinguishedName($domain), $this->keyPair)
            );

            $this->save($domain, $response);
        } catch (\Exception $e) {
            $this->logger->error(
                'failed to renew acme certificate {domain}: {$message}',
                ['domain' => $domain, $e->getMessage()]
            );
        }
    }

    public function save(string $domain, CertificateResponse $response): void
    {
        $certificate = $response->getCertificate();
        $parsedCertificate = $this->certificateParser->parse($certificate);

        $issuerChain = array_map(static fn (Certificate $certificate) => $certificate->getPEM(), $certificate->getIssuerChain());
        $fullChainPem = $certificate->getPEM()."\n".implode("\n", $issuerChain);

        $this->SSLCertificateTable->setCertificate(
            $domain,
            $this->keyPair->getPublicKey()->getPEM(),
            $fullChainPem,
            $parsedCertificate->getValidTo(),
        );
    }

    private function serveHttpChallenge(string $domain, string $token, string $payload): void
    {
        if ($this->registry instanceof AcmeHttpChallenge) {
            $this->registry->serveHttpChallenge($domain, $token, $payload);
            if ($this->registry instanceof Reloadable) {
                $this->registry->reload();
            }
        }
    }

    private function cleanup(string $domain): void
    {
        if ($this->registry instanceof AcmeHttpChallenge) {
            $this->registry->cleanup($domain);
            if ($this->registry instanceof Reloadable) {
                $this->registry->reload();
            }
        }
    }
}
