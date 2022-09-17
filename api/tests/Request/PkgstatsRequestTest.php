<?php

namespace App\Tests\Request;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Request\PkgstatsRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PkgstatsRequestTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        /** @var Package $package */
        $package = $this->createMock(Package::class);

        $request = (new PkgstatsRequest('1.0'))
            ->addPackage($package)
            ->setCountry(new Country('DE'))
            ->setMirror(new Mirror('http://localhost'))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture('x86_64'))
            ->setSystemArchitecture(new SystemArchitecture('x86_64_v4'));

        $this->assertEquals('1.0', $request->getVersion());

        $packages = $request->getPackages();
        $this->assertCount(1, $packages);
        $this->assertSame($package, $packages[0]);

        $this->assertNotNull($request->getCountry());
        $this->assertEquals('DE', $request->getCountry()->getCode());
        $this->assertNotNull($request->getMirror());
        $this->assertEquals('http://localhost', $request->getMirror()->getUrl());
        $this->assertEquals('x86_64', $request->getOperatingSystemArchitecture()->getName());
        $this->assertEquals('x86_64_v4', $request->getSystemArchitecture()->getName());
    }

    /**
     * @dataProvider provideValidArchitecutres
     */
    public function testValidOperatingSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = (new PkgstatsRequest('3'))
            ->setSystemArchitecture(new SystemArchitecture($cpuArch))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture($osArch));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context
            ->expects($this->never())
            ->method('addViolation');

        $request->validateOperatingSystemArchitectures($context);
    }

    /**
     * @dataProvider provideValidArchitecutres
     */
    public function testValidSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = (new PkgstatsRequest('3'))
            ->setSystemArchitecture(new SystemArchitecture($cpuArch))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture($osArch));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context
            ->expects($this->never())
            ->method('addViolation');

        $request->validateSystemArchitectures($context);
    }

    /**
     * @dataProvider provideInvalidArchitectures
     */
    public function testInvalidOperatingSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = (new PkgstatsRequest('3'))
            ->setSystemArchitecture(new SystemArchitecture($cpuArch))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture($osArch));

        $context = $this->createMock(ExecutionContextInterface::class);
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context
            ->expects($this->once())
            ->method('buildViolation')
            ->willReturn($violationBuilder);
        $violationBuilder
            ->expects($this->once())
            ->method('atPath')
            ->willReturn($violationBuilder);
        $violationBuilder
            ->expects($this->once())
            ->method('addViolation')
            ->willReturn($violationBuilder);

        $request->validateOperatingSystemArchitectures($context);
    }

    /**
     * @dataProvider provideInvalidArchitectures
     */
    public function testInvalidSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = (new PkgstatsRequest('3'))
            ->setSystemArchitecture(new SystemArchitecture($cpuArch))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture($osArch));

        $context = $this->createMock(ExecutionContextInterface::class);
        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $context
            ->expects($this->once())
            ->method('buildViolation')
            ->willReturn($violationBuilder);
        $violationBuilder
            ->expects($this->once())
            ->method('atPath')
            ->willReturn($violationBuilder);
        $violationBuilder
            ->expects($this->once())
            ->method('addViolation')
            ->willReturn($violationBuilder);

        $request->validateSystemArchitectures($context);
    }

    public function provideValidArchitecutres(): array
    {
        $result = [];
        $entries = [
            // cpuArch -> arch
            ['x86_64', ['x86_64', 'i686']],
            ['x86_64_v2', ['x86_64', 'i686']],
            ['x86_64_v3', ['x86_64', 'i686']],
            ['x86_64_v4', ['x86_64', 'i686']],
            ['i686', ['i686']],
            ['aarch64', ['aarch64', 'armv7h', 'armv6h', 'arm']],
            ['armv5', ['arm']],
            ['armv6', ['armv6h', 'arm']],
            ['armv7', ['armv7h', 'armv6h', 'arm']],
            ['riscv64', ['riscv64']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $arch) {
                $result[] = [$entry[0], $arch];
            }
        }

        return $result;
    }

    public function provideInvalidArchitectures(): array
    {
        $result = [];
        $entries = [
            // cpuArch -> arch
            ['', ['']],
            ['ppc', ['ppc']],
            ['i486', ['i486']],
            ['aarch64', ['x86_64']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $arch) {
                $result[] = [$entry[0], $arch];
            }
        }

        return $result;
    }
}
