<?php

namespace App\Tests\Cache;

use App\Cache\ApiDocCacheWarmer;
use Nelmio\ApiDocBundle\Controller\SwaggerUiController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiDocCacheWarmerTest extends KernelTestCase
{
    /** @var ApiDocCacheWarmer */
    private $apiDocCacheWarmer;

    /** @var SwaggerUiController */
    private $swaggerUiController;

    public function setUp(): void
    {
        static::bootKernel();
        $this->swaggerUiController = static::$container->get(SwaggerUiController::class);
        $this->apiDocCacheWarmer = new ApiDocCacheWarmer($this->swaggerUiController);
    }

    public function testWarmUp()
    {
        $this->assertNull($this->apiDocCacheWarmer->warmUp(''));
    }

    public function testIsOptional()
    {
        $this->assertTrue($this->apiDocCacheWarmer->isOptional());
    }
}
