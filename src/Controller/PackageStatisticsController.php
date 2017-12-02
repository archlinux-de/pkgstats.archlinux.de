<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\DBAL\Query\QueryBuilder;
use App\Request\Datatables\Request as DatatablesRequest;
use App\Response\Datatables\Response as DatatablesResponse;

class PackageStatisticsController extends Controller
{
    use StatisticsControllerTrait;

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
            /** @var DatatablesResponse $response */
            $packages = $cachedPackages->get();
        } else {
            $connection = $this->getDoctrine()->getConnection();
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder
                ->select([
                    'pkgname',
                    'SUM(count) AS count'
                ])
                ->from('pkgstats_packages')
                ->where('month >= ' . $this->getRangeYearMonth())
                ->groupBy('pkgname');
            $packages = $queryBuilder->execute()->fetchAll(\PDO::FETCH_ASSOC);

            $cachedPackages->expiresAt(new \DateTime('24 hour'));
            $cachedPackages->set($packages);
            $this->cache->save($cachedPackages);
        }

        return $this->json($packages);
    }

    /**
     * @Route("/package/datatables", methods={"GET"})
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

        $cachedPkgstatsCount = $this->cache->getItem('pkgstats.count');
        if ($cachedPkgstatsCount->isHit()) {
            /** @var int $pkgstatsCount */
            $pkgstatsCount = $cachedPkgstatsCount->get();
        } else {
//            $pkgstatsCount = $this->getDoctrine()->getConnection()->createQueryBuilder()
//                ->select('DISTINCT pkgname')
//                ->from('pkgstats_packages')
//                ->where('month >= ' . $this->getRangeYearMonth())
//                ->execute()
//                ->rowCount();
            $pkgstatsCount = $this->getDoctrine()->getConnection()->createQueryBuilder()
                ->select('COUNT(*)')
                ->from('pkgstats_users')
                ->where('time >= ' . $this->getRangeTime())
                ->execute()
                ->fetchColumn();
            $cachedPkgstatsCount->expiresAt(new \DateTime('24 hour'));
            $cachedPkgstatsCount->set($pkgstatsCount);
            $this->cache->save($cachedPkgstatsCount);
        }

        $response->setRecordsTotal($pkgstatsCount);
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
                'pkgname' => 'pkgname'
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
                'SQL_CALC_FOUND_ROWS pkgname AS pkgname',
                'SUM(count) AS count'
            ])
            ->from('pkgstats_packages')
            ->where('month >= ' . $this->getRangeYearMonth())
            ->groupBy('pkgname')
            ->setFirstResult($request->getStart())
            ->setMaxResults($request->getLength());

        foreach ($request->getOrders() as $order) {
            $orderColumnName = $order->getColumn()->getData();
            if (isset($orderableColumns[$orderColumnName])) {
                $queryBuilder->orderBy($orderColumnName, $order->getDir());
            }
        }

        if ($request->hasSearch() && !$request->getSearch()->isRegex()) {
            $queryBuilder->andWhere('pkgname LIKE :search');
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

        $packages = $queryBuilder->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $pkgstatsFiltered = $connection->createQueryBuilder()
            ->select('FOUND_ROWS()')->execute()->fetchColumn();

        $response = new DatatablesResponse($packages);
        $response->setRecordsFiltered($pkgstatsFiltered);

        return $response;
    }
}
