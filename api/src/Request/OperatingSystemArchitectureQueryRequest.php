<?php

namespace App\Request;

use App\Entity\OperatingSystemArchitecture;
use Symfony\Component\Validator\Constraints as Assert;

class OperatingSystemArchitectureQueryRequest
{
    #[Assert\Length(max: 15)]
    #[Assert\Regex('/' . OperatingSystemArchitecture::NAME_REGEXP_OPTIONAL . '/')]
    private readonly string $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
