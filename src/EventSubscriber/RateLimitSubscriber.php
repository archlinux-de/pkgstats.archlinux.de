<?php

namespace App\EventSubscriber;

use App\Controller\PostPackageListController;
use App\Repository\UserRepository;
use App\Service\ClientIdGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class RateLimitSubscriber implements EventSubscriberInterface
{
    /** @var int */
    private $delay;

    /** @var int */
    private $count;

    /** @var ClientIdGenerator */
    private $clientIdGenerator;

    /** @var UserRepository */
    private $userRepository;

    /**
     * @param int $delay
     * @param int $count
     * @param ClientIdGenerator $clientIdGenerator
     * @param UserRepository $userRepository
     */
    public function __construct(
        int $delay,
        int $count,
        ClientIdGenerator $clientIdGenerator,
        UserRepository $userRepository
    ) {
        $this->delay = $delay;
        $this->count = $count;
        $this->clientIdGenerator = $clientIdGenerator;
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
    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if (isset($controller[0]) && $controller[0] instanceof PostPackageListController) {
            $submissionCount = $this->userRepository->getSubmissionCountSince(
                $this->clientIdGenerator->createClientId($event->getRequest()->getClientIp() ?? '127.0.0.1'),
                time() - $this->delay
            );
            if ($submissionCount >= $this->count) {
                throw new AccessDeniedHttpException(
                    sprintf('You already submitted your data %d times.', $this->count)
                );
            }
        }
    }
}
