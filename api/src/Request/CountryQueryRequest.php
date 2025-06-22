<?php

namespace App\Request;

use App\Entity\Country;
use Symfony\Component\Validator\Constraints as Assert;

class CountryQueryRequest
{
    #[Assert\Length(max:2)]
    #[Assert\Regex('/' . Country::CODE_REGEXP . '/')]
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
