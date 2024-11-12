<?php

namespace App\Repository;

use App\Entity\Country;
use App\Entity\Month;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    public function getCountByCodeAndRange(string $code, int $startMonth, int $endMonth): int
    {
        $queryBuilder = $this->createQueryBuilder('country')
            ->where('country.code = :code')
            ->setParameter('code', $code);

        if ($startMonth == $endMonth) {
            $queryBuilder
                ->select('country.count')
                ->andWhere('country.month = :month')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('SUM(country.count)')
                ->andWhere('country.month >= :startMonth')
                ->andWhere('country.month <= :endMonth')
                ->groupBy('country.code')
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
     * @return array{'total': int, 'countries': list<array{'code': string, 'month': int, 'count': int}>}
     */
    public function findMonthlyByCodeAndRange(
        string $code,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('country')
            ->where('country.code = :code')
            ->andWhere('country.month >= :startMonth')
            ->andWhere('country.month <= :endMonth')
            ->orderBy('country.month', 'asc')
            ->setParameter('code', $code)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'code': string, 'month': int, 'count': int}> $countries */
        $countries = $pagination->getQuery()->getArrayResult();

        return [
            'total' => $total,
            'countries' => $countries
        ];
    }

    public function getSumCountByRange(int $startMonth, int $endMonth): int
    {
        return array_reduce(
            $this->getMonthlySumCountByRange($startMonth, $endMonth),
            fn(int $carry, array $item) => $carry + $item['count'],
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
        $sumMonthlyCount = $this->createQueryBuilder('country')
            ->select('SUM(country.count) AS count')
            ->addSelect('country.month')
            ->groupBy('country.month')
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
     * @return array{'total': int, 'countries': list<array{'code': string, 'count': int}>}
     */
    public function findCountriesCountByRange(
        string $query,
        int $startMonth,
        int $endMonth,
        int $offset,
        int $limit
    ): array {
        $queryBuilder = $this->createQueryBuilder('country')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if ($startMonth == $endMonth) {
            $queryBuilder
                ->where('country.month = :month')
                ->orderBy('country.count', 'desc')
                ->addOrderBy('country.code', 'asc')
                ->setParameter('month', $startMonth);
        } else {
            $queryBuilder
                ->select('country.code AS country_code')
                ->addSelect('SUM(country.count) AS country_count')
                ->where('country.month >= :startMonth')
                ->andWhere('country.month <= :endMonth')
                ->groupBy('country.code')
                ->orderBy('country_count', 'desc')
                ->addOrderBy('country.code', 'asc')
                ->setParameter('startMonth', $startMonth)
                ->setParameter('endMonth', $endMonth);
        }
        if (!empty($query)) {//@TODO: testen, ob das greift
            $queryBuilder
                ->andWhere('country.code LIKE :query')
                // @TODO use more efficient index
                ->setParameter('query', '%' . $query . '%');
        }

        $pagination = new Paginator($queryBuilder, false);
        $total = $pagination->count();
        /** @var list<array{'country_code': string, 'country_count': int}> $countries */
        $countries = $pagination->getQuery()->getScalarResult();

        $countries = array_map(function ($country) {
            return [
                'code' => $country['country_code'],
                'count' => $country['country_count']
            ];
        }, $countries);

        return [
            'total' => $total,
            'countries' => $countries
        ];
    }
}
