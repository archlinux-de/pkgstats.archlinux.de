<?php

namespace App\Controller;

use App\Entity\Module;
use App\Entity\Package;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ClientIdGenerator;
use App\Service\GeoIp;
use Doctrine\ORM\EntityManagerInterface;
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
    /** @var RouterInterface */
    private $router;
    /** @var GeoIp */
    private $geoIp;
    /** @var ClientIdGenerator */
    private $clientIdGenerator;
    /** @var UserRepository */
    private $userRepository;
    /** @var EntityManagerInterface */
    private $entityManager;

    /**
     * @param RouterInterface $router
     * @param GeoIp $geoIp
     * @param ClientIdGenerator $clientIdGenerator
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        RouterInterface $router,
        GeoIp $geoIp,
        ClientIdGenerator $clientIdGenerator,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->router = $router;
        $this->geoIp = $geoIp;
        $this->clientIdGenerator = $clientIdGenerator;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
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

        if (!in_array($pkgstatsver, [
            '1.0',
            '2.0',
            '2.1',
            '2.2',
            '2.3',
        ])
        ) {
            throw new BadRequestHttpException('Sorry, your version of pkgstats is not supported.');
        }

        $packages = array_unique(explode("\n", trim($request->request->get('packages'))));
        $packageCount = count($packages);
        if (in_array($pkgstatsver, ['2.2', '2.3'])) {
            $modules = array_unique(explode("\n", trim($request->request->get('modules'))));
            $moduleCount = count($modules);
        } else {
            $modules = [];
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
        if (!in_array($arch, [
            'i686',
            'x86_64',
        ])
        ) {
            throw new BadRequestHttpException($arch . ' is not a known architecture.');
        }
        if (!in_array($cpuArch, [
            'i686',
            'x86_64',
            '',
        ])
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
            $this->entityManager->beginTransaction();
            $user = (new User())
                ->setIp($this->clientIdGenerator->createClientId($clientIp))
                ->setTime(time())
                ->setArch($arch)
                ->setCpuarch($cpuArch)
                ->setCountrycode($countryCode)
                ->setMirror($mirror)
                ->setPackages($packageCount)
                ->setModules($moduleCount);
            $this->entityManager->persist($user);

            foreach ($packages as $package) {
                $packageEntity = (new Package())
                    ->setPkgname($package)
                    ->setMonth(date('Ym', time()));
                $this->entityManager->merge($packageEntity);
            }

            foreach ($modules as $module) {
                $moduleEntity = (new Module())
                    ->setName($module)
                    ->setMonth(date('Ym', time()));
                $this->entityManager->merge($moduleEntity);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\PDOException $e) {
            $this->entityManager->rollback();
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

    /**
     * @param Request $request
     */
    private function checkIfAlreadySubmitted(Request $request)
    {
        $submissionCount = $this->userRepository->getSubmissionCountSince(
            $this->clientIdGenerator->createClientId($request->getClientIp()),
            time() - $this->delay
        );
        if ($submissionCount >= $this->count) {
            throw new BadRequestHttpException(
                'You already submitted your data ' . $this->count . ' times.'
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
