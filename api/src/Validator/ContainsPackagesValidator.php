<?php

namespace App\Validator;

use App\Entity\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ContainsPackagesValidator extends ConstraintValidator
{
    public function __construct(
        /** @var string[] */ private readonly array $expectedPackages,
        private readonly float $maxMissing,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ContainsPackages) {
            throw new UnexpectedTypeException($constraint, ContainsPackages::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        /** @var Package[] $value */
        $packages = array_map(fn(Package $package): string => $package->getName(), $value);

        $missingPackages = array_diff($this->expectedPackages, $packages);

        if (empty($this->expectedPackages)) {
            return;
        }

        if (count($missingPackages) / count($this->expectedPackages) > $this->maxMissing) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
