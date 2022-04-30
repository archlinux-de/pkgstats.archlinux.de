<?php

namespace App\Tests\ParamConverter;

use App\ParamConverter\PkgstatsParamConverter;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverterTest extends TestCase
{
    /** @var ValidatorInterface|MockObject */
    private MockObject $validator;

    private PkgstatsParamConverter $paramConverter;

    /** @var MockObject|SerializerInterface */
    private MockObject $serializer;

    public function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->serializer
            ->expects($this->any())
            ->method('deserialize')
            ->willReturn(new PkgstatsRequest('3.2.2'));

        $this->paramConverter = new PkgstatsParamConverter($this->validator, $this->serializer);
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

        $request = Request::create('/api/submit');
        $request->headers->set('Content-Type', 'application/json');

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
        $this->assertEquals('3.2.2', $pkgstatsRequest->getVersion());
    }

    public function testApplyFailsOnValidationErrors(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);

        $request = Request::create('/api/submit');
        $request->headers->set('Content-Type', 'application/json');

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
}
