<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class PaginationRequest
{
    public const int MAX_LIMIT = 10000;

    #[Assert\Range(min:0, max:100000)]
    private readonly int $offset;

    #[Assert\Range(min:1, max:PaginationRequest::MAX_LIMIT)]
    private readonly int $limit;

    public function __construct(int $offset, int $limit)
    {
        $this->offset = $offset;
        $this->limit = $limit === 0 ? self::MAX_LIMIT : $limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
