<?php

namespace App\Tests\ParamConverter;

use App\ParamConverter\PkgstatsParamConverter;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverterTest extends TestCase
{
    /** @var GeoIp|MockObject */
    private $geoIp;

    /** @var ValidatorInterface|MockObject */
    private $validator;

    /** @var PkgstatsParamConverter */
    private $paramConverter;

    /** @var MirrorUrlFilter|MockObject */
    private $mirrorUrlFilter;

    /** @var MockObject|SerializerInterface */
    private $serializer;

    /** @var MockObject|DenormalizerInterface */
    private $denormalizer;

    public function setUp(): void
    {
        $this->geoIp = $this->createMock(GeoIp::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->mirrorUrlFilter = $this->createMock(MirrorUrlFilter::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->denormalizer = $this->createMock(DenormalizerInterface::class);

        $this->denormalizer
            ->expects($this->any())
            ->method('denormalize')
            ->willReturn(new PkgstatsRequest('2.4'));
        $this->serializer
            ->expects($this->any())
            ->method('deserialize')
            ->willReturn(new PkgstatsRequest('2.4'));

        $this->paramConverter = new PkgstatsParamConverter($this->validator, $this->serializer, $this->denormalizer);
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

    /**
     * @param string $contentType
     * @dataProvider provideContentTypes
     */
    public function testApplyVersion(string $contentType): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PkgstatsRequest::class);

        $request = Request::create('/post');
        $request->server->set('HTTP_USER_AGENT', 'pkgstats/2.4');
        $request->headers->set('Content-Type', $contentType);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                function (PkgstatsRequest $_) {
                    return new ConstraintViolationList();
                }
            );

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PkgstatsRequest::class, $request->attributes->get(PkgstatsRequest::class));
        /** @var PkgstatsRequest $pkgstatsRequest */
        $pkgstatsRequest = $request->attributes->get(PkgstatsRequest::class);
        $this->assertEquals(2.4, $pkgstatsRequest->getVersion());
    }

    /**
     * @param string $contentType
     * @dataProvider provideContentTypes
     */
    public function testApplyFailsOnValidationErrors(string $contentType): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);

        $request = Request::create('/post');
        $request->server->set('HTTP_USER_AGENT', 'pkgstats/2.4');
        $request->headers->set('Content-Type', $contentType);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                function (PkgstatsRequest $_) {
                    return new ConstraintViolationList([$this->createMock(ConstraintViolation::class)]);
                }
            );

        $this->expectException(PkgstatsRequestException::class);
        $this->paramConverter->apply($request, $configuration);
    }

    /**
     * @return array
     */
    public function provideContentTypes(): array
    {
        return [
            ['application/json'],
            ['pplication/x-www-form-urlencoded'],
        ];
    }
}
