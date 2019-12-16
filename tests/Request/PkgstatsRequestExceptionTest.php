<?php

namespace App\Tests\Request;

use App\Request\PkgstatsRequestException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class PkgstatsRequestExceptionTest extends TestCase
{
    public function testMessageIsRendered(): void
    {
        /** @var ConstraintViolationInterface|MockObject $violationA */
        $violationA = $this->createMock(ConstraintViolationInterface::class);
        $violationA
            ->expects($this->once())
            ->method('getInvalidValue')
            ->willReturn('foo');
        $violationA
            ->expects($this->once())
            ->method('getMessage')
            ->willReturn('bar');

        /** @var ConstraintViolationInterface|MockObject $violationB */
        $violationB = $this->createMock(ConstraintViolationInterface::class);
        $violationB
            ->expects($this->once())
            ->method('getInvalidValue')
            ->willReturn(['nope']);
        $violationB
            ->expects($this->once())
            ->method('getMessage')
            ->willReturn('baz');

        $violationList = new ConstraintViolationList([$violationA, $violationB]);

        $requestException = new PkgstatsRequestException($violationList);

        $this->assertStringContainsString('foo', $requestException->getMessage());
        $this->assertStringContainsString('bar', $requestException->getMessage());
        $this->assertStringContainsString('nope', $requestException->getMessage());
        $this->assertStringContainsString('baz', $requestException->getMessage());
    }
}
