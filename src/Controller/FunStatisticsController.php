<?php

namespace App\Controller;

use App\Repository\PackageRepository;
use App\Repository\UserRepository;
use Psr\Cache\CacheItemPoolInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FunStatisticsController extends AbstractController
{
    /** @var int */
    private $rangeMonths = 3;
    /** @var CacheItemPoolInterface */
    private $cache;
    /** @var PackageRepository */
    private $packageRepository;
    /** @var UserRepository */
    private $userRepository;

    /**
     * @param CacheItemPoolInterface $cache
     * @param PackageRepository $packageRepository
     * @param UserRepository $userRepository
     */
    public function __construct(
        CacheItemPoolInterface $cache,
        PackageRepository $packageRepository,
        UserRepository $userRepository
    ) {
        $this->cache = $cache;
        $this->packageRepository = $packageRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/fun", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function funAction(): Response
    {
        $cachedData = $this->cache->getItem('fun.statistics');
        if ($cachedData->isHit()) {
            $data = $cachedData->get();
        } else {
            $data = $this->getData();
            $cachedData->expiresAt(new \DateTime('24 hour'));
            $cachedData->set($data);

            $this->cache->save($cachedData);
        }

        return $this->render('fun.html.twig', $data);
    }

    private function getData(): array
    {
        $total = $this->userRepository->getCountSince($this->getRangeTime());

        return [
            'total' => $total,
            'stats' => [
                [
                    'name' => 'Browsers',
                    'data' => $this->getPackageStatistics(
                        [
                            'Mozilla Firefox' => 'firefox',
                            'Chromium' => 'chromium',
                            'Konqueror' => ['kdebase-konqueror', 'konqueror'],
                            'Midori' => 'midori',
                            'Epiphany' => 'epiphany',
                            'Opera' => 'opera',
                        ]
                    )
                ],
                [
                    'name' => 'Editors',
                    'data' => $this->getPackageStatistics(
                        [
                            'Vim' => [
                                'vim',
                                'gvim',
                            ],
                            'Emacs' => [
                                'emacs',
                                'xemacs',
                            ],
                            'Nano' => 'nano',
                            'Gedit' => 'gedit',
                            'Kate' => ['kdesdk-kate', 'kate'],
                            'Kwrite' => ['kdebase-kwrite', 'kwrite'],
                            'Vi' => 'vi',
                            'Mousepad' => 'mousepad',
                            'Leafpad' => 'leafpad',
                            'Geany' => 'geany',
                            'Pluma' => 'pluma',
                        ]
                    )
                ],
                [
                    'name' => 'Desktop Environments',
                    'data' => $this->getPackageStatistics(
                        [
                            'KDE SC' => ['kdebase-workspace', 'plasma-workspace', 'plasma-desktop'],
                            'GNOME' => 'gnome-shell',
                            'LXDE' => 'lxde-common',
                            'Xfce' => 'xfdesktop',
                            'Enlightenment' => ['enlightenment', 'enlightenment16'],
                            'MATE' => 'mate-panel',
                            'Cinnamon' => 'cinnamon',
                        ]
                    )
                ],
                [
                    'name' => 'File Managers',
                    'data' => $this->getPackageStatistics(
                        [
                            'Dolphin' => ['kdebase-dolphin', 'dolphin'],
                            'Konqueror' => ['kdebase-konqueror', 'konqueror'],
                            'MC' => 'mc',
                            'Nautilus' => 'nautilus',
                            'Pcmanfm' => 'pcmanfm',
                            'Thunar' => 'thunar',
                            'Caja' => 'caja',
                        ]
                    )
                ],
                [
                    'name' => 'Window Managers',
                    'data' => $this->getPackageStatistics(
                        [
                            'Openbox' => 'openbox',
                            'Fluxbox' => 'fluxbox',
                            'I3' => 'i3-wm',
                            'awesome' => 'awesome',
                        ]
                    )
                ],
                [
                    'name' => 'Media Players',
                    'data' => $this->getPackageStatistics(
                        [
                            'Mplayer' => 'mplayer',
                            'Xine' => 'xine-lib',
                            'VLC' => 'vlc',
                        ]
                    )
                ],
                [
                    'name' => 'Shells',
                    'data' => $this->getPackageStatistics(
                        [
                            'Bash' => 'bash',
                            'Dash' => 'dash',
                            'Zsh' => 'zsh',
                            'Fish' => 'fish',
                            'Tcsh' => 'tcsh',
                        ]
                    )
                ],
                [
                    'name' => 'Graphic Chipsets',
                    'data' => $this->getPackageStatistics(
                        [
                            'ATI' => [
                                'xf86-video-ati',
                                'xf86-video-r128',
                                'xf86-video-mach64',
                            ],
                            'NVIDIA' => [
                                'nvidia-304xx-utils',
                                'nvidia-utils',
                                'xf86-video-nouveau',
                                'xf86-video-nv',
                            ],
                            'Intel' => [
                                'xf86-video-intel',
                                'xf86-video-i740',
                            ],
                        ]
                    )
                ]
            ]
        ];
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
