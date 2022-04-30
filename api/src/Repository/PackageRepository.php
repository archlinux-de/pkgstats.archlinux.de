<?php

namespace App\Repository;

use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

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

    public function getMaximumCountByRange(int $startMonth, int $endMonth): int
    {
        return array_reduce(
            $this->getMonthlyMaximumCountByRange($startMonth, $endMonth),
            fn($carry, $item) => $carry + $item['count'],
            0
        );
    }

    public function getMonthlyMaximumCountByRange(int $startMonth, int $endMonth): array
    {
        $nextMonth = new \DateTime((new \DateTime('first day of this month +1 month'))->format('Y-m-01'));
        $lifetime = $nextMonth->getTimestamp() - time();

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
            function ($entry) use ($startMonth, $endMonth) {
                assert(is_array($entry));
                return $entry['month'] >= $startMonth && $entry['month'] <= $endMonth;
            }
        );
    }

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
            assert(is_array($package));
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
}
