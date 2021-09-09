<?php

namespace App\Tests\ParamConverter;

use App\ParamConverter\PaginationParamConverter;
use App\Request\PaginationRequest;
use App\Request\PkgstatsRequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PaginationParamConverterTest extends TestCase
{
    /** @var ValidatorInterface|MockObject */
    private MockObject $validator;

    private PaginationParamConverter $paramConverter;

    public function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->paramConverter = new PaginationParamConverter($this->validator);
    }

    public function testApply(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PaginationRequest::class);

        $request = Request::create('/get', 'GET', ['offset' => 2, 'limit' => 42]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PaginationRequest $_) {
                return new ConstraintViolationList();
            });

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PaginationRequest::class, $request->attributes->get(PaginationRequest::class));
        /** @var PaginationRequest $paginationRequest */
        $paginationRequest = $request->attributes->get(PaginationRequest::class);
        $this->assertEquals(2, $paginationRequest->getOffset());
        $this->assertEquals(42, $paginationRequest->getLimit());
    }

    public function testDefaults(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getName')
            ->willReturn(PaginationRequest::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PaginationRequest $_) {
                return new ConstraintViolationList();
            });

        $this->assertTrue($this->paramConverter->apply($request, $configuration));

        $this->assertInstanceOf(PaginationRequest::class, $request->attributes->get(PaginationRequest::class));
        /** @var PaginationRequest $paginationRequest */
        $paginationRequest = $request->attributes->get(PaginationRequest::class);
        $this->assertEquals(0, $paginationRequest->getOffset());
        $this->assertEquals(100, $paginationRequest->getLimit());
    }

    public function testSupports(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->expects($this->once())
            ->method('getClass')
            ->willReturn(PaginationRequest::class);

        $this->assertTrue($this->paramConverter->supports($configuration));
    }

    public function testApplyFailsOnValidationErrors(): void
    {
        /** @var ParamConverter|MockObject $configuration */
        $configuration = $this->createMock(ParamConverter::class);

        $request = Request::create('/get');

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function (PaginationRequest $_) {
                return new ConstraintViolationList([$this->createMock(ConstraintViolation::class)]);
            });

        $this->expectException(PkgstatsRequestException::class);
        $this->paramConverter->apply($request, $configuration);
    }
}
