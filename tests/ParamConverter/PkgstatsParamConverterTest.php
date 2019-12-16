<?php

namespace App\Tests\ParamConverter;

use App\ParamConverter\PkgstatsParamConverter;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use App\Service\ClientIdGenerator;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverterTest extends TestCase
{
    /** @var GeoIp|MockObject */
    private $geoIp;

    /** @var ClientIdGenerator|MockObject */
    private $clientIdGenerator;

    /** @var ValidatorInterface|MockObject */
    private $validator;

    /** @var PkgstatsParamConverter */
    private $paramConverter;

    /** @var MirrorUrlFilter|MockObject */
    private $mirrorUrlFilter;

    public function setUp(): void
    {
        $this->geoIp = $this->createMock(GeoIp::class);
        $this->clientIdGenerator = $this->createMock(ClientIdGenerator::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->mirrorUrlFilter = $this->createMock(MirrorUrlFilter::class);

        $this->paramConverter = new PkgstatsParamConverter(
            $this->geoIp,
            $this->clientIdGenerator,
            $this->validator,
            $this->mirrorUrlFilter
        );
    }

    public function testSupportsPkgStatsRequest(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getClass')
            ->willReturn(PkgstatsRequest::class);

        $this->assertTrue($this->paramConverter->supports($configuration));
    }

    public function testRejectUnsupportedRequest(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getClass')
            ->willReturn('foo');

        $this->assertFalse($this->paramConverter->supports($configuration));
    }

    public function testApplyVersion(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PkgstatsRequest::class);

        $request = Request::create('/post');
        $request->server->set('HTTP_USER_AGENT', 'pkgstats/2.4');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PkgstatsRequest $_) {
                return new ConstraintViolationList();
            });

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PkgstatsRequest::class, $request->attributes->get(PkgstatsRequest::class));
        /** @var PkgstatsRequest $pkgstatsRequest */
        $pkgstatsRequest = $request->attributes->get(PkgstatsRequest::class);
        $this->assertEquals(2.4, $pkgstatsRequest->getVersion());
    }

    public function testApplyQuiet(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PkgstatsRequest::class);

        $request = Request::create('/post');
        $request->request->set('quiet', 'true');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PkgstatsRequest $_) {
                return new ConstraintViolationList();
            });

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PkgstatsRequest::class, $request->attributes->get(PkgstatsRequest::class));
        /** @var PkgstatsRequest $pkgstatsRequest */
        $pkgstatsRequest = $request->attributes->get(PkgstatsRequest::class);
        $this->assertTrue($pkgstatsRequest->isQuiet());
    }

    public function testApplyUser(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PkgstatsRequest::class);

        $this->mirrorUrlFilter->expects($this->once())->method('filter')->willReturnArgument(0);

        $request = Request::create('/post');
        $request->request->set('arch', 'x86_64');
        $request->request->set('cpuarch', 'x86_64');
        $request->request->set('mirror', 'https://mirror.archlinux.de/');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PkgstatsRequest $_) {
                return new ConstraintViolationList();
            });

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PkgstatsRequest::class, $request->attributes->get(PkgstatsRequest::class));
        /** @var PkgstatsRequest $pkgstatsRequest */
        $pkgstatsRequest = $request->attributes->get(PkgstatsRequest::class);
        $user = $pkgstatsRequest->getUser();
        $this->assertEquals('x86_64', $user->getArch());
        $this->assertEquals('x86_64', $user->getCpuarch());
        $this->assertEquals('https://mirror.archlinux.de/', $user->getMirror());
    }

    public function testApplyPackages(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PkgstatsRequest::class);

        $request = Request::create('/post');
        $request->request->set('packages', implode("\n", ['foo', 'bar']));

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PkgstatsRequest $_) {
                return new ConstraintViolationList();
            });

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PkgstatsRequest::class, $request->attributes->get(PkgstatsRequest::class));
        /** @var PkgstatsRequest $pkgstatsRequest */
        $pkgstatsRequest = $request->attributes->get(PkgstatsRequest::class);
        $packages = $pkgstatsRequest->getPackages();
        $this->assertCount(2, $packages);
        $this->assertEquals('foo', $packages[0]->getName());
        $this->assertEquals('bar', $packages[1]->getName());
    }

    public function testApplyFailsOnValidationErrors(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);

        $request = Request::create('/post');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PkgstatsRequest $_) {
                return new ConstraintViolationList([$this->createMock(ConstraintViolation::class)]);
            });

        $this->expectException(PkgstatsRequestException::class);
        $this->paramConverter->apply($request, $configuration);
    }
}
