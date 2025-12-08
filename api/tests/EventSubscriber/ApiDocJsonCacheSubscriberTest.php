<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiDocJsonCacheSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class ApiDocJsonCacheSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = new ApiDocJsonCacheSubscriber('prod')->getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testSubscriberIsDisabledForOtherRoutes(): void
    {
        /** @var Response|MockObject $response */
        $response = $this->createMock(Response::class);
        $response
            ->expects($this->never())
            ->method('setMaxAge');
        $response
            ->expects($this->never())
            ->method('setSharedMaxAge');

        $event = $this->createEvent(new Request(), $response);

        new ApiDocJsonCacheSubscriber('prod')->onKernelResponse($event);
    }

    private function createEvent(Request $request, Response $response): ResponseEvent
    {
        /** @var KernelInterface|MockObject $kernel */
        $kernel = $this->createStub(KernelInterface::class);

        return new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
    }

    public function testSubscriberIsDisabledOnError(): void
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

        $event = $this->createEvent(new Request([], [], ['_route' => 'app_swagger']), $response);

        new ApiDocJsonCacheSubscriber('prod')->onKernelResponse($event);
    }

    public function testSubscriberWillSetCacheHeader(): void
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

        $event = $this->createEvent(new Request([], [], ['_route' => 'app_swagger']), $response);

        new ApiDocJsonCacheSubscriber('prod')->onKernelResponse($event);
    }
}
