<?php
declare(strict_types=1);

namespace PRSW\SwarmIngress\Tests\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PRSW\SwarmIngress\Registry\Nginx;
use PRSW\SwarmIngress\TableCache\SSLCertificateTable;
use PRSW\SwarmIngress\TableCache\UpstreamTable;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class NginxTest extends TestCase
{
    private Environment $twig;
    private LoggerInterface|MockObject $logger;
    private UpstreamTable|MockObject $upstreamTable;
    private SSLCertificateTable|MockObject $SSLCertificateTable;
    private Nginx $nginx;
    private string $nginxConfigPath;

    public function setUp(): void
    {
        $loader = new FilesystemLoader(__DIR__.'/../../templates');

        $this->twig = new Environment($loader);
        $this->logger = $this->createMock(LoggerInterface::class);
        // @phpstan-ignore-next-line
        $this->upstreamTable = $this->createMock(UpstreamTable::class);
        // @phpstan-ignore-next-line
        $this->SSLCertificateTable = $this->createMock(SSLCertificateTable::class);
        $this->nginxConfigPath = tempnam(sys_get_temp_dir(), 'nginx.conf');
    }

    public function testInitSuccess(): void
    {
        $nginxConfig = ['nginx_conf_path' => $this->nginxConfigPath, 'options' => []];
        $this->nginx = new Nginx($this->logger, $this->twig, $this->upstreamTable, $this->SSLCertificateTable, $nginxConfig);

        $this->logger->expects($this->never())->method('error');

        $this->nginx->init();

        $this->assertTrue(file_exists($this->nginxConfigPath));
        $this->assertNotEmpty(file_get_contents($this->nginxConfigPath));
    }
}