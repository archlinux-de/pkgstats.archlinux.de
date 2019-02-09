<?php

namespace App\Tests\EventSubscriber;

use App\Controller\PostPackageListController;
use App\EventSubscriber\RateLimitSubscriber;
use App\Repository\UserRepository;
use App\Service\ClientIdGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

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
        $this->clientIdGenerator = $this->createMock(ClientIdGenerator::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->rateLimitSubscriber = new RateLimitSubscriber(
            1,
            2,
            $this->clientIdGenerator,
            $this->userRepository
        );
    }

    public function testGetSubscribedEvents()
    {
        $events = $this->rateLimitSubscriber->getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::CONTROLLER, $events);
    }

    /**
     * @param mixed $controller
     * @dataProvider provideInvalidControllers
     */
    public function testSubscriberIsDisabledByDefault($controller)
    {
        /** @var FilterControllerEvent|MockObject $event */
        $event = $this->createMock(FilterControllerEvent::class);
        $event
            ->expects($this->once())
            ->method('getController')
            ->willReturn($controller);

        $this->userRepository->expects($this->never())->method('getSubmissionCountSince');

        $this->rateLimitSubscriber->onKernelController($event);
    }

    public function testOnKernelController()
    {
        $event = $this->createEvent();
        $this->clientIdGenerator
            ->expects($this->once())
            ->method('createClientId')
            ->with('127.0.0.1')
            ->willReturn('foo');
        $this->userRepository
            ->expects($this->once())
            ->method('getSubmissionCountSince')
            ->with('foo')
            ->willReturn(1);

        $this->rateLimitSubscriber->onKernelController($event);
    }

    /**
     * @return MockObject|FilterControllerEvent
     */
    private function createEvent(): FilterControllerEvent
    {
        /** @var Request|MockObject $request */
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('getClientIp')
            ->willReturn('127.0.0.1');

        /** @var FilterControllerEvent|MockObject $event */
        $event = $this->createMock(FilterControllerEvent::class);
        $event
            ->expects($this->once())
            ->method('getController')
            ->willReturn([$this->createMock(PostPackageListController::class)]);
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->willReturn($request);

        return $event;
    }

    public function testRateLimit()
    {
        $event = $this->createEvent();
        $this->clientIdGenerator
            ->expects($this->once())
            ->method('createClientId');
        $this->userRepository
            ->expects($this->once())
            ->method('getSubmissionCountSince')
            ->willReturn(2);

        $this->expectException(AccessDeniedHttpException::class);
        $this->rateLimitSubscriber->onKernelController($event);
    }

    /**
     * @return array
     */
    public function provideInvalidControllers(): array
    {
        return [
            [[]],
            [[new \stdClass()]],
            [
                function () {
                }
            ]
        ];
    }
}
