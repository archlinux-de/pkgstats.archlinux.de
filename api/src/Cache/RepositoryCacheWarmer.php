<?php

namespace App\Cache;

use App\Repository\CountryRepository;
use App\Repository\MirrorRepository;
use App\Repository\PackageRepository;
use App\Repository\SystemArchitectureRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @codeCoverageIgnore
 */
readonly class RepositoryCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private PackageRepository $packageRepository,
        private SystemArchitectureRepository $systemArchitectureRepository,
        private MirrorRepository $mirrorRepository,
        private CountryRepository $countryRepository,
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
        if ($this->environment !== 'prod') {
            return [];
        }

        try {
            $this->packageRepository->getMonthlyMaximumCountByRange(0, 0);
            $this->systemArchitectureRepository->getMonthlySumCountByRange(0, 0);
            $this->mirrorRepository->getMonthlySumCountByRange(0, 0);
            $this->countryRepository->getMonthlySumCountByRange(0, 0);

            $this->logger->info('Repository caches warmed up');
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        return [];
    }
}
