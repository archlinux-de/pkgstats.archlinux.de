<?php

namespace App\Tests\ValueResolver;

use App\Entity\Month;
use App\ValueResolver\StatisticsRangeValueResolver;
use App\Request\PkgstatsRequestException;
use App\Request\StatisticsRangeRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatisticsRangeValueResolverTest extends TestCase
{
    private ValidatorInterface&MockObject $validator;
    private StatisticsRangeValueResolver $statisticsRangeValueResolver;

    public function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->statisticsRangeValueResolver = new StatisticsRangeValueResolver($this->validator);
    }

    public function testDefault(): void
    {
        Month::resetBaseTimestamp();

        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                fn(StatisticsRangeRequest $_): ConstraintViolationList => new ConstraintViolationList()
            );

        $values = [...$this->statisticsRangeValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(StatisticsRangeRequest::class, $values[0]);
        /** @var StatisticsRangeRequest $statisticsRangeRequest */
        $statisticsRangeRequest = $values[0];
        $this->assertEquals(
            date(
                'Ym',
                strtotime('first day of this month -1 months')
            ),
            $statisticsRangeRequest->getStartMonth()
        );
        $this->assertEquals(
            date(
                'Ym',
                strtotime('first day of this month -1 months')
            ),
            $statisticsRangeRequest->getEndMonth()
        );
    }

    public function testResolve(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get', 'GET', ['startMonth' => 201801, 'endMonth' => 201812]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                fn(StatisticsRangeRequest $_): ConstraintViolationList => new ConstraintViolationList()
            );

        $values = [...$this->statisticsRangeValueResolver->resolve($request, $argument)];
        $this->assertCount(1, $values);

        $this->assertInstanceOf(StatisticsRangeRequest::class, $values[0]);
        /** @var StatisticsRangeRequest $statisticsRangeRequest */
        $statisticsRangeRequest = $values[0];
        $this->assertEquals(201801, $statisticsRangeRequest->getStartMonth());
        $this->assertEquals(201812, $statisticsRangeRequest->getEndMonth());
    }

    public function testResolveFailsOnValidationErrors(): void
    {
        /** @var ArgumentMetadata|MockObject $argument */
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument
            ->expects($this->atLeastOnce())
            ->method('getType')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                fn(StatisticsRangeRequest $_): ConstraintViolationList
                => new ConstraintViolationList([$this->createStub(ConstraintViolation::class)])
            );

        $this->expectException(PkgstatsRequestException::class);
        $this->statisticsRangeValueResolver->resolve($request, $argument);
    }
}
