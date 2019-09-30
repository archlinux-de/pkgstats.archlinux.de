<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiDocJsonCacheSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiDocJsonCacheSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents()
    {
        $events = (new ApiDocJsonCacheSubscriber())->getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testSubscriberIsDisabledForOtherRoutes()
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $response
            ->expects($this->never())
            ->method('setMaxAge');
        $response
            ->expects($this->never())
            ->method('setSharedMaxAge');

        /** @var ResponseEvent|MockObject $event */
        $event = $this->createMock(ResponseEvent::class);
        $event
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request());
        $event
            ->expects($this->never())
            ->method('getResponse')
            ->willReturn($response);

        (new ApiDocJsonCacheSubscriber())->onKernelResponse($event);
    }

    public function testSubscriberIsDisabledOnError()
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $response
            ->expects($this->never())
            ->method('setMaxAge');
        $response
            ->expects($this->never())
            ->method('setSharedMaxAge');
        $response
            ->expects($this->once())
            ->method('isOK')
            ->willReturn(false);

        /** @var ResponseEvent|MockObject $event */
        $event = $this->createMock(ResponseEvent::class);
        $event
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request([], [], ['_route' => 'app_swagger']));
        $event
            ->expects($this->once())
            ->method('getResponse')
            ->willReturn($response);

        (new ApiDocJsonCacheSubscriber())->onKernelResponse($event);
    }

    public function testSubscriberWillSetCacheHeader()
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $response
            ->expects($this->once())
            ->method('setMaxAge');
        $response
            ->expects($this->once())
            ->method('setSharedMaxAge');
        $response
            ->expects($this->once())
            ->method('isOK')
            ->willReturn(true);

        /** @var ResponseEvent|MockObject $event */
        $event = $this->createMock(ResponseEvent::class);
        $event
            ->expects($this->once())
            ->method('isMasterRequest')
            ->willReturn(true);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn(new Request([], [], ['_route' => 'app_swagger']));
        $event
            ->expects($this->atLeastOnce())
            ->method('getResponse')
            ->willReturn($response);

        (new ApiDocJsonCacheSubscriber())->onKernelResponse($event);
    }
}
