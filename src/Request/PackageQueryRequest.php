<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class PackageQueryRequest
{
    /**
     * @var string
     * @Assert\Length(max=255)
     * @Assert\Regex("/^[^-]+\S*$/")
     */
    private $query;

    /**
     * @param string $query
     */
    public function __construct(string $query)
    {
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }
}
