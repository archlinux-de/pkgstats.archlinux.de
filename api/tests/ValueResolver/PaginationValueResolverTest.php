<?php

namespace App\Tests\ValueResolver;

use App\ValueResolver\PaginationValueResolver;
use App\Request\PaginationRequest;
use App\Request\PkgstatsRequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PaginationValueResolverTest extends TestCase
{
    /** @var ValidatorInterface|MockObject */
    private MockObject $validator;

    private PaginationValueResolver $paginationValueResolver;

    public function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->paginationValueResolver = new PaginationValueResolver($this->validator);
    }

    public function testResolve(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PaginationRequest::class);

        $request = Request::create('/get', 'GET', ['offset' => 2, 'limit' => 42]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PaginationRequest $_) {
                return new ConstraintViolationList();
            });

        $values = [...$this->paginationValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(PaginationRequest::class, $values[0]);
        /** @var PaginationRequest $paginationRequest */
        $paginationRequest = $values[0];
        $this->assertEquals(2, $paginationRequest->getOffset());
        $this->assertEquals(42, $paginationRequest->getLimit());
    }

    public function testDefaults(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PaginationRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PaginationRequest $_) {
                return new ConstraintViolationList();
            });

        $values = [...$this->paginationValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(PaginationRequest::class, $values[0]);
        /** @var PaginationRequest $paginationRequest */
        $paginationRequest = $values[0];
        $this->assertEquals(0, $paginationRequest->getOffset());
        $this->assertEquals(100, $paginationRequest->getLimit());
    }

    public function testResolveFailsOnValidationErrors(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PaginationRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PaginationRequest $_) {
                return new ConstraintViolationList([$this->createMock(ConstraintViolation::class)]);
            });

        $this->expectException(PkgstatsRequestException::class);
        $this->paginationValueResolver->resolve($request, $argument);
    }
}
