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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mirror::class);
    }

    public function getCountByUrlAndRange(string $url, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('mirror')
            ->where('mirror.url = :url')
            ->setParameter('url', $url);

        if ($startMonth == $endMonth) {
            $queryBuilder
                ->select('mirror.count')
                ->andWhere('mirror.month = :month')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(mirror.count)')
                ->andWhere('mirror.month >= :startMonth')
                ->andWhere('mirror.month <= :endMonth')
                ->groupBy('mirror.url')
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
            fn($carry, $item) => $carry + $item['count'],
            0
        );
    }

    public function getMonthlySumCountByRange(int $startMonth, int $endMonth): array
    {
        $lifetime = Month::create(1)->getTimestamp() - time();

        $sumMonthlyCount = $this->createQueryBuilder('mirror')
            ->select('SUM(mirror.count) AS count')
            ->addSelect('mirror.month')
            ->groupBy('mirror.month')
            ->getQuery()
            ->enableResultCache($lifetime)
            ->getScalarResult();

        return array_filter(
            $sumMonthlyCount,
            function ($entry) use ($startMonth, $endMonth) {
                assert(is_array($entry));
                return $entry['month'] >= $startMonth && $entry['month'] <= $endMonth;
            }
        );
    }

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
        if ($startMonth == $endMonth) {
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
        $mirrors = $pagination->getQuery()->getScalarResult();

        $mirrors = array_map(function ($mirror) {
            assert(is_array($mirror));
            return [
                'url' => $mirror['mirror_url'],
                'count' => $mirror['mirror_count']
            ];
        }, $mirrors);

        return [
            'total' => $total,
            'mirrors' => $mirrors
        ];
    }
}
