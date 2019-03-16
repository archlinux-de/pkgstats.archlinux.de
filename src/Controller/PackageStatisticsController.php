<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use DatatablesApiBundle\DatatablesColumnConfiguration;
use DatatablesApiBundle\DatatablesQuery;
use DatatablesApiBundle\DatatablesRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PackageStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths;

    /** @var PackageRepository */
    private $packageRepository;

    /** @var UserRepository */
    private $userRepository;

    /** @var DatatablesQuery */
    private $datatablesQuery;

    /**
     * @param int $rangeMonths
     * @param PackageRepository $packageRepository
     * @param UserRepository $userRepository
     * @param DatatablesQuery $datatablesQuery
     */
    public function __construct(
        int $rangeMonths,
        PackageRepository $packageRepository,
        UserRepository $userRepository,
        DatatablesQuery $datatablesQuery
    ) {
        $this->rangeMonths = $rangeMonths;
        $this->packageRepository = $packageRepository;
        $this->userRepository = $userRepository;
        $this->datatablesQuery = $datatablesQuery;
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
     * @Route("/package/datatables", methods={"GET"})
     * @param DatatablesRequest $request
     * @return Response
     */
    public function datatablesAction(DatatablesRequest $request): Response
    {
        $columnConfiguration = (new DatatablesColumnConfiguration())
            ->addTextSearchableColumn('pkgname', 'package.pkgname')
            ->addOrderableColumn('count', 'count');
        $response = $this->datatablesQuery->getResult(
            $request,
            $columnConfiguration,
            $this->packageRepository
                ->createQueryBuilder('package')
                ->select('package.pkgname AS pkgname')
                ->addSelect('SUM(package.count) AS count')
                ->where('package.month >= :month')
                ->setParameter('month', $this->getRangeYearMonth())
                ->groupBy('package.pkgname'),
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
}
