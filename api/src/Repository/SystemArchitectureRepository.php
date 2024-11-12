<?php

namespace App\Repository;

use App\Entity\Month;
use App\Entity\SystemArchitecture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemArchitecture>
 */
class SystemArchitectureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemArchitecture::class);
    }

    public function getCountByNameAndRange(string $name, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('systemArchitecture')
            ->where('systemArchitecture.name = :name')
            ->setParameter('name', $name);

        if ($startMonth == $endMonth) {
            $queryBuilder
                ->select('systemArchitecture.count')
                ->andWhere('systemArchitecture.month = :month')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(systemArchitecture.count)')
                ->andWhere('systemArchitecture.month >= :startMonth')
                ->andWhere('systemArchitecture.month <= :endMonth')
                ->groupBy('systemArchitecture.name')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }

        try {
            return (int)$queryBuilder
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException $e) {
            return 0;
        }
    }

    /**
     * @return array{'total': int, 'systemArchitectures': list<array{'name': string, 'month': int, 'count': int}>}
     */
    public function findMonthlyByNameAndRange(
        string $name,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('systemArchitecture')
            ->where('systemArchitecture.name = :name')
            ->andWhere('systemArchitecture.month >= :startMonth')
            ->andWhere('systemArchitecture.month <= :endMonth')
            ->orderBy('systemArchitecture.month', 'asc')
            ->setParameter('name', $name)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'name': string, 'month': int, 'count': int}> $systemArchitectures */
        $systemArchitectures = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'systemArchitectures' => $systemArchitectures
        ];
    }

    public function getSumCountByRange(int $startMonth, int $endMonth): int
    {
        return array_reduce(
            $this->getMonthlySumCountByRange($startMonth, $endMonth),
            fn($carry, $item) => $carry + $item['count'],
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
        $sumMonthlyCount = $this->createQueryBuilder('systemArchitecture')
            ->select('SUM(systemArchitecture.count) AS count')
            ->addSelect('systemArchitecture.month')
            ->groupBy('systemArchitecture.month')
            ->getQuery()
            ->enableResultCache($lifetime)
            ->getScalarResult();

        return array_filter(
            $sumMonthlyCount,
            function ($entry) use ($startMonth, $endMonth) {
                return $entry['month'] >= $startMonth && $entry['month'] <= $endMonth;
            }
        );
    }

    /**
     * @return array{'total': int, 'systemArchitectures': list<array{'name': string, 'count': int}>}
     */
    public function findSystemArchitecturesCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('systemArchitecture')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($startMonth == $endMonth) {
            $queryBuilder
                ->where('systemArchitecture.month = :month')
                ->orderBy('systemArchitecture.count', 'desc')
                ->addOrderBy('systemArchitecture.name', 'asc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('systemArchitecture.name AS systemArchitecture_name')
                ->addSelect('SUM(systemArchitecture.count) AS systemArchitecture_count')
                ->where('systemArchitecture.month >= :startMonth')
                ->andWhere('systemArchitecture.month <= :endMonth')
                ->groupBy('systemArchitecture.name')
                ->orderBy('systemArchitecture_count', 'desc')
                ->addOrderBy('systemArchitecture.name', 'asc')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }
        if (!empty($query)) {//@TODO: testen, ob das greift
            $queryBuilder
                ->andWhere('systemArchitecture.name LIKE :query')
                // @TODO use more efficient index
                ->setParameter('query', '%' . $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'systemArchitecture_name': string, 'systemArchitecture_count': int}> $systemArchitectures */
        $systemArchitectures = $pagination->getQuery()->getScalarResult();

        $systemArchitectures = array_map(function ($systemArchitecture) {
            return [
                'name' => $systemArchitecture['systemArchitecture_name'],
                'count' => $systemArchitecture['systemArchitecture_count']
            ];
        }, $systemArchitectures);

        return [
            'total' => $total,
            'systemArchitectures' => $systemArchitectures
        ];
    }
}
