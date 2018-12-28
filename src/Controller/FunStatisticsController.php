<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FunStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths;
    /** @var PackageRepository */
    private $packageRepository;
    /** @var UserRepository */
    private $userRepository;
    /** @var array */
    private $funConfiguration;

    /**
     * @param int $rangeMonths
     * @param PackageRepository $packageRepository
     * @param UserRepository $userRepository
     * @param array $funConfiguration
     */
    public function __construct(
        int $rangeMonths,
        PackageRepository $packageRepository,
        UserRepository $userRepository,
        array $funConfiguration
    ) {
        $this->rangeMonths = $rangeMonths;
        $this->packageRepository = $packageRepository;
        $this->userRepository = $userRepository;
        $this->funConfiguration = $funConfiguration;
    }

    /**
     * @Route("/fun", methods={"GET"})
     * @Cache(smaxage="86400")
     * @return Response
     */
    public function funAction(): Response
    {
        $data = $this->getData();
        return $this->render('fun.html.twig', $data);
    }

    /**
     * @return array
     */
    private function getData(): array
    {
        $total = $this->userRepository->getCountSince($this->getRangeTime());

        $stats = [];
        foreach ($this->funConfiguration as $funCategory => $funPackages) {
            $stats[] = [
                'name' => $funCategory,
                'data' => $this->getPackageStatistics($funPackages)
            ];
        }

        return ['total' => $total, 'stats' => $stats];
    }

    /**
     * @return int
     */
    private function getRangeTime(): int
    {
        return strtotime(date('1-m-Y', strtotime('now -' . $this->rangeMonths . ' months')));
    }

    /**
     * @param array $packages
     *
     * @return array
     */
    private function getPackageStatistics(array $packages): array
    {
        $packageArray = [];
        foreach ($packages as $package => $pkgnames) {
            if (!is_array($pkgnames)) {
                $pkgnames = [
                    $pkgnames,
                ];
            }
            foreach ($pkgnames as $pkgname) {
                $count = $this->packageRepository->getCountByNameSince($pkgname, $this->getRangeYearMonth());
                if (isset($packageArray[$package])) {
                    $packageArray[$package] += $count;
                } else {
                    $packageArray[$package] = $count;
                }
            }
        }

        arsort($packageArray);
        return $packageArray;
    }

    /**
     * @return int
     */
    private function getRangeYearMonth(): int
    {
        return date('Ym', $this->getRangeTime());
    }
}
