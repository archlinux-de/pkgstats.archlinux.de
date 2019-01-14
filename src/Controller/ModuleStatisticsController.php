<?php

namespace App\Controller;

use App\Repository\ModuleRepository;
use App\Repository\UserRepository;
use DatatablesApiBundle\DatatablesColumnConfiguration;
use DatatablesApiBundle\DatatablesQuery;
use DatatablesApiBundle\DatatablesRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ModuleStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths;

    /** @var ModuleRepository */
    private $moduleRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var DatatablesQuery */
    private $datatablesQuery;

    /**
     * @param int $rangeMonths
     * @param ModuleRepository $moduleRepository
     * @param UserRepository $userRepository
     * @param DatatablesQuery $datatablesQuery
     */
    public function __construct(
        int $rangeMonths,
        ModuleRepository $moduleRepository,
        UserRepository $userRepository,
        DatatablesQuery $datatablesQuery
    ) {
        $this->rangeMonths = $rangeMonths;
        $this->moduleRepository = $moduleRepository;
        $this->userRepository = $userRepository;
        $this->datatablesQuery = $datatablesQuery;
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
     * @Cache(smaxage="86400")
     * @return Response
     */
    public function moduleJsonAction(): Response
    {
        $modules = $this->moduleRepository
            ->createQueryBuilder('module')
            ->select('module.name AS name')
            ->addSelect('SUM(module.count) AS count')
            ->where('module.month >= :month')
            ->setParameter('month', $this->getRangeYearMonth())
            ->groupBy('module.name')
            ->getQuery()
            ->getScalarResult();

        array_walk($modules, function (&$item) {
            $item['count'] = (int)$item['count'];
        });

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
        return (int)strtotime(date('1-m-Y', (int)strtotime('now -' . $this->rangeMonths . ' months')));
    }

    /**
     * @Route("/module/datatables", methods={"GET"})
     * @param DatatablesRequest $request
     * @return Response
     */
    public function datatablesAction(DatatablesRequest $request): Response
    {
        $columnConfiguration = (new DatatablesColumnConfiguration())
            ->addTextSearchableColumn('name', 'module.name')
            ->addOrderableColumn('count', 'count');
        $response = $this->datatablesQuery->getResult(
            $request,
            $columnConfiguration,
            $this->moduleRepository
                ->createQueryBuilder('module')
                ->select('module.name AS name')
                ->addSelect('SUM(module.count) AS count')
                ->where('module.month >= :month')
                ->setParameter('month', $this->getRangeYearMonth())
                ->groupBy('module.name'),
            $this->userRepository->getCountSince($this->getRangeTime())
        );

        $response->setData(
            array_map(
                function ($item) {
                    $item['count'] = (int)$item['count'];
                    return $item;
                },
                $response->getData()
            )
        );

        return $this->json($response);
    }
}
