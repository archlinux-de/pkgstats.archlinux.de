<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

class UserRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
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
