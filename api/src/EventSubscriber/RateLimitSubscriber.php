<?php

namespace App\EventSubscriber;

use App\Controller\PostPackageListController;
use App\Repository\UserRepository;
use App\Request\PkgstatsRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class RateLimitSubscriber implements EventSubscriberInterface
{
    /** @var int */
    private $delay;

    /** @var int */
    private $count;

    /** @var UserRepository */
    private $userRepository;

    /**
     * @param int $delay
     * @param int $count
     * @param UserRepository $userRepository
     */
    public function __construct(int $delay, int $count, UserRepository $userRepository)
    {
        $this->delay = $delay;
        $this->count = $count;
        $this->userRepository = $userRepository;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::CONTROLLER => ['onKernelController', -1]];
    }

    /**
     * @param ControllerEvent $event
     */
    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if (isset($controller[0]) && $controller[0] instanceof PostPackageListController) {
            /** @var PkgstatsRequest $pkgStatsRequest */
            $pkgStatsRequest = $event->getRequest()->attributes->get('pkgstatsRequest');
            if (!$pkgStatsRequest instanceof PkgstatsRequest) {
                throw new \RuntimeException('Missing ' . PkgstatsRequest::class);
            }

            $submissionCount = $this->userRepository->getSubmissionCountSince(
                $pkgStatsRequest->getUser()->getIp(),
                time() - $this->delay
            );

            if ($submissionCount >= $this->count) {
                throw new TooManyRequestsHttpException(
                    $this->delay,
                    sprintf(
                        'You already submitted your data %d times. Retry after %d seconds',
                        $this->count,
                        $this->delay
                    )
                );
            }
        }
    }
}
