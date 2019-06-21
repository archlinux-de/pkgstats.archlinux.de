<?php

namespace App\Cache;

use Nelmio\ApiDocBundle\Controller\SwaggerUiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ApiDocCacheWarmer implements CacheWarmerInterface
{
    /** @var SwaggerUiController */
    private $swaggerUiController;

    /**
     * @param SwaggerUiController $swaggerUiController
     */
    public function __construct(SwaggerUiController $swaggerUiController)
    {
        $this->swaggerUiController = $swaggerUiController;
    }

    /**
     * @return bool
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * @param string $cacheDir
     */
    public function warmUp($cacheDir)
    {
        $this->swaggerUiController->__invoke(Request::createFromGlobals());
    }
}
