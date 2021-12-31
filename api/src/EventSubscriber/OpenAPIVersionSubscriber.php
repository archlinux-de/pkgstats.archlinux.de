<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OpenAPIVersionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse']];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (
            !$event->isMainRequest()
            || $event->getRequest()->attributes->get('_route') != 'app_swagger'
            || !$event->getResponse()->isOk()
        ) {
            return;
        }

        /**
         * @FIXME: Workaround for incompatible Swagger-UI
         * @see https://github.com/swagger-api/swagger-ui/issues/5891
         */
        $event->getResponse()->setContent(
            preg_replace(
                '/"openapi":\s*"3\.1\.[0-9]+"/',
                '"openapi": "3.0.0"',
                (string)$event->getResponse()->getContent()
            )
        );
    }
}
