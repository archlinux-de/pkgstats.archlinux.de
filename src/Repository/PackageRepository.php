<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;

class PackageRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    /**
     * @param string $name
     * @param int $startMonth
     * @param int $endMonth
     * @return int
     */
    public function getCountByNameAndRange(string $name, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('package')
            ->where('package.name = :name')
            ->setParameter('name', $name);

        if ($startMonth == $endMonth) {
            $queryBuilder
                ->select('package.count')
                ->andWhere('package.month = :month')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(package.count)')
                ->andWhere('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.name')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }

        try {
            return $queryBuilder
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
     * @param int $offset
     * @param int $limit
     * @return array<mixed>
     */
    public function findMonthlyByNameAndRange(
        string $name,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('package')
            ->where('package.name = :name')
            ->andWhere('package.month >= :startMonth')
            ->andWhere('package.month <= :endMonth')
            ->orderBy('package.month', 'asc')
            ->setParameter('name', $name)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        $packages = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'packages' => $packages
        ];
    }

    /**
     * @param int $startMonth
     * @param int $endMonth
     * @return int
     */
    public function getMaximumCountByRange(int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('package');

        if ($startMonth == $endMonth) {
            $queryBuilder
                ->select('package.count')
                ->where('package.month = :month')
                ->orderBy('package.count', 'DESC')
                ->setMaxResults(1)
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(package.count) AS count')
                ->where('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.name')
                ->orderBy('count', 'DESC')
                ->setMaxResults(1)
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }

        try {
            return $queryBuilder
                ->getQuery()
                ->enableResultCache(60 * 60 * 24 * 30)
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
     * @return array<mixed>
     */
    public function findPackagesCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('package')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($startMonth == $endMonth) {
            $queryBuilder
                ->where('package.month = :month')
                ->orderBy('package.count', 'desc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('package.name AS package_name')
                ->addSelect('SUM(package.count) AS package_count')
                ->where('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.name')
                ->orderBy('package_count', 'desc')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }
        if (!empty($query)) {//@TODO: testen, ob das greift
            $queryBuilder
                ->andWhere('package.name LIKE :query')
                ->setParameter('query', $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        $packages = $pagination->getQuery()->getScalarResult();

        $packages = array_map(function ($package) {
            return [
                'name' => $package['package_name'],
                'count' => $package['package_count']
            ];
        }, $packages);

        return [
            'total' => $total,
            'packages' => $packages
        ];
    }

    /**
     * @param int $startMonth
     * @param int $endMonth
     * @return array<mixed>
     */
    public function getMonthlyMaximumCountByRange(int $startMonth, int $endMonth): array
    {
        return $this->createQueryBuilder('package')
            ->select('MAX(package.count) AS count')
            ->addSelect('package.month')
            ->where('package.month >= :startMonth')
            ->andWhere('package.month <= :endMonth')
            ->groupBy('package.month')
            ->orderBy('package.month', 'asc')
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->getQuery()
            ->enableResultCache(60 * 60 * 24 * 30)
            ->getScalarResult();
    }
}
