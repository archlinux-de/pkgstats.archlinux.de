<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ApiCorsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class ApiCorsSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = new ApiCorsSubscriber()->getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testSubscriberIsOnlyEnabledOnMasterRequests(): void
    {
        $response = $this->createMock(Response::class);
        $response->headers = new ResponseHeaderBag();

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->never())
            ->method('isMethod');

        $event = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        new ApiCorsSubscriber()->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testSubscriberIsOnlyEnabledOnGetRequests(): void
    {
        $response = $this->createMock(Response::class);
        $response->headers = new ResponseHeaderBag();

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('isMethod')
            ->with(Request::METHOD_GET)
            ->willReturn(false);
        $request
            ->expects($this->never())
            ->method('getPathInfo')
            ->willReturn('/api/foo');

        $event = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        new ApiCorsSubscriber()->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    public function testCorsHeadersAreSetForApiRequests(): void
    {
        $response = $this->createMock(Response::class);
        $response->headers = new ResponseHeaderBag();

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('isMethod')
            ->with(Request::METHOD_GET)
            ->willReturn(true);
        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/foo');

        $event = new ResponseEvent(
            $this->createMock(KernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        new ApiCorsSubscriber()->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
    }
}
