<?php

namespace App\Cache;

use App\Repository\PackageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @codeCoverageIgnore
 */
class PackageRepositoryCacheWarmer implements CacheWarmerInterface
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
    public function warmUp(string $cacheDir): array
    {
        if ($this->environment != 'prod') {
            return [];
        }

        try {
            $defaultMonth = (int)date(
                'Ym',
                (int)strtotime(
                    date(
                        '1-m-Y',
                        (int)strtotime('first day of this month -1 months')
                    )
                )
            );

            $this->packageRepository->getMonthlyMaximumCountByRange(0, $defaultMonth);
            $this->packageRepository->getMaximumCountByRange($defaultMonth, $defaultMonth);

            $this->logger->info('Package repository cache warmed up');
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        return [];
    }
}
