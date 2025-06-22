<?php

namespace App\Tests\ValueResolver;

use App\ValueResolver\QueryValueResolver;
use App\Request\PackageQueryRequest;
use App\Request\PkgstatsRequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class QueryValueResolverTest extends TestCase
{
    private ValidatorInterface&MockObject $validator;
    private QueryValueResolver $queryValueResolver;

    public function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->queryValueResolver = new QueryValueResolver($this->validator);
    }

    public function testResolve(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PackageQueryRequest::class);

        $request = Request::create('/get', 'GET', ['query' => 'foo']);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(fn(PackageQueryRequest $_): ConstraintViolationList => new ConstraintViolationList());

        $values = [...$this->queryValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(PackageQueryRequest::class, $values[0]);
        /** @var PackageQueryRequest $packageQueryRequest */
        $packageQueryRequest = $values[0];
        $this->assertEquals('foo', $packageQueryRequest->getQuery());
    }

    public function testDefault(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PackageQueryRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(fn(PackageQueryRequest $_): ConstraintViolationList => new ConstraintViolationList());

        $values = [...$this->queryValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(PackageQueryRequest::class, $values[0]);
        /** @var PackageQueryRequest $packageQueryRequest */
        $packageQueryRequest = $values[0];
        $this->assertEquals('', $packageQueryRequest->getQuery());
    }

    public function testResolveFailsOnValidationErrors(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(PackageQueryRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(fn(PackageQueryRequest $_): ConstraintViolationList
            => new ConstraintViolationList([$this->createMock(ConstraintViolation::class)]));

        $this->expectException(PkgstatsRequestException::class);
        $this->queryValueResolver->resolve($request, $argument);
    }
}
