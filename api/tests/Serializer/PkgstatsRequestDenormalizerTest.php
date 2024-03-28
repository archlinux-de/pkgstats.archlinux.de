<?php

namespace App\Tests\Serializer;

use App\Request\PkgstatsRequest;
use App\Serializer\PkgstatsRequestDenormalizer;
use App\Service\GeoIpInterface;
use App\Service\MirrorUrlFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PkgstatsRequestDenormalizerTest extends TestCase
{
    private GeoIpInterface&MockObject $geoIp;
    private MirrorUrlFilter&MockObject $mirrorUrlFilter;
    private PkgstatsRequestDenormalizer $denormalizer;

    public function setUp(): void
    {
        $this->geoIp = $this->createMock(GeoIpInterface::class);
        $this->mirrorUrlFilter = $this->createMock(MirrorUrlFilter::class);
        $this->denormalizer = new PkgstatsRequestDenormalizer(
            $this->geoIp,
            $this->mirrorUrlFilter
        );
    }

    public function testDenormalizeRequest(): void
    {
        $this->mirrorUrlFilter->expects($this->once())->method('filter')->willReturnArgument(0);
        $this->geoIp
            ->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('DE');
        $context = ['clientIp' => 'abc'];

        $data = [
            'version' => '3',
            'system' => [
                'architecture' => 'x86_64'
            ],
            'os' => [
                'architecture' => 'x86_64'
            ],
            'pacman' => [
                'mirror' => 'https://mirror.archlinux.de/',
                'packages' => ['foo', 'bar']
            ]
        ];

        $pkgstatsRequest = $this->denormalizer->denormalize($data, PkgstatsRequest::class, 'form', $context);

        $this->assertInstanceOf(PkgstatsRequest::class, $pkgstatsRequest);
        $this->assertEquals('x86_64', $pkgstatsRequest->getOperatingSystemArchitecture()->getName());
        $this->assertEquals('x86_64', $pkgstatsRequest->getSystemArchitecture()->getName());
        $this->assertNotNull($pkgstatsRequest->getMirror());
        $this->assertEquals('https://mirror.archlinux.de/', $pkgstatsRequest->getMirror()->getUrl());
        $packages = $pkgstatsRequest->getPackages();
        $this->assertCount(2, $packages);
        $this->assertEquals('foo', $packages[0]->getName());
        $this->assertEquals('bar', $packages[1]->getName());
    }

    public function testSpportsDenormalization(): void
    {
        $this->assertTrue($this->denormalizer->supportsDenormalization([], PkgstatsRequest::class, 'json'));
    }
}
