<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\PkgstatsRequestRateLimitSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class PkgstatsRequestRateLimitSubscriberTest extends TestCase
{
    private PkgstatsRequestRateLimitSubscriber $rateLimitSubscriber;

    public function setUp(): void
    {
        $pkgstatsRequestLimiter = new RateLimiterFactory(
            ['id' => 'pkgstats_request', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 day'],
            new InMemoryStorage()
        );

        $this->rateLimitSubscriber = new PkgstatsRequestRateLimitSubscriber($pkgstatsRequestLimiter);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = $this->rateLimitSubscriber->getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    private function createEvent(string $route, InvocationOrder $ipRequests): RequestEvent
    {
        /** @var KernelInterface|MockObject $kernel */
        $kernel = $this->createStub(KernelInterface::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $request
            ->expects($ipRequests)
            ->method('getClientIp')
            ->willReturn('127.0.0.1');
        $request->attributes = new ParameterBag(['_route' => $route]);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testRateLimit(): void
    {
        $event = $this->createEvent('app_api_submit', $this->atLeastOnce());
        $this->rateLimitSubscriber->onKernelRequest($event);
        $this->expectException(TooManyRequestsHttpException::class);
        $this->rateLimitSubscriber->onKernelRequest($event);
    }

    public function testRateLimitDoesNotApplyToOtherRoutes(): void
    {
        $event = $this->createEvent('foo', $this->never());
        $this->rateLimitSubscriber->onKernelRequest($event);
        $this->rateLimitSubscriber->onKernelRequest($event);
        // Asserting that no exception was thrown
        // @phpstan-ignore-next-line
        $this->assertTrue(true);
    }
}
