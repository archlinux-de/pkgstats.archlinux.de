<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Symfony\Bridge\Doctrine\RegistryInterface;

class PackageRepository extends ServiceEntityRepository
{
    /**
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Package::class);
    }

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
