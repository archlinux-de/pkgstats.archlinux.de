<?php

namespace App\Cache;

use Nelmio\ApiDocBundle\Controller\DocumentationController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @codeCoverageIgnore
 */
class ApiDocCacheWarmer implements CacheWarmerInterface
{
    /** @var DocumentationController */
    private $documentationController;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $environment;

    /**
     * @param DocumentationController $documentationController
     * @param LoggerInterface $logger
     * @param string $environment
     */
    public function __construct(
        DocumentationController $documentationController,
        LoggerInterface $logger,
        string $environment
    ) {
        $this->documentationController = $documentationController;
        $this->logger = $logger;
        $this->environment = $environment;
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
     * @return string[]
     */
    public function warmUp($cacheDir): array
    {
        if ($this->environment != 'prod') {
            return [];
        }

        try {
            $this->documentationController->__invoke(Request::createFromGlobals());

            $this->logger->info('API Doc cache warmed up');
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        return [];
    }
}
