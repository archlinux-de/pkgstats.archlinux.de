<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class PkgstatsRequestRateLimitSubscriber implements EventSubscriberInterface
{
    /** @var RateLimiterFactory */
    private $pkgstatsRequestLimiter;

    /**
     * @param RateLimiterFactory $pkgstatsRequestLimiter
     */
    public function __construct(RateLimiterFactory $pkgstatsRequestLimiter)
    {
        $this->pkgstatsRequestLimiter = $pkgstatsRequestLimiter;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => ['onKernelRequest']];
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!in_array($event->getRequest()->attributes->get('_route'), ['app_api_submit', 'app_pkgstats_post'])) {
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
