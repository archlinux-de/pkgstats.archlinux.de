<?php

namespace App\Tests\Serializer;

use App\Request\PkgstatsRequest;
use App\Serializer\PkgstatsRequestV3Denormalizer;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PkgstatsRequestV3DenormalizerTest extends TestCase
{
    /** @var GeoIp|MockObject */
    private $geoIp;

    /** @var MirrorUrlFilter|MockObject */
    private $mirrorUrlFilter;

    /** @var PkgstatsRequestV3Denormalizer */
    private $denormalizer;

    public function setUp(): void
    {
        $this->geoIp = $this->createMock(GeoIp::class);
        $this->mirrorUrlFilter = $this->createMock(MirrorUrlFilter::class);
        $this->denormalizer = new PkgstatsRequestV3Denormalizer(
            $this->geoIp,
            $this->mirrorUrlFilter
        );
    }

    public function testDenormalizeRequest(): void
    {
        $this->mirrorUrlFilter->expects($this->once())->method('filter')->willReturnArgument(0);
        $context = [
            'clientIp' => 'abc',
            'userAgent' => 'pkgstats/2.4'
        ];

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
        $this->assertTrue($this->denormalizer->hasCacheableSupportsMethod());
    }
}
