<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

readonly class PkgstatsRequestRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(private RateLimiterFactoryInterface $pkgstatsRequestLimiter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest']];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->getRequest()->attributes->get('_route') != 'app_api_submit') {
            return;
        }

        $limiter = $this->pkgstatsRequestLimiter->create($event->getRequest()->getClientIp());
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->format(\DateTimeInterface::RFC7231),
                sprintf(
                    'You already submitted your data %d times. Retry after %s.',
                    $limit->getLimit(),
                    $limit->getRetryAfter()->format(\DateTimeInterface::RFC3339)
                )
            );
        }
    }
}
