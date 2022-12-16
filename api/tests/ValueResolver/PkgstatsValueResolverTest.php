<?php

namespace App\Tests\ValueResolver;

use App\ValueResolver\PkgstatsValueResolver;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsValueResolverTest extends TestCase
{
    /** @var ValidatorInterface|MockObject */
    private MockObject $validator;

    private PkgstatsValueResolver $pkgstatsValueResolver;

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

        $this->pkgstatsValueResolver = new PkgstatsValueResolver($this->validator, $this->serializer);
    }

    public function testResolveVersion(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
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

        $values = [...$this->pkgstatsValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(PkgstatsRequest::class, $values[0]);
        /** @var PkgstatsRequest $pkgstatsRequest */
        $pkgstatsRequest = $values[0];
        $this->assertEquals('3.2.2', $pkgstatsRequest->getVersion());
    }

    public function testResolveFailsOnValidationErrors(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PkgstatsRequest::class);

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
        $this->pkgstatsValueResolver->resolve($request, $argument);
    }
}
