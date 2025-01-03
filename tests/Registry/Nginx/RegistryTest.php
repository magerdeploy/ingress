<?php
declare(strict_types=1);

namespace PRSW\SwarmIngress\Tests\Registry\Nginx;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PRSW\SwarmIngress\Registry\Nginx\Registry;
use PRSW\SwarmIngress\TableCache\SslCertificateTable;
use PRSW\SwarmIngress\TableCache\ServiceTable;
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
    private string $nginxConfigPath;

    public function setUp(): void
    {
        $loader = new FilesystemLoader(__DIR__.'/../../templates');

        $this->twig = new Environment($loader);
        $this->logger = $this->createMock(LoggerInterface::class);
        // @phpstan-ignore-next-line
        $this->serviceTable = $this->createMock(ServiceTable::class);
        // @phpstan-ignore-next-line
        $this->SSLCertificateTable = $this->createMock(SslCertificateTable::class);
        $this->nginxConfigPath = tempnam(sys_get_temp_dir(), 'nginx.conf');
    }

    public function testInitSuccess(): void
    {
        $nginxConfig = ['nginx_conf_path' => $this->nginxConfigPath, 'options' => []];
        $this->nginx = new Registry($this->logger, $this->twig, $this->serviceTable, $this->SSLCertificateTable, $nginxConfig);

        $this->logger->expects($this->never())->method('error');

        $this->nginx->init();

        $this->assertTrue(file_exists($this->nginxConfigPath));
        $this->assertNotEmpty(file_get_contents($this->nginxConfigPath));
    }
}