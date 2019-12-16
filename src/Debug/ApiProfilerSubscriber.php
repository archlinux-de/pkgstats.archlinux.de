<?php

namespace App\Debug;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @codeCoverageIgnore
 */
class ApiProfilerSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse']];
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();
        $response->setContent(preg_replace_callback('/<script([^>]+)/i', function (array $matches) {
            if (stripos($matches[1], 'src') !== false && stripos($matches[1], 'defer') === false) {
                return '<script defer' . $matches[1];
            }
            return $matches[0];
        }, (string)$response->getContent()));
    }
}
