<?php

namespace App\Tests\Validator;

use App\Entity\Package;
use App\Validator\ContainsPackages;
use App\Validator\ContainsPackagesValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ContainsPackagesValidator>
 */
class ContainsPackagesValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ContainsPackagesValidator
    {
        return new ContainsPackagesValidator(['pkg1', 'pkg2', 'pkg3', 'pkg4'], 0.5);
    }

    public function testValidateWithWrongConstraintThrowsException(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->validator->validate([], new class extends Constraint {
        });
    }

    /**
     * @param class-string<\Throwable> $expectedException
     */
    #[DataProvider('provideInvalidValues')]
    public function testInvalidValueThrowsException(mixed $value, string $expectedException): void
    {
        $this->expectException($expectedException);
        $this->validator->validate($value, new ContainsPackages());
    }

    /**
     * @return iterable<array{mixed, class-string<\Throwable>}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'string' => ['foo', UnexpectedValueException::class];
        yield 'integer' => [123, UnexpectedValueException::class];
        yield 'object' => [new \stdClass(), UnexpectedValueException::class];
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new ContainsPackages());
        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new ContainsPackages());
        $this->assertNoViolation();
    }

    public function testValidateWithValidPackages(): void
    {
        $packages = [
            $this->createPackage('pkg1'),
            $this->createPackage('pkg2'),
            $this->createPackage('pkg3'),
            $this->createPackage('pkg4'),
        ];

        $this->validator->validate($packages, new ContainsPackages());

        $this->assertNoViolation();
    }

    public function testValidateWithTooManyMissingPackages(): void
    {
        $packages = [
            $this->createPackage('pkg1'),
        ];

        $constraint = new ContainsPackages();
        $this->validator->validate($packages, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised();
    }

    public function testValidateWithAcceptableMissingPackages(): void
    {
        $packages = [
            $this->createPackage('pkg1'),
            $this->createPackage('pkg2'),
            $this->createPackage('pkg3'),
        ];

        $this->validator->validate($packages, new ContainsPackages());

        $this->assertNoViolation();
    }

    public function testValidateWithEmptyExpectedPackages(): void
    {
        $validator = new ContainsPackagesValidator([], 0.5);
        $validator->initialize($this->context);
        $validator->validate([$this->createPackage('pkg1')], new ContainsPackages());

        $this->assertNoViolation();
    }

    private function createPackage(string $name): Package
    {
        $package = new Package();
        $package->setName($name);

        return $package;
    }
}
