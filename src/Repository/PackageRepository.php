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
     * @param int $endMonth
     * @return int
     */
    public function getCountByNameAndRange(string $name, int $startMonth, int $endMonth): int
    {
        try {
            return $this->createQueryBuilder('package')
                ->select('SUM(package.count)')
                ->where('package.name = :name')
                ->andWhere('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.name')
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
     * @param string $name
     * @param int $startMonth
     * @param int $endMonth
     * @param int $offset
     * @param int $limit
     * @return array
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
        try {
            return $this->createQueryBuilder('package')
                ->select('SUM(package.count) AS count')
                ->where('package.month >= :startMonth')
                ->andWhere('package.month <= :endMonth')
                ->groupBy('package.name')
                ->orderBy('count', 'DESC')
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
            ->select('package.name')
            ->addSelect('SUM(package.count) AS count')
            ->where('package.month >= :startMonth')
            ->andWhere('package.month <= :endMonth')
            ->groupBy('package.name')
            ->orderBy('count', 'desc')
            ->setParameter('startMonth', $startMonth)
            ->setParameter('endMonth', $endMonth)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        if (!empty($query)) {
            $queryBuilder
                ->andWhere('package.name LIKE :query')
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

    /**
     * @param int $startMonth
     * @param int $endMonth
     * @return array
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
            ->getScalarResult();
    }

    /**
     * @param string $name
     * @return int|null
     */
    public function getFirstMonthByName(string $name): ?int
    {
        return $this->createQueryBuilder('package')
            ->select('MIN(package.month) AS month')
            ->where('package.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param string $name
     * @return int|null
     */
    public function getLatestMonthByName(string $name): ?int
    {
        return $this->createQueryBuilder('package')
            ->select('MAX(package.month) AS month')
            ->where('package.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int
     */
    public function getFirstMonth(): ?int
    {
        return $this->createQueryBuilder('package')
            ->select('MIN(package.month) AS month')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int
     */
    public function getLatestMonth(): ?int
    {
        return $this->createQueryBuilder('package')
            ->select('MAX(package.month) AS month')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
