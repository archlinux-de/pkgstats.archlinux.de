<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiDocJsonCacheSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse']];
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (
            !$event->isMasterRequest()
            || $event->getRequest()->attributes->get('_route') != 'app_swagger'
            || !$event->getResponse()->isOk()
        ) {
            return;
        }

        $event->getResponse()->setMaxAge(300);
        $event->getResponse()->setSharedMaxAge(3600);
    }
}
