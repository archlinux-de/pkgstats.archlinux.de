<?php

namespace App\Tests\Serializer;

use App\Request\PkgstatsRequest;
use App\Serializer\PkgstatsRequestV2Denormalizer;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PkgstatsRequestV2DenormalizerTest extends TestCase
{
    /** @var GeoIp|MockObject */
    private $geoIp;

    /** @var MirrorUrlFilter|MockObject */
    private $mirrorUrlFilter;

    /** @var PkgstatsRequestV2Denormalizer */
    private $denormalizer;

    public function setUp(): void
    {
        $this->geoIp = $this->createMock(GeoIp::class);
        $this->mirrorUrlFilter = $this->createMock(MirrorUrlFilter::class);
        $this->denormalizer = new PkgstatsRequestV2Denormalizer(
            $this->geoIp,
            $this->mirrorUrlFilter
        );
    }

    public function testDenormalizeUser(): void
    {
        $this->mirrorUrlFilter->expects($this->once())->method('filter')->willReturnArgument(0);
        $this->geoIp
            ->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('DE');
        $context = [
            'clientIp' => 'abc',
            'userAgent' => 'pkgstats/2.4'
        ];

        $data = [
            'arch' => 'x86_64',
            'cpuarch' => 'x86_64',
            'mirror' => 'https://mirror.archlinux.de/'
        ];

        $pkgstatsRequest = $this->denormalizer->denormalize($data, PkgstatsRequest::class, 'form', $context);

        $this->assertInstanceOf(PkgstatsRequest::class, $pkgstatsRequest);
        $this->assertEquals('x86_64', $pkgstatsRequest->getOperatingSystemArchitecture()->getName());
        $this->assertEquals('x86_64', $pkgstatsRequest->getSystemArchitecture()->getName());
        $this->assertNotNull($pkgstatsRequest->getMirror());
        $this->assertEquals('https://mirror.archlinux.de/', $pkgstatsRequest->getMirror()->getUrl());
    }

    public function testDenormalizePackages(): void
    {
        $context = [
            'clientIp' => 'abc',
            'userAgent' => 'pkgstats/2.4'
        ];

        $data = [
            'packages' => implode("\n", ['foo', 'bar']),
        ];

        $pkgstatsRequest = $this->denormalizer->denormalize($data, PkgstatsRequest::class, 'form', $context);

        $this->assertInstanceOf(PkgstatsRequest::class, $pkgstatsRequest);
        /** @var PkgstatsRequest $pkgstatsRequest */
        $packages = $pkgstatsRequest->getPackages();
        $this->assertCount(2, $packages);
        $this->assertEquals('foo', $packages[0]->getName());
        $this->assertEquals('bar', $packages[1]->getName());
    }

    public function testSpportsDenormalization(): void
    {
        $this->assertTrue($this->denormalizer->supportsDenormalization([], PkgstatsRequest::class, 'form'));
        $this->assertTrue($this->denormalizer->hasCacheableSupportsMethod());
    }
}
