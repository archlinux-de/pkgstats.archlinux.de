<?php

namespace App\Repository;

use App\Entity\Month;
use App\Entity\OperatingSystemId;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperatingSystemId>
 */
class OperatingSystemIdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperatingSystemId::class);
    }

    public function getCountByIdAndRange(string $id, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('operatingSystemId')
            ->where('operatingSystemId.id = :id')
            ->setParameter('id', $id);

        if ($startMonth === $endMonth) {
            $queryBuilder
                ->select('operatingSystemId.count')
                ->andWhere('operatingSystemId.month = :month')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(operatingSystemId.count)')
                ->andWhere('operatingSystemId.month >= :startMonth')
                ->andWhere('operatingSystemId.month <= :endMonth')
                ->groupBy('operatingSystemId.id')
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
     * @return array{'total': int, 'operatingSystemIds': list<array{'id': string, 'month': int, 'count': int}>}
     */
    public function findMonthlyByIdAndRange(
        string $id,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('operatingSystemId')
            ->where('operatingSystemId.id = :id')
            ->andWhere('operatingSystemId.month >= :startMonth')
            ->andWhere('operatingSystemId.month <= :endMonth')
            ->orderBy('operatingSystemId.month', 'asc')
            ->setParameter('id', $id)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'id': string, 'month': int, 'count': int}> $operatingSystemIds */
        $operatingSystemIds = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'operatingSystemIds' => $operatingSystemIds
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
        $sumMonthlyCount = $this->createQueryBuilder('operatingSystemId')
            ->select('SUM(operatingSystemId.count) AS count')
            ->addSelect('operatingSystemId.month')
            ->groupBy('operatingSystemId.month')
            ->getQuery()
            ->enableResultCache($lifetime)
            ->getScalarResult();

        return array_filter(
            $sumMonthlyCount,
            fn(array $entry): bool => $entry['month'] >= $startMonth && $entry['month'] <= $endMonth
        );
    }

    /**
     * @return array{'total': int, 'operatingSystemIds': list<array{'id': string, 'count': int}>}
     */
    public function findOperatingSystemIdsCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('operatingSystemId')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($startMonth === $endMonth) {
            $queryBuilder
                ->where('operatingSystemId.month = :month')
                ->orderBy('operatingSystemId.count', 'desc')
                ->addOrderBy('operatingSystemId.id', 'asc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('operatingSystemId.id AS operatingSystemId_id')
                ->addSelect('SUM(operatingSystemId.count) AS operatingSystemId_count')
                ->where('operatingSystemId.month >= :startMonth')
                ->andWhere('operatingSystemId.month <= :endMonth')
                ->groupBy('operatingSystemId.id')
                ->orderBy('operatingSystemId_count', 'desc')
                ->addOrderBy('operatingSystemId.id', 'asc')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }
        if (!empty($query)) {
            $queryBuilder
                ->andWhere('operatingSystemId.id LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'operatingSystemId_id': string, 'operatingSystemId_count': int}> $operatingSystemIds */
        $operatingSystemIds = $pagination->getQuery()->getScalarResult();

        $operatingSystemIds = array_map(fn(array $operatingSystemId): array => [
            'id' => $operatingSystemId['operatingSystemId_id'],
            'count' => $operatingSystemId['operatingSystemId_count']
        ], $operatingSystemIds);

        return [
            'total' => $total,
            'operatingSystemIds' => $operatingSystemIds
        ];
    }
}
