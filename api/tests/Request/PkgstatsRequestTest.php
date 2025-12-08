<?php

namespace App\Tests\Request;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Request\PkgstatsRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PkgstatsRequestTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $package = $this->createStub(Package::class);

        $request = new PkgstatsRequest('1.0')
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

    #[DataProvider('provideValidArchitectures')]
    public function testValidOperatingSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = new PkgstatsRequest('3')
            ->setSystemArchitecture(new SystemArchitecture($cpuArch))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture($osArch));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context
            ->expects($this->never())
            ->method('addViolation');

        $request->validateOperatingSystemArchitectures($context);
    }

    #[DataProvider('provideValidArchitectures')]
    public function testValidSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = new PkgstatsRequest('3')
            ->setSystemArchitecture(new SystemArchitecture($cpuArch))
            ->setOperatingSystemArchitecture(new OperatingSystemArchitecture($osArch));

        $context = $this->createMock(ExecutionContextInterface::class);
        $context
            ->expects($this->never())
            ->method('addViolation');

        $request->validateSystemArchitectures($context);
    }

    #[DataProvider('provideInvalidArchitectures')]
    public function testInvalidOperatingSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = new PkgstatsRequest('3')
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
            ->method('addViolation');

        $request->validateOperatingSystemArchitectures($context);
    }

    #[DataProvider('provideInvalidArchitectures')]
    public function testInvalidSystemArchitectures(string $cpuArch, string $osArch): void
    {
        $request = new PkgstatsRequest('3')
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
            ->method('addViolation');

        $request->validateSystemArchitectures($context);
    }

    /**
     * @return list<array<string>>
     */
    public static function provideValidArchitectures(): array
    {
        $result = [];
        $entries = [
            // cpuArch -> arch
            ['x86_64', ['x86_64', 'i686', 'i586']],
            ['x86_64_v2', ['x86_64', 'i686', 'i586']],
            ['x86_64_v3', ['x86_64', 'i686', 'i586']],
            ['x86_64_v4', ['x86_64', 'i686', 'i586']],
            ['i586', ['i586']],
            ['i686', ['i586', 'i686']],
            ['aarch64', ['aarch64', 'armv7l', 'armv7h', 'armv6l', 'armv6h', 'arm', 'armv5tel']],
            ['armv5', ['arm', 'armv5tel']],
            ['armv6', ['armv6l', 'armv6h', 'arm', 'armv5tel']],
            ['armv7', ['armv7l', 'armv7h', 'armv6l', 'armv6h', 'arm', 'armv5tel']],
            ['riscv64', ['riscv64']],
            ['loong64', ['loongarch64']]
        ];

        foreach ($entries as $entry) {
            foreach ($entry[1] as $arch) {
                $result[] = [$entry[0], $arch];
            }
        }

        return $result;
    }

    /**
     * @return list<array<string>>
     */
    public static function provideInvalidArchitectures(): array
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
