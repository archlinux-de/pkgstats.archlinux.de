<?php

namespace App\Tests\Service;

use App\Service\GeoIp;
use MaxMind\Db\Reader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeoIpTest extends TestCase
{
    /** @var Reader|MockObject */
    private $reader;
    /** @var LoggerInterface|MockObject */
    private $logger;
    /** @var GeoIp */
    private $geoIp;

    public function setUp(): void
    {
        $this->reader = $this->getMockBuilder(Reader::class)->disableOriginalConstructor()->getMock();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->geoIp = new GeoIp($this->reader, $this->logger);
    }

    public function testGeoIpReturnesCountryCode()
    {
        $this->reader->method('get')->willReturn(['country' => ['iso_code' => 'DE']]);
        $this->assertEquals('DE', $this->geoIp->getCountryCode('::1'));
    }

    public function testGeoIpReturnesNullOnError()
    {
        $this->reader->method('get')->willThrowException(new \Exception());
        $this->assertNull($this->geoIp->getCountryCode('foo'));
    }

    public function testGeoIpLogsErrors()
    {
        $this->reader->method('get')->willThrowException(new \Exception(':-('));
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
