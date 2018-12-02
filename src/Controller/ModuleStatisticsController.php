<?php

namespace App\Controller;

use App\Request\Datatables\Request as DatatablesRequest;
use App\Response\Datatables\Response as DatatablesResponse;
use Doctrine\DBAL\Query\QueryBuilder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ModuleStatisticsController extends AbstractController
{
    use StatisticsControllerTrait;

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
    public function packageJsonAction(): Response
    {
        $cachedModules = $this->cache->getItem('module.json');
        if ($cachedModules->isHit()) {
            /** @var DatatablesResponse $response */
            $modules = $cachedModules->get();
        } else {
            $connection = $this->getDoctrine()->getConnection();
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder
                ->select([
                    'name',
                    'SUM(`count`) AS count'
                ])
                ->from('module')
                ->where('month >= ' . $this->getRangeYearMonth())
                ->groupBy('name');
            $modules = $queryBuilder->execute()->fetchAll(\PDO::FETCH_ASSOC);

            $cachedModules->expiresAt(new \DateTime('24 hour'));
            $cachedModules->set($modules);
            $this->cache->save($cachedModules);
        }

        return $this->json($modules);
    }

    /**
     * @Route("/module/datatables", methods={"GET"})
     * @param DatatablesRequest $request
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function datatablesAction(DatatablesRequest $request): Response
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

        $cachedModuleCount = $this->cache->getItem('module.count');
        if ($cachedModuleCount->isHit()) {
            /** @var int $moduleCount */
            $moduleCount = $cachedModuleCount->get();
        } else {
            $moduleCount = $this->getDoctrine()->getConnection()->createQueryBuilder()
                ->select('DISTINCT name')
                ->from('module')
                ->where('`month` >= ' . $this->getRangeYearMonth())
                ->execute()
                ->rowCount();
//            $moduleCount = $this->getDoctrine()->getConnection()->createQueryBuilder()
//                ->select('COUNT(*)')
//                ->from('users')
//                ->where('time >= ' . $this->getRangeTime())
//                ->execute()
//                ->fetchColumn();
            $cachedModuleCount->expiresAt(new \DateTime('24 hour'));
            $cachedModuleCount->set($moduleCount);
            $this->cache->save($cachedModuleCount);
        }

        $response->setRecordsTotal($moduleCount);
        $response->setDraw($request->getDraw());

        return $this->json(
            $response,
            Response::HTTP_OK,
            [
                'X-Cache-App' => $cachedResponse->isHit() ? 'HIT' : 'MISS'
            ]
        );
    }

    /**
     * @param DatatablesRequest $request
     * @return DatatablesResponse
     */
    private function queryDatabase(DatatablesRequest $request): DatatablesResponse
    {
        $compareableColumns = [
        ];
        $searchableColumns = array_merge(
            $compareableColumns,
            [
                'name' => 'name'
            ]
        );
        $orderableColumns = array_merge(
            $compareableColumns,
            [
                'count' => 'count'
            ]
        );

        $connection = $this->getDoctrine()->getConnection();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select([
                'SQL_CALC_FOUND_ROWS name',
                'SUM(count) AS count'
            ])
            ->from('module')
            ->where('month >= ' . $this->getRangeYearMonth())
            ->groupBy('name')
            ->setFirstResult($request->getStart())
            ->setMaxResults($request->getLength());

        foreach ($request->getOrders() as $order) {
            $orderColumnName = $order->getColumn()->getData();
            if (isset($orderableColumns[$orderColumnName])) {
                $queryBuilder->orderBy($orderColumnName, $order->getDir());
            }
        }

        if ($request->hasSearch() && !$request->getSearch()->isRegex()) {
            $queryBuilder->andWhere('name LIKE :search');
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

        $modules = $queryBuilder->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $modulesFiltered = $connection->createQueryBuilder()
            ->select('FOUND_ROWS()')->execute()->fetchColumn();

        $response = new DatatablesResponse($modules);
        $response->setRecordsFiltered($modulesFiltered);

        return $response;
    }
}
