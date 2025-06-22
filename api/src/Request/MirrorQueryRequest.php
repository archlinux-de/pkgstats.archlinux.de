<?php

namespace App\Request;

use App\Entity\Mirror;
use Symfony\Component\Validator\Constraints as Assert;

class MirrorQueryRequest
{
    #[Assert\Length(max:191)]
    #[Assert\Regex('/' . Mirror::URL_REGEXP . '/')]
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
