<?php

namespace App\Request;

use App\Entity\OperatingSystemId;
use Symfony\Component\Validator\Constraints as Assert;

class OperatingSystemIdQueryRequest
{
    #[Assert\Length(max: 50)]
    #[Assert\Regex('/' . OperatingSystemId::ID_REGEXP_OPTIONAL . '/')]
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
