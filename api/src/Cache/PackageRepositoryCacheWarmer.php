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
    /** @var PackageRepository */
    private $packageRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $environment;

    /**
     * @param PackageRepository $packageRepository
     * @param string $environment
     * @param LoggerInterface $logger
     */
    public function __construct(
        PackageRepository $packageRepository,
        LoggerInterface $logger,
        string $environment
    ) {
        $this->packageRepository = $packageRepository;
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
            $defaultMonth = (int)date(
                'Ym',
                (int)strtotime(
                    date(
                        '1-m-Y',
                        (int)strtotime('now -1 months')
                    )
                )
            );

            $this->packageRepository->getMonthlyMaximumCountByRange($defaultMonth, $defaultMonth);
            $this->packageRepository->getMaximumCountByRange($defaultMonth, $defaultMonth);
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }

        return [];
    }
}
