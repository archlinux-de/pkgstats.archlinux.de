<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;

class PackageRepository extends EntityRepository
{
    /**
     * @param string $name
     * @param int $startMonth
     * @return int
     */
    public function getCountByNameSince(string $name, int $startMonth): int
    {
        try {
            return $this->createQueryBuilder('package')
                ->select('SUM(package.count)')
                ->where('package.month >= :month')
                ->andWhere('package.pkgname = :name')
                ->groupBy('package.pkgname')
                ->setParameter('month', $startMonth)
                ->setParameter('name', $name)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        }
    }
}
