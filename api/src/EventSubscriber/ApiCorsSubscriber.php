<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\String\ByteString;

class ApiCorsSubscriber implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    /**
     * @param ResponseEvent $responseEvent
     */
    public function onKernelResponse(ResponseEvent $responseEvent): void
    {
        if (!$responseEvent->isMainRequest()) {
            return;
        }

        $request = $responseEvent->getRequest();

        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }

        $path = new ByteString($request->getPathInfo());

        if ($path->startsWith('/api/')) {
            $response = $responseEvent->getResponse();
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }
    }
}
