<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Statement;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class FunStatisticsController extends Controller
{
    use StatisticsControllerTrait;

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
        $total = $this->database->query('
            SELECT
                COUNT(*)
            FROM
                `user`
            WHERE
                time >= ' . $this->getRangeTime() . '
            ')->fetchColumn();

        $stm = $this->database->prepare('
            SELECT
                SUM(`count`)
            FROM
                package
            WHERE
                pkgname = :pkgname
                AND `month` >= ' . $this->getRangeYearMonth() . '
            GROUP BY
                pkgname
            ');

        return [
            'total' => $total,
            'stats' => [[
                'name' => 'Browsers',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'Mozilla Firefox' => 'firefox',
                        'Chromium' => 'chromium',
                        'Konqueror' => ['kdebase-konqueror', 'konqueror'],
                        'Midori' => 'midori',
                        'Epiphany' => 'epiphany',
                        'Opera' => 'opera',
                    )
                )
            ], [
                'name' => 'Editors',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'Vim' => array(
                            'vim',
                            'gvim',
                        ),
                        'Emacs' => array(
                            'emacs',
                            'xemacs',
                        ),
                        'Nano' => 'nano',
                        'Gedit' => 'gedit',
                        'Kate' => array('kdesdk-kate', 'kate'),
                        'Kwrite' => array('kdebase-kwrite', 'kwrite'),
                        'Vi' => 'vi',
                        'Mousepad' => 'mousepad',
                        'Leafpad' => 'leafpad',
                        'Geany' => 'geany',
                        'Pluma' => 'pluma',
                    )
                )
            ], [
                'name' => 'Desktop Environments',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'KDE SC' => array('kdebase-workspace', 'plasma-workspace', 'plasma-desktop'),
                        'GNOME' => 'gnome-shell',
                        'LXDE' => 'lxde-common',
                        'Xfce' => 'xfdesktop',
                        'Enlightenment' => array('enlightenment', 'enlightenment16'),
                        'MATE' => 'mate-panel',
                        'Cinnamon' => 'cinnamon',
                    )
                )
            ], [
                'name' => 'File Managers',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'Dolphin' => ['kdebase-dolphin', 'dolphin'],
                        'Konqueror' => ['kdebase-konqueror', 'konqueror'],
                        'MC' => 'mc',
                        'Nautilus' => 'nautilus',
                        'Pcmanfm' => 'pcmanfm',
                        'Thunar' => 'thunar',
                        'Caja' => 'caja',
                    )
                )
            ], [
                'name' => 'Window Managers',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'Openbox' => 'openbox',
                        'Fluxbox' => 'fluxbox',
                        'I3' => 'i3-wm',
                        'awesome' => 'awesome',
                    )
                )
            ], [
                'name' => 'Media Players',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'Mplayer' => 'mplayer',
                        'Xine' => 'xine-lib',
                        'VLC' => 'vlc',
                    )
                )
            ], [
                'name' => 'Shells',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'Bash' => 'bash',
                        'Dash' => 'dash',
                        'Zsh' => 'zsh',
                        'Fish' => 'fish',
                        'Tcsh' => 'tcsh',
                    )
                )
            ], [
                'name' => 'Graphic Chipsets',
                'data' => $this->getPackageStatistics(
                    $stm,
                    array(
                        'ATI' => array(
                            'xf86-video-ati',
                            'xf86-video-r128',
                            'xf86-video-mach64',
                        ),
                        'NVIDIA' => array(
                            'nvidia-304xx-utils',
                            'nvidia-utils',
                            'xf86-video-nouveau',
                            'xf86-video-nv',
                        ),
                        'Intel' => array(
                            'xf86-video-intel',
                            'xf86-video-i740',
                        ),
                    )
                )
            ]]
        ];
    }

    /**
     * @param Statement $stm
     * @param array $packages
     *
     * @return array
     */
    private function getPackageStatistics(Statement $stm, array $packages): array
    {
        $packageArray = array();
        foreach ($packages as $package => $pkgnames) {
            if (!is_array($pkgnames)) {
                $pkgnames = array(
                    $pkgnames,
                );
            }
            foreach ($pkgnames as $pkgname) {
                $stm->bindValue('pkgname', $pkgname, \PDO::PARAM_STR);
                $stm->execute();
                $count = $stm->fetchColumn() ?: 0;
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
}
