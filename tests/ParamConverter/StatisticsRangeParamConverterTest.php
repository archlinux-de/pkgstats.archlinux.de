<?php

namespace App\Tests\ParamConverter;

use App\ParamConverter\StatisticsRangeParamConverter;
use App\Request\StatisticsRangeRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

class StatisticsRangeParamConverterTest extends TestCase
{
    /** @var StatisticsRangeParamConverter */
    private $paramConverter;

    public function setUp(): void
    {
        $this->paramConverter = new StatisticsRangeParamConverter(0);
    }

    public function testSupports()
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getClass')
            ->willReturn(StatisticsRangeRequest::class);

        $this->assertTrue($this->paramConverter->supports($configuration));
    }

    public function testDefault()
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get');

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(
            StatisticsRangeRequest::class,
            $request->attributes->get(StatisticsRangeRequest::class)
        );
        /** @var StatisticsRangeRequest $statisticsRangeRequest */
        $statisticsRangeRequest = $request->attributes->get(StatisticsRangeRequest::class);
        $this->assertEquals(date('Ym'), $statisticsRangeRequest->getStartMonth());
        $this->assertEquals(date('Ym'), $statisticsRangeRequest->getEndMonth());
    }

    public function testApply()
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(StatisticsRangeRequest::class);

        $request = Request::create('/get', 'GET', ['startMonth' => 201801, 'endMonth' => 201812]);

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
}
