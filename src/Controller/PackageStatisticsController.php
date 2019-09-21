<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use DatatablesApiBundle\DatatablesColumnConfiguration;
use DatatablesApiBundle\DatatablesQuery;
use DatatablesApiBundle\DatatablesRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PackageStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths;

    /** @var PackageRepository */
    private $packageRepository;

    /** @var DatatablesQuery */
    private $datatablesQuery;

    /**
     * @param int $rangeMonths
     * @param PackageRepository $packageRepository
     * @param DatatablesQuery $datatablesQuery
     */
    public function __construct(
        int $rangeMonths,
        PackageRepository $packageRepository,
        DatatablesQuery $datatablesQuery
    ) {
        $this->rangeMonths = $rangeMonths;
        $this->packageRepository = $packageRepository;
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
            $this->packageRepository->getMaximumCountSince($this->getRangeYearMonth())
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
     * @return int
     */
    private function getRangeYearMonth(): int
    {
        return (int)date('Ym', $this->getRangeTime());
    }

    /**
     * @return int
     */
    private function getRangeTime(): int
    {
        return (int)strtotime(date('1-m-Y', (int)strtotime('now -' . $this->rangeMonths . ' months')));
    }

    /**
     * @Route("/package.json", methods={"GET"})
     * @Cache(smaxage="86400")
     * @return Response
     *
     * @SWG\Tag(name="packages")
     * @SWG\Get(
     *     deprecated=true
     * )
     * @SWG\Response(
     *     description="Returns count based on popularity of all packages",
     *     response=200,
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(
     *             type="object",
     *             @SWG\Property(property="pkgname", type="string"),
     *             @SWG\Property(property="count", type="integer")
     *         ),
     *    )
     * )
     * @deprecated
     */
    public function packageJsonAction(): Response
    {
        $packages = $this->packageRepository
            ->createQueryBuilder('package')
            ->select('package.pkgname AS pkgname')
            ->addSelect('SUM(package.count) AS count')
            ->where('package.month >= :month')
            ->setParameter('month', $this->getRangeYearMonth())
            ->groupBy('package.pkgname')
            ->getQuery()
            ->getScalarResult();
        array_walk($packages, function (&$item) {
            $item['count'] = (int)$item['count'];
        });
        return $this->json($packages);
    }

    /**
     * @Route(path="/packages/{package}", methods={"GET"})
     * @Cache(smaxage="900")
     * @param string $package
     * @return Response
     */
    public function packagesDetailAction(string $package): Response
    {
        $startMonth = $this->packageRepository->getFirstMonthByName($package);
        if (!$startMonth) {
            throw $this->createNotFoundException(sprintf('Package %s was not found', $package));
        }
        $endMonth = $this->packageRepository->getLatestMonthByName($package);

        return $this->render(
            'packages_detail.html.twig',
            [
                'package' => $package,
                'startMonth' => $startMonth,
                'endMonth' => $endMonth,
                'limit' => 0
            ]
        );
    }
}
