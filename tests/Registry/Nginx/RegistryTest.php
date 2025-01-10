<?php
declare(strict_types=1);

namespace PRSW\SwarmIngress\Tests\Registry\Nginx;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PRSW\SwarmIngress\Ingress\Service;
use PRSW\SwarmIngress\Registry\Nginx\Registry;
use PRSW\SwarmIngress\Cache\SslCertificateTable;
use PRSW\SwarmIngress\Cache\ServiceTable;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class RegistryTest extends TestCase
{
    private Environment $twig;
    private LoggerInterface|MockObject $logger;
    private ServiceTable|MockObject $serviceTable;
    private SslCertificateTable|MockObject $SSLCertificateTable;
    private Registry $nginx;

    /**
     * @var array <string, mixed>
     */
    private array $nginxConfig;

    public function setUp(): void
    {
        $loader = new FilesystemLoader(__DIR__.'/../../../templates');

        $this->twig = new Environment($loader);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->serviceTable = $this->createMock(ServiceTable::class);
        $this->SSLCertificateTable = $this->createMock(SslCertificateTable::class);

        $this->nginxConfig = [
            'nginx_conf_path' => tempnam(sys_get_temp_dir(), 'nginx.conf'),
            'nginx_vhost_dir' => sys_get_temp_dir().'/vhost',
            'nginx_vhost_ssl_key_path' => tempnam(sys_get_temp_dir(), 'ssl-cert'),
            'nginx_vhost_ssl_certificate_path' => tempnam(sys_get_temp_dir(), 'ssl-key'),
            'options' => []
        ];

        $this->nginx = new Registry($this->logger, $this->twig, $this->serviceTable, $this->SSLCertificateTable, $this->nginxConfig);
    }

    public function testInitSuccess(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->nginx->init();

        $this->assertTrue(file_exists($this->nginxConfig['nginx_conf_path']));
        $this->assertNotEmpty(file_get_contents($this->nginxConfig['nginx_conf_path']));
    }

    public function testAddServiceSuccess(): void
    {
        $service = new Service();
        $service->type = Service::TYPE_CONTAINER;
        $service->path = '/';
        $service->upstream = 'test.com:80';
        $service->name = 'example';
        $service->domain = 'example.com';

        $this->serviceTable->expects($this->once())
            ->method('addUpstream')
            ->with($service->getIdentifier(), $service->upstream);
        $this->serviceTable->expects($this->once())
            ->method('getUpstream')
            ->with($service->getIdentifier())
            ->willReturn([$service->upstream]);
        $this->SSLCertificateTable->expects($this->once())->method('get')->with($service->domain)->willReturn(null);

        $this->nginx->addService($service);

        $this->assertNotEmpty(file_get_contents($this->nginxConfig['nginx_vhost_dir'].'/'.$service->getIdentifier()));
    }
}