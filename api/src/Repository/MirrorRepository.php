<?php

namespace App\Repository;

use App\Entity\Mirror;
use App\Entity\Month;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mirror>
 */
class MirrorRepository extends ServiceEntityRepository
{
    public const int MIN_POPULARITY = 16;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mirror::class);
    }

    public function getCountByUrlAndRange(string $url, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('mirror')
            ->where('mirror.url = :url')
            ->setParameter('url', $url);

        if ($startMonth === $endMonth) {
            $queryBuilder
                ->select('mirror.count')
                ->andWhere('mirror.month = :month')
                ->setParameter('month', $startMonth)
                ->andWhere('mirror.count >= :minPopularity')
                ->setParameter('minPopularity', self::MIN_POPULARITY);
        } else {
            $queryBuilder
                ->select('SUM(mirror.count) AS mirror_count')
                ->andWhere('mirror.month >= :startMonth')
                ->andWhere('mirror.month <= :endMonth')
                ->groupBy('mirror.url')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth)
                ->having('mirror_count >= :minPopularity')
                ->setParameter('minPopularity', self::MIN_POPULARITY);
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
     * @return array{'total': int, 'mirrors': list<array{'url': string, 'month': int, 'count': int}>}
     */
    public function findMonthlyByUrlAndRange(
        string $url,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('mirror')
            ->where('mirror.url = :url')
            ->andWhere('mirror.month >= :startMonth')
            ->andWhere('mirror.month <= :endMonth')
            ->orderBy('mirror.month', 'asc')
            ->setParameter('url', $url)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'url': string, 'month': int, 'count': int}> $mirrors */
        $mirrors = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'mirrors' => $mirrors
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
        $sumMonthlyCount = $this->createQueryBuilder('mirror')
            ->select('SUM(mirror.count) AS count')
            ->addSelect('mirror.month')
            ->groupBy('mirror.month')
            ->getQuery()
            ->enableResultCache($lifetime)
            ->getScalarResult();

        return array_filter(
            $sumMonthlyCount,
            fn(array $entry): bool => $entry['month'] >= $startMonth && $entry['month'] <= $endMonth
        );
    }

    /**
     * @return array{'total': int, 'mirrors': list<array{'url': string, 'count': int}>}
     */
    public function findMirrorsCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('mirror')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($startMonth === $endMonth) {
            $queryBuilder
                ->where('mirror.month = :month')
                ->orderBy('mirror.count', 'desc')
                ->addOrderBy('mirror.url', 'asc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('mirror.url AS mirror_url')
                ->addSelect('SUM(mirror.count) AS mirror_count')
                ->where('mirror.month >= :startMonth')
                ->andWhere('mirror.month <= :endMonth')
                ->groupBy('mirror.url')
                ->orderBy('mirror_count', 'desc')
                ->addOrderBy('mirror.url', 'asc')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }
        if (!empty($query)) {//@TODO: testen, ob das greift
            $queryBuilder
                ->andWhere('mirror.url LIKE :query')
                // @TODO use more efficient index or domain based search
                ->setParameter('query', '%' . $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'mirror_url': string, 'mirror_count': int}> $mirrors */
        $mirrors = $pagination->getQuery()->getScalarResult();

        $mirrors = array_map(fn(array $mirror): array => [
            'url' => $mirror['mirror_url'],
            'count' => $mirror['mirror_count']
        ], $mirrors);

        return [
            'total' => $total,
            'mirrors' => $mirrors
        ];
    }
}
