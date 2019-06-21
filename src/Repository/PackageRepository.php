<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * @param int $startMonth
     * @return int
     */
    public function getMaximumCountSince(int $startMonth): int
    {
        try {
            return $this->createQueryBuilder('package')
                ->select('SUM(package.count) AS c')
                ->where('package.month >= :month')
                ->groupBy('package.pkgname')
                ->orderBy('c', 'DESC')
                ->setMaxResults(1)
                ->setParameter('month', $startMonth)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * @param string $name
     * @param int $startMonth
     * @param int $endMonth
     * @return int
     */
    public function getCountByNameAndRange(string $name, int $startMonth, int $endMonth): int
    {
        try {
            return $this->createQueryBuilder('package')
                ->select('SUM(package.count)')
                ->where('package.pkgname = :name')
                ->andWhere('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.pkgname')
                ->setParameter('name', $name)
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * @param int $startMonth
     * @param int $endMonth
     * @return int
     */
    public function getMaximumCountByRange(int $startMonth, int $endMonth): int
    {
        try {
            return $this->createQueryBuilder('package')
                ->select('SUM(package.count) AS c')
                ->where('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.pkgname')
                ->orderBy('c', 'DESC')
                ->setMaxResults(1)
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * @param string $query
     * @param int $startMonth
     * @param int $endMonth
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function findPackagesCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('package')
            ->select('package.pkgname AS name')
            ->addSelect('SUM(package.count) AS count')
            ->where('package.month >= :startMonth')
            ->andWhere('package.month <= :endMonth')
            ->groupBy('package.pkgname')
            ->orderBy('count', 'desc')
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if (!empty($query)) {
            $queryBuilder
                ->andWhere('package.pkgname LIKE :query')
                ->setParameter('query', $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        $packages = $pagination->getQuery()->getScalarResult();

        return [
            'total' => $total,
            'packages' => $packages
        ];
    }
}
