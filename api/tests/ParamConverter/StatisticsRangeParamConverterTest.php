<?php

namespace App\Tests\ParamConverter;

use App\ParamConverter\StatisticsRangeParamConverter;
use App\Request\PkgstatsRequestException;
use App\Request\StatisticsRangeRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatisticsRangeParamConverterTest extends TestCase
{
    /** @var ValidatorInterface|MockObject */
    private $validator;

    /** @var StatisticsRangeParamConverter */
    private $paramConverter;

    public function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->paramConverter = new StatisticsRangeParamConverter($this->validator);
    }

    public function testSupports(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getClass')
            ->willReturn(StatisticsRangeRequest::class);

        $this->assertTrue($this->paramConverter->supports($configuration));
    }

    public function testDefault(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                function (StatisticsRangeRequest $_) {
                    return new ConstraintViolationList();
                }
            );

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(
            StatisticsRangeRequest::class,
            $request->attributes->get(StatisticsRangeRequest::class)
        );
        /** @var StatisticsRangeRequest $statisticsRangeRequest */
        $statisticsRangeRequest = $request->attributes->get(StatisticsRangeRequest::class);
        $this->assertEquals(date('Ym', strtotime('-1 month')), $statisticsRangeRequest->getStartMonth());
        $this->assertEquals(date('Ym', strtotime('-1 month')), $statisticsRangeRequest->getEndMonth());
    }

    public function testApply(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get', 'GET', ['startMonth' => 201801, 'endMonth' => 201812]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                function (StatisticsRangeRequest $_) {
                    return new ConstraintViolationList();
                }
            );

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(
            StatisticsRangeRequest::class,
            $request->attributes->get(StatisticsRangeRequest::class)
        );
        /** @var StatisticsRangeRequest $statisticsRangeRequest */
        $statisticsRangeRequest = $request->attributes->get(StatisticsRangeRequest::class);
        $this->assertEquals(201801, $statisticsRangeRequest->getStartMonth());
        $this->assertEquals(201812, $statisticsRangeRequest->getEndMonth());
    }

    public function testApplyFailsOnValidationErrors(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                function (StatisticsRangeRequest $_) {
                    return new ConstraintViolationList([$this->createMock(ConstraintViolation::class)]);
                }
            );

        $this->expectException(PkgstatsRequestException::class);
        $this->paramConverter->apply($request, $configuration);
    }
}
