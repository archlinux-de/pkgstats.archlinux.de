<?php

namespace App\Tests\Service;

use App\Service\GeoIp;
use MaxMind\Db\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeoIpTest extends TestCase
{
    private Reader&MockObject $reader;
    private LoggerInterface&MockObject $logger;
    private GeoIp $geoIp;

    public function setUp(): void
    {
        $this->reader = $this->getMockBuilder(Reader::class)->disableOriginalConstructor()->getMock();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->geoIp = new GeoIp($this->reader, $this->logger);
    }

    public function testGeoIpReturnesCountryCode(): void
    {
        $this->reader->expects($this->once())->method('get')->willReturn(['country' => ['iso_code' => 'DE']]);
        $this->logger->expects($this->never())->method('error');
        $this->assertEquals('DE', $this->geoIp->getCountryCode('::1'));
    }

    public function testGeoIpReturnesNullOnError(): void
    {
        $this->reader->expects($this->once())->method('get')->willThrowException(new \Exception());
        $this->logger->expects($this->once())->method('error');
        $this->assertNull($this->geoIp->getCountryCode('foo'));
    }

    public function testGeoIpLogsErrors(): void
    {
        $this->reader->expects($this->once())->method('get')->willThrowException(new \Exception(':-('));
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo(':-('),
                $this->arrayHasKey('exception')
            );
        $this->geoIp->getCountryCode('foo');
    }
}
