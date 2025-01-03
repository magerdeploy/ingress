<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\SslCertificate;

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
use DI\Attribute\Inject;
use GuzzleHttp\ClientInterface;
use PRSW\SwarmIngress\Lock\LockFactory;
use PRSW\SwarmIngress\Registry\AcmeHttpChallenge;
use PRSW\SwarmIngress\Registry\RegistryInterface;
use PRSW\SwarmIngress\Registry\Reloadable;
use PRSW\SwarmIngress\TableCache\SslCertificateTable;
use Psr\Log\LoggerInterface;

final readonly class AcmeGenerator implements CertificateGeneratorInterface
{
    /**
     * @param array<string,array<string,int|string>|int|string> $options
     */
    public function __construct(
        private RegistryInterface $registry,
        private SslCertificateTable $SSLCertificateTable,
        private LockFactory $lockFactory,
        private AcmeClientInterface $acmeClient,
        private LoggerInterface $logger,
        private KeyPair $keyPair,
        private CertificateParser $certificateParser,
        private ClientInterface $httpClient,
        #[Inject('acme.options')]
        private array $options
    ) {}

    public function createNewCertificate(string $domain): void
    {
        if (!$this->registry instanceof AcmeHttpChallenge) {
            $this->logger->warning('acme http challenge not supported in {registry}', ['registry' => $this->registry::class]);

            return;
        }

        $this->sanityCheck($domain);

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

    private function sanityCheck(string $domain): void
    {
        $this->serveHttpChallenge($domain, 'dummy', 'dummy');

        $try = 0;
        while (true) {
            if ($try > (int) $this->options['max_sanity_check_tries']) {
                $this->cleanup($domain);

                throw new \Exception(
                    sprintf('failed to get sanity check for acme challenge after trying 5 times %s', $domain)
                );
            }

            try {
                $response = $this->httpClient->request(
                    'GET',
                    "http://{$domain}/.well-known/acme-challenge/dummy",
                    [
                        'timeout' => 5,
                    ]
                );
                if (200 === $response->getStatusCode()) {
                    break;
                }
                $this->logger->warning('failed to get sanity check for acme challenge {domain} {status}', ['domain' => $domain, 'status' => $response->getStatusCode()]);
            } catch (\Exception $e) {
                $this->logger->warning('failed to get sanity check for acme challenge {domain} {msg}', ['domain' => $domain, 'msg' => $e->getMessage()]);
            } finally {
                sleep($this->options['sanity_check_interval']);
                ++$try;
            }
        }
    }
}
