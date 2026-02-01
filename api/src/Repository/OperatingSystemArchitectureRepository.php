<?php

namespace App\Repository;

use App\Entity\Month;
use App\Entity\OperatingSystemArchitecture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperatingSystemArchitecture>
 */
class OperatingSystemArchitectureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperatingSystemArchitecture::class);
    }

    public function getCountByNameAndRange(string $name, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('operatingSystemArchitecture')
            ->where('operatingSystemArchitecture.name = :name')
            ->setParameter('name', $name);

        if ($startMonth === $endMonth) {
            $queryBuilder
                ->select('operatingSystemArchitecture.count')
                ->andWhere('operatingSystemArchitecture.month = :month')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(operatingSystemArchitecture.count)')
                ->andWhere('operatingSystemArchitecture.month >= :startMonth')
                ->andWhere('operatingSystemArchitecture.month <= :endMonth')
                ->groupBy('operatingSystemArchitecture.name')
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
     * @return array{
     *     'total': int,
     *     'operatingSystemArchitectures': list<array{'name': string, 'month': int, 'count': int}>
     * }
     */
    public function findMonthlyByNameAndRange(
        string $name,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('operatingSystemArchitecture')
            ->where('operatingSystemArchitecture.name = :name')
            ->andWhere('operatingSystemArchitecture.month >= :startMonth')
            ->andWhere('operatingSystemArchitecture.month <= :endMonth')
            ->orderBy('operatingSystemArchitecture.month', 'asc')
            ->setParameter('name', $name)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'name': string, 'month': int, 'count': int}> $operatingSystemArchitectures */
        $operatingSystemArchitectures = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'operatingSystemArchitectures' => $operatingSystemArchitectures
        ];
    }

    public function getSumCountByRange(int $startMonth, int $endMonth): int
    {
        return array_reduce(
            $this->getMonthlySumCountByRange($startMonth, $endMonth),
            fn($carry, $item): float|int => $carry + $item['count'],
            0
        );
    }

    /**
     * @return array<array{'month': int, 'count': int}>
     */
    public function getMonthlySumCountByRange(int $startMonth, int $endMonth): array
    {
        $lifetime = Month::create(1)->getTimestamp() - time();

        /** @var list<array{'month': int, 'count': int}> $sumMonthlyCount */
        $sumMonthlyCount = $this->createQueryBuilder('operatingSystemArchitecture')
            ->select('SUM(operatingSystemArchitecture.count) AS count')
            ->addSelect('operatingSystemArchitecture.month')
            ->groupBy('operatingSystemArchitecture.month')
            ->getQuery()
            ->enableResultCache($lifetime)
            ->getScalarResult();

        return array_filter(
            $sumMonthlyCount,
            fn(array $entry): bool => $entry['month'] >= $startMonth && $entry['month'] <= $endMonth
        );
    }

    /**
     * @return array{'total': int, 'operatingSystemArchitectures': list<array{'name': string, 'count': int}>}
     */
    public function findOperatingSystemArchitecturesCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('operatingSystemArchitecture')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($startMonth === $endMonth) {
            $queryBuilder
                ->where('operatingSystemArchitecture.month = :month')
                ->orderBy('operatingSystemArchitecture.count', 'desc')
                ->addOrderBy('operatingSystemArchitecture.name', 'asc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('operatingSystemArchitecture.name AS operatingSystemArchitecture_name')
                ->addSelect('SUM(operatingSystemArchitecture.count) AS operatingSystemArchitecture_count')
                ->where('operatingSystemArchitecture.month >= :startMonth')
                ->andWhere('operatingSystemArchitecture.month <= :endMonth')
                ->groupBy('operatingSystemArchitecture.name')
                ->orderBy('operatingSystemArchitecture_count', 'desc')
                ->addOrderBy('operatingSystemArchitecture.name', 'asc')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }
        if (!empty($query)) {
            $queryBuilder
                ->andWhere('operatingSystemArchitecture.name LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'operatingSystemArchitecture_name': string, 'operatingSystemArchitecture_count': int}> $operatingSystemArchitectures */
        $operatingSystemArchitectures = $pagination->getQuery()->getScalarResult();

        $operatingSystemArchitectures = array_map(fn(array $arch): array => [
            'name' => $arch['operatingSystemArchitecture_name'],
            'count' => $arch['operatingSystemArchitecture_count']
        ], $operatingSystemArchitectures);

        return [
            'total' => $total,
            'operatingSystemArchitectures' => $operatingSystemArchitectures
        ];
    }
}
