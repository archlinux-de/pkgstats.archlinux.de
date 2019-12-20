<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class PackageQueryRequest
{
    /**
     * @var string
     * @Assert\Length(max=191)
     * @Assert\Regex("/^[a-zA-Z0-9][a-zA-Z0-9@:\.+_-]*$/")
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
