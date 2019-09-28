<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FunController extends AbstractController
{
    /** @var int */
    private $rangeMonths;

    /** @var PackageRepository */
    private $packageRepository;

    /** @var array */
    private $funConfiguration;

    /**
     * @param int $rangeMonths
     * @param PackageRepository $packageRepository
     * @param array $funConfiguration
     */
    public function __construct(int $rangeMonths, PackageRepository $packageRepository, array $funConfiguration)
    {
        $this->rangeMonths = $rangeMonths;
        $this->packageRepository = $packageRepository;
        $this->funConfiguration = $funConfiguration;
    }

    /**
     * @Route("/fun", methods={"GET"}, name="app_fun")
     * @Cache(smaxage="+1 day", maxage="+5 minutes")
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
        $total = $this->packageRepository->getMaximumCountSince($this->getRangeYearMonth());

        $stats = [];
        foreach ($this->funConfiguration as $funCategory => $funPackages) {
            $stats[] = [
                'name' => $funCategory,
                'data' => $this->getPackageStatistics($funPackages),
                'packages' => $this->getPackageNames($funPackages)
            ];
        }

        return ['total' => $total, 'stats' => $stats];
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
     * @param array $packages
     *
     * @return array
     */
    private function getPackageStatistics(array $packages): array
    {
        $packageArray = [];
        foreach ($packages as $package => $names) {
            if (!is_array($names)) {
                $names = [$names];
            }
            foreach ($names as $name) {
                $count = $this->packageRepository->getCountByNameSince($name, $this->getRangeYearMonth());
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
     * @param array $packages
     * @return array
     */
    private function getPackageNames(array $packages): array
    {
        $packageArray = [];
        foreach ($packages as $names) {
            if (!is_array($names)) {
                $names = [$names];
            }
            $packageArray = array_merge($packageArray, $names);
        }

        return $packageArray;
    }
}
