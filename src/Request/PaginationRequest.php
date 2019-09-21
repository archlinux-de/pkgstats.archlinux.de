<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class PaginationRequest
{
    /** @var int */
    public const MAX_LIMIT = 10000;

    /**
     * @var int
     * @Assert\Range(min=0, max=100000)
     */
    private $offset;

    /**
     * @var int
     * @Assert\Range(min=1, max=PaginationRequest::MAX_LIMIT)
     */
    private $limit;

    /**
     * @param int $offset
     * @param int $limit
     */
    public function __construct(int $offset, int $limit)
    {
        $this->offset = $offset;
        $this->limit = $limit == 0 ? self::MAX_LIMIT : $limit;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
