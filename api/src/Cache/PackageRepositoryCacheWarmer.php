<?php

namespace App\Cache;

use App\Entity\Month;
use App\Repository\PackageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @codeCoverageIgnore
 */
readonly class PackageRepositoryCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private PackageRepository $packageRepository,
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
    public function warmUp(string $cacheDir, string $buildDir = null): array
    {
        if ($this->environment != 'prod') {
            return [];
        }

        try {
            $defaultMonth = Month::create()->getYearMonth();

            $this->packageRepository->getMonthlyMaximumCountByRange(0, $defaultMonth);
            $this->packageRepository->getMaximumCountByRange($defaultMonth, $defaultMonth);

            $this->logger->info('Package repository cache warmed up');
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        return [];
    }
}
