<?php

namespace App\Controller;

use App\Repository\ModuleRepository;
use App\Repository\UserRepository;
use DatatablesApiBundle\Request\Datatables\Request as DatatablesRequest;
use DatatablesApiBundle\Response\Datatables\Response as DatatablesResponse;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Cache\CacheItemPoolInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ModuleStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var ModuleRepository */
    private $moduleRepository;

    /** @var UserRepository */
    private $userRepository;

    /**
     * @param int $rangeMonths
     * @param CacheItemPoolInterface $cache
     * @param ModuleRepository $moduleRepository
     * @param UserRepository $userRepository
     */
    public function __construct(
        int $rangeMonths,
        CacheItemPoolInterface $cache,
        ModuleRepository $moduleRepository,
        UserRepository $userRepository
    ) {
        $this->rangeMonths = $rangeMonths;
        $this->cache = $cache;
        $this->moduleRepository = $moduleRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/module", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     */
    public function moduleAction(): Response
    {
        return $this->render('module.html.twig');
    }

    /**
     * @Route("/module.json", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function moduleJsonAction(): Response
    {
        $cachedModules = $this->cache->getItem('module.json');
        if ($cachedModules->isHit()) {
            $modules = $cachedModules->get();
        } else {
            $queryBuilder = $this->moduleRepository
                ->createQueryBuilder('module')
                ->select('module.name AS name')
                ->addSelect('SUM(module.count) AS count')
                ->where('module.month >= :month')
                ->setParameter('month', $this->getRangeYearMonth())
                ->groupBy('module.name');
            $modules = $queryBuilder->getQuery()->getScalarResult();

            array_walk($modules, function (&$item) {
                $item['count'] = (int)$item['count'];
            });

            $cachedModules->expiresAt(new \DateTime('24 hour'));
            $cachedModules->set($modules);
            $this->cache->save($cachedModules);
        }

        return $this->json($modules);
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
     * @Route("/module/datatables", methods={"GET"})
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

        $moduleCount = $this->calculateModuleCount();

        $response->setRecordsTotal($moduleCount);
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
            'name' => 'module.name'
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

        $queryBuilder = $this->moduleRepository
            ->createQueryBuilder('module')
            ->select('module.name AS name')
            ->addSelect('SUM(module.count) AS count')
            ->where('module.month >= :month')
            ->setParameter('month', $this->getRangeYearMonth())
            ->groupBy('module.name')
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
        $modulesFiltered = $pagination->count();
        $modules = $pagination->getQuery()->getScalarResult();

        array_walk($modules, function (&$item) {
            $item['count'] = (int)$item['count'];
        });

        $response = new DatatablesResponse($modules);
        $response->setRecordsFiltered($modulesFiltered);

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
    private function calculateModuleCount(): int
    {
        $cachedModuleCount = $this->cache->getItem('module.count');
        if ($cachedModuleCount->isHit()) {
            /** @var int $moduleCount */
            $moduleCount = $cachedModuleCount->get();
        } else {
            $moduleCount = $this->userRepository->getCountSince($this->getRangeTime());

            $cachedModuleCount->expiresAt(new \DateTime('24 hour'));
            $cachedModuleCount->set($moduleCount);
            $this->cache->save($cachedModuleCount);
        }
        return $moduleCount;
    }
}
