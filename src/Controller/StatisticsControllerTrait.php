<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Connection;
use Psr\Cache\CacheItemPoolInterface;

trait StatisticsControllerTrait
{
    /** @var int */
    private $rangeMonths = 3;
    /** @var Connection */
    private $database;
    /** @var CacheItemPoolInterface */
    private $cache;

    /**
     * @param Connection $connection
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(Connection $connection, CacheItemPoolInterface $cache)
    {
        $this->database = $connection;
        $this->cache = $cache;
    }

    /**
     * @return int
     */
    private function getRangeTime(): int
    {
        return strtotime(date('1-m-Y', strtotime('now -' . $this->rangeMonths . ' months')));
    }

    /**
     * @return string
     */
    private function getRangeYearMonth(): string
    {
        return date('Ym', $this->getRangeTime());
    }
}
