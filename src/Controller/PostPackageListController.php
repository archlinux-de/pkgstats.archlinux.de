<?php

namespace App\Controller;

use App\Service\ClientIdGenerator;
use App\Service\GeoIp;
use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PostPackageListController extends AbstractController
{
    /** @var int */
    private $delay = 86400; // 24 hours
    /** @var int */
    private $count = 10;
    /** @var bool */
    private $quiet = false;
    /** @var Connection */
    private $database;
    /** @var RouterInterface */
    private $router;
    /** @var GeoIp */
    private $geoIp;
    /** @var ClientIdGenerator */
    private $clientIdGenerator;

    /**
     * @param Connection $connection
     * @param RouterInterface $router
     * @param GeoIp $geoIp
     * @param ClientIdGenerator $clientIdGenerator
     */
    public function __construct(
        Connection $connection,
        RouterInterface $router,
        GeoIp $geoIp,
        ClientIdGenerator $clientIdGenerator
    ) {
        $this->database = $connection;
        $this->router = $router;
        $this->geoIp = $geoIp;
        $this->clientIdGenerator = $clientIdGenerator;
    }

    /**
     * @Route("/post", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function postAction(Request $request): Response
    {
        # Can be rewritten once 2.0 is no longer in use
        $pkgstatsver = $request->request->get(
            'pkgstatsver',
            str_replace('pkgstats/', '', $request->server->get('HTTP_USER_AGENT'))
        );

        if (!in_array($pkgstatsver, array(
            '1.0',
            '2.0',
            '2.1',
            '2.2',
            '2.3',
        ))
        ) {
            throw new BadRequestHttpException('Sorry, your version of pkgstats is not supported.');
        }

        $packages = array_unique(explode("\n", trim($request->request->get('packages'))));
        $packageCount = count($packages);
        if (in_array($pkgstatsver, array('2.2', '2.3'))) {
            $modules = array_unique(explode("\n", trim($request->request->get('modules'))));
            $moduleCount = count($modules);
        } else {
            $modules = array();
            $moduleCount = null;
        }
        $arch = $request->request->get('arch');
        $cpuArch = $request->request->get('cpuarch', '');
        # Can be rewritten once 1.0 is no longer in use
        $mirror = $request->request->get('mirror', '');
        # Can be rewritten once 2.0 is no longer in use
        $this->quiet = ($request->request->get('quiet', 'false') == 'true');

        if (!empty($mirror) && !preg_match('#^(https?|ftp)://\S+/#', $mirror)) {
            $mirror = null;
        } elseif (!empty($mirror) && strlen($mirror) > 255) {
            throw new BadRequestHttpException($mirror . ' is too long.');
        } elseif (empty($mirror)) {
            $mirror = null;
        }
        if (!in_array($arch, array(
            'i686',
            'x86_64',
        ))
        ) {
            throw new BadRequestHttpException($arch . ' is not a known architecture.');
        }
        if (!in_array($cpuArch, array(
            'i686',
            'x86_64',
            '',
        ))
        ) {
            throw new BadRequestHttpException($cpuArch . ' is not a known architecture.');
        }
        if ($cpuArch == '') {
            $cpuArch = null;
        }
        if ($packageCount == 0) {
            throw new BadRequestHttpException('Your package list is empty.');
        }
        if ($packageCount > 10000) {
            throw new BadRequestHttpException('So, you have installed more than 10,000 packages?');
        }
        foreach ($packages as $package) {
            if (!preg_match('/^[^-]+\S{0,254}$/', $package)) {
                throw new BadRequestHttpException($package . ' does not look like a valid package');
            }
        }
        if ($moduleCount > 5000) {
            throw new BadRequestHttpException('So, you have loaded more than 5,000 modules?');
        }
        foreach ($modules as $module) {
            if (!preg_match('/^[\w\-]{1,254}$/', $module)) {
                throw new BadRequestHttpException($module . ' does not look like a valid module');
            }
        }
        $this->checkIfAlreadySubmitted($request);
        $clientIp = $request->getClientIp();
        $countryCode = $this->geoIp->getCountryCode($clientIp);
        if (empty($countryCode)) {
            $countryCode = null;
        }
        try {
            $this->database->beginTransaction();
            $stm = $this->database->prepare('
            INSERT INTO
                `user`
            SET
                ip = :ip,
                time = :time,
                arch = :arch,
                cpuarch = :cpuarch,
                countryCode = :countryCode,
                mirror = :mirror,
                packages = :packages,
                modules = :modules
            ');
            $stm->bindValue('ip', $this->clientIdGenerator->createClientId($clientIp), \PDO::PARAM_STR);
            $stm->bindValue('time', time(), \PDO::PARAM_INT);
            $stm->bindParam('arch', $arch, \PDO::PARAM_STR);
            $stm->bindParam('cpuarch', $cpuArch, \PDO::PARAM_STR);
            $stm->bindParam('countryCode', $countryCode, \PDO::PARAM_STR);
            $stm->bindParam('mirror', $mirror, \PDO::PARAM_STR);
            $stm->bindParam('packages', $packageCount, \PDO::PARAM_INT);
            $stm->bindParam('modules', $moduleCount, \PDO::PARAM_INT);
            $stm->execute();
            $stm = $this->database->prepare('
            INSERT INTO
                package
            SET
                pkgname = :pkgname,
                month = :month,
                count = 1
            ON DUPLICATE KEY UPDATE
                count = count + 1
            ');
            foreach ($packages as $package) {
                $stm->bindValue('pkgname', $package, \PDO::PARAM_STR);
                $stm->bindValue('month', date('Ym', time()), \PDO::PARAM_INT);
                $stm->execute();
            }
            $stm = $this->database->prepare('
            INSERT INTO
                module
            SET
                name = :module,
                month = :month,
                count = 1
            ON DUPLICATE KEY UPDATE
                count = count + 1
            ');
            foreach ($modules as $module) {
                $stm->bindParam('module', $module, \PDO::PARAM_STR);
                $stm->bindValue('month', date('Ym', time()), \PDO::PARAM_INT);
                $stm->execute();
            }
            $this->database->commit();
        } catch (\PDOException $e) {
            $this->database->rollBack();
            throw new HttpException(500, $e->getMessage(), $e);
        }

        if (!$this->quiet) {
            $body = 'Thanks for your submission. :-)' . "\n" . 'See results at '
                . $this->router->generate('app_start_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . "\n";
        } else {
            $body = '';
        }

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function checkIfAlreadySubmitted(Request $request)
    {
        $stm = $this->database->prepare('
        SELECT
            COUNT(*) AS count,
            MIN(time) AS mintime
        FROM
            `user`
        WHERE
            time >= :time
            AND ip = :ip
        GROUP BY
            ip
        ');
        $stm->bindValue('time', time() - $this->delay, \PDO::PARAM_INT);
        $stm->bindValue('ip', $this->clientIdGenerator->createClientId($request->getClientIp()), \PDO::PARAM_STR);
        $stm->execute();
        $log = $stm->fetch();
        if ($log !== false && $log['count'] >= $this->count) {
            throw new BadRequestHttpException(
                'You already submitted your data '
                . $this->count . ' times since '
                . $this->createGmDateTime($log['mintime'])
                . ' using the IP ' . $request->getClientIp()
                . ".\n         You are blocked until "
                . $this->createGmDateTime($log['mintime'] + $this->delay)
            );
        }
    }

    /**
     * @param int $timestamp
     * @return string
     */
    private function createGmDateTime($timestamp): string
    {
        return gmdate('Y-m-d H:i', $timestamp);
    }
}
