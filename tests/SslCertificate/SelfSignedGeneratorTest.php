<?php
declare(strict_types=1);

namespace PRSW\Ingress\Tests\SslCertificate;

use AcmePhp\Ssl\Generator\KeyPairGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PRSW\Ingress\SslCertificate\SelfSignedGenerator;
use PRSW\Ingress\Cache\SslCertificateTable;
use Psr\Log\LoggerInterface;

class SelfSignedGeneratorTest extends TestCase
{
    private SelfSignedGenerator $generator;
    private SslCertificateTable|MockObject $sslCertificateTable;

    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->sslCertificateTable = $this->createMock(SslCertificateTable::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->generator = new SelfSignedGenerator(
            (new KeyPairGenerator())->generateKeyPair(),
            $this->sslCertificateTable,
            $this->logger,
            ['ca' => '']
        );
    }

    public function testGenerateWithValidDomain(): void
    {
        $domain = 'example.com';
        $this->sslCertificateTable->expects($this->once())->method('setCertificate');
        $this->generator->generate($domain);
    }

    public function testWithCACertificate(): void
    {
        $ca = <<<CA
-----BEGIN CERTIFICATE-----
MIIE4DCCA0igAwIBAgIRAMb76nIzLzArpWnV3Vhl96UwDQYJKoZIhvcNAQELBQAw
gYcxHjAcBgNVBAoTFW1rY2VydCBkZXZlbG9wbWVudCBDQTEuMCwGA1UECwwlam93
eUBQcmFzLVVidW50dSAoUHJhc2V0eW8gV2ljYWtzb25vKTE1MDMGA1UEAwwsbWtj
ZXJ0IGpvd3lAUHJhcy1VYnVudHUgKFByYXNldHlvIFdpY2Frc29ubykwHhcNMjUw
MTAzMjA1NDIyWhcNMzUwMTAzMjA1NDIyWjCBhzEeMBwGA1UEChMVbWtjZXJ0IGRl
dmVsb3BtZW50IENBMS4wLAYDVQQLDCVqb3d5QFByYXMtVWJ1bnR1IChQcmFzZXR5
byBXaWNha3Nvbm8pMTUwMwYDVQQDDCxta2NlcnQgam93eUBQcmFzLVVidW50dSAo
UHJhc2V0eW8gV2ljYWtzb25vKTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCCAYoC
ggGBAMaluehIWHKyTu39Mhae23eKs/h5HdFklD+F1k4wLM11KnBUD+9s9dwf7R8L
MJr8lAEnp5f6hq+M+f5hej34osdXGvoVRNOUBjCDseuCQoBia/C+NBDX94fpYP6p
u/sEDNXqew4e762gc6o/OKlp8DWC+48PAQ9abKIffAwLLe9vddD1JJNBwKpJttPS
+H1Z8ecqyC70Fj8RhzuG8KYakhftN+uafdJmKRIr+N4sqYdcReI97Wui3LHsZ36Y
Qexac8CzKOTxuDwgUueyZLOB+312c73Y0y7xkkV6pfjBNv5vH/vREJQOD0sO/LRB
l8iuggMKAfOAJevtJmHTht43ei09+tNXQCGaEBiKviHQgsn/pt2OShb33k4adPGj
zjLCy953vLW4wekswjG75gn+rADh94r84mXsdxNSm2ZJXjFFUI5cix1UQBqpJPpc
5DbyN+C+T+37ahHtkU3VigwtjPM4nCqtcvNxDXEW8oSO4QfSrGmgDfRppWxKLHMu
PDYF1QIDAQABo0UwQzAOBgNVHQ8BAf8EBAMCAgQwEgYDVR0TAQH/BAgwBgEB/wIB
ADAdBgNVHQ4EFgQUwo1zOqtfIytb5GDG8d37AzP+3xMwDQYJKoZIhvcNAQELBQAD
ggGBAG0+j9ht5n75El/tPUSSafTB7YSSvETLgXcu0agEpUj7MEjF5MoCJ6U+P7KV
yG8c2ScDH7tKhpRGuEtpSA650uCp0AhguYocdRt76pSM5wy1Urmu1eKvZ183AhLN
vo0FhLbqH+GMKJ1Gp/WdWVugcD+eNANDzH97T3r07PuVpN3hgoNIo2vI7mUXXcGE
R7tGyuNLv4r4pbiLT2O7SL32uFEIpTLsu2BIWLUpVzPrzJVovbYqsRIgR/sg8oc1
v+6II5wH19EFsZAx4ivoRgVOWvps8ikyIWH/Q5FtZeAZ0No8OISp8BnooxY0mDgM
I76TKyeBNsLwoz1rer20MwGJhGJnUCJHsOooiIrTF23L6h0g2iQIqwIgz7kRLd3q
Wr3UMbkV1GN+ToMzOUkJoX58c+p74/2q0u/x1JCoyGtpMmstzpKE8Ub02SyJ7WUG
KXl8ItHp+QVz9mG/5s73q3pp1i0QgvZqRbQNF7RoxYNjLGQK3v3Fd3jnuf87zzKc
G6J2Zw==
-----END CERTIFICATE-----
CA;
        $caPkey = <<<CAPKEY
-----BEGIN PRIVATE KEY-----
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
3O0l8vR4hCzVy6hn8ICzlQ==
-----END PRIVATE KEY-----
CAPKEY;


        $this->generator = new SelfSignedGenerator(
            (new KeyPairGenerator())->generateKeyPair(),
            $this->sslCertificateTable,
            $this->logger,
            ['ca' => $ca, 'ca_private_key' => $caPkey]
        );

        $domain = 'example.com';
        $this->sslCertificateTable->expects($this->once())->method('setCertificate');
        $this->generator->generate($domain);
    }
}