<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ContainsPackages extends Constraint
{
    public string $message = 'The package list is invalid.';
}
