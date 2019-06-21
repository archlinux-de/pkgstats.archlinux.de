<?php

namespace App\Cache;

use Nelmio\ApiDocBundle\Controller\SwaggerUiController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @codeCoverageIgnore
 */
class ApiDocCacheWarmer implements CacheWarmerInterface
{
    /** @var SwaggerUiController */
    private $swaggerUiController;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param SwaggerUiController $swaggerUiController
     * @param LoggerInterface $logger
     */
    public function __construct(SwaggerUiController $swaggerUiController, LoggerInterface $logger)
    {
        $this->swaggerUiController = $swaggerUiController;
        $this->logger = $logger;
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
        try {
            $this->swaggerUiController->__invoke(Request::createFromGlobals());
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }
}
