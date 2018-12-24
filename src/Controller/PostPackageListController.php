<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Request\PkgstatsRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PostPackageListController extends AbstractController
{
    /** @var int */
    private $delay;
    /** @var int */
    private $count;
    /** @var UserRepository */
    private $userRepository;
    /** @var EntityManagerInterface */
    private $entityManager;

    /**
     * @param int $delay
     * @param int $count
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        int $delay,
        int $count,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->delay = $delay;
        $this->count = $count;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/post", methods={"POST"})
     * @param PkgstatsRequest $pkgstatsRequest
     * @return Response
     */
    public function postAction(PkgstatsRequest $pkgstatsRequest): Response
    {
        $user = $pkgstatsRequest->getUser();
        $packages = $pkgstatsRequest->getPackages();
        $modules = $pkgstatsRequest->getModules();

        $this->checkIfAlreadySubmitted($user);

        $this->entityManager->transactional(
            function (EntityManagerInterface $entityManager) use ($user, $packages, $modules) {
                $entityManager->persist($user);

                foreach ($packages as $package) {
                    $entityManager->merge($package);
                }

                foreach ($modules as $module) {
                    $entityManager->merge($module);
                }
            }
        );

        if (!$pkgstatsRequest->isQuiet()) {
            $body = 'Thanks for your submission. :-)' . "\n" . 'See results at '
                . $this->generateUrl('app_start_index', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . "\n";
        } else {
            $body = '';
        }

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @param User $user
     */
    private function checkIfAlreadySubmitted(User $user)
    {
        $submissionCount = $this->userRepository->getSubmissionCountSince(
            $user->getIp(),
            $user->getTime() - $this->delay
        );
        if ($submissionCount >= $this->count) {
            throw new BadRequestHttpException(
                'You already submitted your data ' . $this->count . ' times.'
            );
        }
    }
}
