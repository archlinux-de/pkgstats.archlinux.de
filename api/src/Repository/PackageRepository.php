<?php

namespace App\Repository;

use App\Entity\Month;
use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Package>
 */
class PackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    public function getCountByNameAndRange(string $name, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('package')
            ->where('package.name = :name')
            ->setParameter('name', $name);

        if ($startMonth === $endMonth) {
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
            return (int)$queryBuilder
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException) {
            return 0;
        }
    }

    /**
     * @return array{'total': int, 'packages': list<array{'name': string, 'month': int, 'count': int}>}
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
        /** @var list<array{'name': string, 'month': int, 'count': int}> $packages */
        $packages = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'packages' => $packages
        ];
    }

    public function getMaximumCountByRange(int $startMonth, int $endMonth): int
    {
        return array_reduce(
            $this->getMonthlyMaximumCountByRange($startMonth, $endMonth),
            fn($carry, $item): float|int => $carry + $item['count'],
            0
        );
    }

    /**
     * @return array<array{'month': int, 'count': int}>
     */
    public function getMonthlyMaximumCountByRange(int $startMonth, int $endMonth): array
    {
        $lifetime = Month::create(1)->getTimestamp() - time();

        /** @var list<array{'month': int, 'count': int}> $maxMonthlyCount */
        $maxMonthlyCount = $this->createQueryBuilder('package')
            ->select('MAX(package.count) AS count')
            ->addSelect('package.month')
            ->groupBy('package.month')
            ->orderBy('package.month', 'asc')
            ->getQuery()
            ->enableResultCache($lifetime)
            ->getScalarResult();

        return array_filter(
            $maxMonthlyCount,
            fn($entry): bool => $entry['month'] >= $startMonth && $entry['month'] <= $endMonth
        );
    }

    /**
     * @return array{'total': int, 'packages': list<array{'name': string, 'count': int}>}
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
        if ($startMonth === $endMonth) {
            $queryBuilder
                ->where('package.month = :month')
                ->orderBy('package.count', 'desc')
                ->addOrderBy('package.name', 'asc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('package.name AS package_name')
                ->addSelect('SUM(package.count) AS package_count')
                ->where('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.name')
                ->orderBy('package_count', 'desc')
                ->addOrderBy('package.name', 'asc')
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
        /** @var list<array{'package_name': string, 'package_count': int}> $packages */
        $packages = $pagination->getQuery()->getScalarResult();

        $packages = array_map(fn($package): array => [
            'name' => $package['package_name'],
            'count' => $package['package_count']
        ], $packages);

        return [
            'total' => $total,
            'packages' => $packages
        ];
    }
}
