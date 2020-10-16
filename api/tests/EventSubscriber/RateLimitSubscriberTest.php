<?php

namespace App\Tests\EventSubscriber;

use App\Controller\PostPackageListController;
use App\Entity\User;
use App\EventSubscriber\RateLimitSubscriber;
use App\Repository\UserRepository;
use App\Request\PkgstatsRequest;
use App\Service\ClientIdGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class RateLimitSubscriberTest extends TestCase
{
    /** @var RateLimitSubscriber */
    private $rateLimitSubscriber;

    /** @var ClientIdGenerator|MockObject */
    private $clientIdGenerator;

    /** @var UserRepository|MockObject */
    private $userRepository;

    public function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->rateLimitSubscriber = new RateLimitSubscriber(1, 2, $this->userRepository);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = $this->rateLimitSubscriber->getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::CONTROLLER, $events);
    }

    /**
     * @param mixed $controller
     * @dataProvider provideInvalidControllers
     */
    public function testSubscriberIsDisabledByDefault($controller): void
    {
        /** @var KernelInterface|MockObject $kernel */
        $kernel = $this->createMock(KernelInterface::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);

        $this->userRepository->expects($this->never())->method('getSubmissionCountSince');

        $this->rateLimitSubscriber->onKernelController(
            new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MASTER_REQUEST)
        );
    }

    public function testOnKernelController(): void
    {
        $event = $this->createEvent();
        $this->userRepository
            ->expects($this->once())
            ->method('getSubmissionCountSince')
            ->with('abc')
            ->willReturn(1);

        $this->rateLimitSubscriber->onKernelController($event);
    }

    /**
     * @return ControllerEvent
     */
    private function createEvent(): ControllerEvent
    {
        /** @var KernelInterface|MockObject $kernel */
        $kernel = $this->createMock(KernelInterface::class);

        /** @var PostPackageListController|MockObject $controller */
        $controller = $this->createMock(PostPackageListController::class);

        /** @var User|MockObject $user */
        $user = $this->createMock(User::class);
        $user
            ->expects($this->once())
            ->method('getIp')
            ->willReturn('abc');

        $pkgStatsRequest = $this->createMock(PkgstatsRequest::class);
        $pkgStatsRequest
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->any())
            ->method('getClientIp')
            ->willReturn('127.0.0.1');
        $request->attributes = new ParameterBag(['pkgstatsRequest' => $pkgStatsRequest]);

        return new ControllerEvent(
            $kernel,
            [$controller, 'postAction'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );
    }

    public function testRateLimit(): void
    {
        $event = $this->createEvent();
        $this->userRepository
            ->expects($this->once())
            ->method('getSubmissionCountSince')
            ->willReturn(2);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->rateLimitSubscriber->onKernelController($event);
    }

    public function testRateLimitFailsWithUnsupportedRequest(): void
    {
        /** @var KernelInterface|MockObject $kernel */
        $kernel = $this->createMock(KernelInterface::class);

        /** @var PostPackageListController|MockObject $controller */
        $controller = $this->createMock(PostPackageListController::class);

        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag(
            [
                'pkgstatsRequest' => new class {
                }
            ]
        );

        $event = new ControllerEvent(
            $kernel,
            [$controller, 'postAction'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $this->expectException(\RuntimeException::class);
        $this->rateLimitSubscriber->onKernelController($event);
    }

    /**
     * @return array
     */
    public function provideInvalidControllers(): array
    {
        return [
            [[$this->createMock(\stdClass::class), 'expects']],
            [fn() => null]
        ];
    }
}
