<?php

namespace App\Request;

use App\Entity\SystemArchitecture;
use Symfony\Component\Validator\Constraints as Assert;

class SystemArchitectureQueryRequest
{
    #[Assert\Length(max:191)]
    #[Assert\Regex('/' . SystemArchitecture::NAME_REGEXP . '/')]
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
