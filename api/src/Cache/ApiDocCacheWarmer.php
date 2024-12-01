<?php

namespace App\Cache;

use Nelmio\ApiDocBundle\Controller\DocumentationController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @codeCoverageIgnore
 */
readonly class ApiDocCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private DocumentationController $documentationController,
        private LoggerInterface $logger,
        private string $environment
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
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
