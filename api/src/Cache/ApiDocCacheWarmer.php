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

    /**
     * @param DocumentationController $documentationController
     * @param LoggerInterface $logger
     */
    public function __construct(DocumentationController $documentationController, LoggerInterface $logger)
    {
        $this->documentationController = $documentationController;
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
     * @return string[]
     */
    public function warmUp($cacheDir): array
    {
        try {
            $this->documentationController->__invoke(Request::createFromGlobals());
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
        return [];
    }
}
