<?php

namespace App\Request;

use App\Entity\Package;
use Symfony\Component\Validator\Constraints as Assert;

class PackageQueryRequest
{
    #[Assert\Length(max:191)]
    #[Assert\Regex('/' . Package::NAME_REGEXP . '/')]
    private string $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    public function getQuery(): string
    {
        return $this->query;
    }
}
