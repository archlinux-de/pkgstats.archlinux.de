<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class UserRepository extends EntityRepository
{
    /**
     * @param int $startTime
     * @return int
     */
    public function getCountSince(int $startTime): int
    {
        return $this->createQueryBuilder('user')
            ->select('COUNT(user)')
            ->where('user.time >= :startTime')
            ->setParameter('startTime', $startTime)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param string $ip
     * @param int $startTime
     * @return int
     */
    public function getSubmissionCountSince(string $ip, int $startTime): int
    {
        try {
            return $this->createQueryBuilder('user')
                ->select('COUNT(user)')
                ->where('user.time >= :startTime')
                ->andWhere('user.ip = :ip')
                ->groupBy('user.ip')
                ->setParameter('startTime', $startTime)
                ->setParameter('ip', $ip)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        }
    }
}
