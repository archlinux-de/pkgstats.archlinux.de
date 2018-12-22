<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use App\Request\Datatables\Request as DatatablesRequest;
use App\Response\Datatables\Response as DatatablesResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Cache\CacheItemPoolInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PackageStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var PackageRepository */
    private $packageRepository;

    /** @var UserRepository */
    private $userRepository;

    /**
     * @param int $rangeMonths
     * @param CacheItemPoolInterface $cache
     * @param PackageRepository $packageRepository
     * @param UserRepository $userRepository
     */
    public function __construct(
        int $rangeMonths,
        CacheItemPoolInterface $cache,
        PackageRepository $packageRepository,
        UserRepository $userRepository
    ) {
        $this->rangeMonths = $rangeMonths;
        $this->cache = $cache;
        $this->packageRepository = $packageRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/package", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     */
    public function packageAction(): Response
    {
        return $this->render('package.html.twig');
    }

    /**
     * @Route("/package.json", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function packageJsonAction(): Response
    {
        $cachedPackages = $this->cache->getItem('pkgstats.json');
        if ($cachedPackages->isHit()) {
            $packages = $cachedPackages->get();
        } else {
            $queryBuilder = $this->packageRepository
                ->createQueryBuilder('package')
                ->select('package.pkgname AS pkgname')
                ->addSelect('SUM(package.count) AS count')
                ->where('package.month >= :month')
                ->setParameter('month', $this->getRangeYearMonth())
                ->groupBy('package.pkgname');
            $packages = $queryBuilder->getQuery()->getScalarResult();

            array_walk($packages, function (&$item) {
                $item['count'] = (int)$item['count'];
            });

            $cachedPackages->expiresAt(new \DateTime('24 hour'));
            $cachedPackages->set($packages);
            $this->cache->save($cachedPackages);
        }

        return $this->json($packages);
    }

    /**
     * @return string
     */
    private function getRangeYearMonth(): string
    {
        return date('Ym', $this->getRangeTime());
    }

    /**
     * @return int
     */
    private function getRangeTime(): int
    {
        return strtotime(date('1-m-Y', strtotime('now -' . $this->rangeMonths . ' months')));
    }

    /**
     * @Route("/package/datatables", methods={"GET"})
     * @param DatatablesRequest $request
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function datatablesAction(DatatablesRequest $request): Response
    {
        $response = $this->createDatatablesResponse($request);

        return $this->json($response);
    }

    /**
     * @param DatatablesRequest $request
     * @return DatatablesResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function createDatatablesResponse(DatatablesRequest $request): DatatablesResponse
    {
        $cachedResponse = $this->cache->getItem($request->getId());
        if ($cachedResponse->isHit()) {
            /** @var DatatablesResponse $response */
            $response = $cachedResponse->get();
        } else {
            $response = $this->queryDatabase($request);
            $cachedResponse->expiresAt(new \DateTime('1 hour'));
            $cachedResponse->set($response);
            // Only store the first draw (initial state of the page)
            if ($request->getDraw() == 1) {
                $this->cache->save($cachedResponse);
            }
        }

        $pkgstatsCount = $this->calculatePkgstatsCount();

        $response->setRecordsTotal($pkgstatsCount);
        $response->setDraw($request->getDraw());
        return $response;
    }

    /**
     * @param DatatablesRequest $request
     * @return DatatablesResponse
     */
    private function queryDatabase(DatatablesRequest $request): DatatablesResponse
    {
        $compareableColumns = [
        ];
        $textSearchableColumns = [
            'pkgname' => 'pkgname'
        ];
        $searchableColumns = array_merge(
            $compareableColumns,
            $textSearchableColumns
        );
        $orderableColumns = array_merge(
            $compareableColumns,
            [
                'count' => 'count'
            ]
        );

        $queryBuilder = $this->packageRepository
            ->createQueryBuilder('package')
            ->select('package.pkgname AS pkgname')
            ->addSelect('SUM(package.count) AS count')
            ->where('package.month >= :month')
            ->setParameter('month', $this->getRangeYearMonth())
            ->groupBy('package.pkgname')
            ->setFirstResult($request->getStart())
            ->setMaxResults($request->getLength());

        foreach ($request->getOrders() as $order) {
            $orderColumnName = $order->getColumn()->getData();
            if (isset($orderableColumns[$orderColumnName])) {
                $queryBuilder->orderBy($orderableColumns[$orderColumnName], $order->getDir());
            }
        }

        if ($request->hasSearch() && !$request->getSearch()->isRegex()) {
            $queryBuilder->andWhere($this->createTextSearchQuery($textSearchableColumns));
            $queryBuilder->setParameter(':search', '%' . $request->getSearch()->getValue() . '%');
        }

        foreach ($request->getColumns() as $column) {
            if ($column->isSearchable()) {
                $columnName = $column->getData();
                if (isset($searchableColumns[$columnName])) {
                    if (!$column->getSearch()->isRegex() && $column->getSearch()->isValid()) {
                        $queryBuilder->andWhere(
                            $searchableColumns[$columnName] . ' LIKE :columnSearch' . $column->getId()
                        );
                        $searchValue = $column->getSearch()->getValue();
                        if (!isset($compareableColumns[$columnName])) {
                            $searchValue = '%' . $searchValue . '%';
                        }
                        $queryBuilder->setParameter(':columnSearch' . $column->getId(), $searchValue);
                    }
                }
            }
        }

        $pagination = new Paginator($queryBuilder, false);
        $pkgstatsFiltered = $pagination->count();
        $packages = $pagination->getQuery()->getScalarResult();

        array_walk($packages, function (&$item) {
            $item['count'] = (int)$item['count'];
        });

        $response = new DatatablesResponse($packages);
        $response->setRecordsFiltered($pkgstatsFiltered);

        return $response;
    }

    /**
     * @param $textSearchableColumns
     * @return string
     */
    private function createTextSearchQuery($textSearchableColumns): string
    {
        $textSearchesArray = iterator_to_array($this->createTextSearchesIterator($textSearchableColumns));
        return '(' . implode(' OR ', $textSearchesArray) . ')';
    }

    /**
     * @param $textSearchableColumns
     * @return \Iterator
     */
    private function createTextSearchesIterator($textSearchableColumns): \Iterator
    {
        foreach ($textSearchableColumns as $textSearchableColumn) {
            yield $textSearchableColumn . ' LIKE :search';
        }
    }

    /**
     * @return int
     */
    private function calculatePkgstatsCount(): int
    {
        $cachedPackageCount = $this->cache->getItem('pkgstats.count');
        if ($cachedPackageCount->isHit()) {
            /** @var int $packageCount */
            $packageCount = $cachedPackageCount->get();
        } else {
            $packageCount = $this->userRepository->getCountSince($this->getRangeTime());

            $cachedPackageCount->expiresAt(new \DateTime('24 hour'));
            $cachedPackageCount->set($packageCount);
            $this->cache->save($cachedPackageCount);
        }
        return $packageCount;
    }
}
