<?php

namespace App\Serializer;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Request\PkgstatsRequest;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PkgstatsRequestV2Denormalizer implements DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function __construct(private GeoIp $geoIp, private MirrorUrlFilter $mirrorUrlFilter)
    {
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): PkgstatsRequest
    {
        $packages = $this->filterList((string)($data['packages'] ?? ''));
        $arch = (string)($data['arch'] ?? '');
        $cpuArch = $data['cpuarch'] ?? $arch;
        $mirror = $this->mirrorUrlFilter->filter((string)($data['mirror'] ?? ''));

        $clientIp = $context['clientIp'] ?? '127.0.0.1';

        $pkgstatsver = str_replace('pkgstats/', '', $context['userAgent'] ?? '');
        $pkgstatsRequest = new PkgstatsRequest($pkgstatsver);
        $pkgstatsRequest->setOperatingSystemArchitecture(
            (new OperatingSystemArchitecture($arch))->setMonth((int)date('Ym'))
        );
        $pkgstatsRequest->setSystemArchitecture((new SystemArchitecture($cpuArch))->setMonth((int)date('Ym')));

        if ($mirror) {
            $pkgstatsRequest->setMirror((new Mirror($mirror))->setMonth((int)date('Ym')));
        }

        $countryCode = $this->geoIp->getCountryCode($clientIp);
        if ($countryCode) {
            $pkgstatsRequest->setCountry((new Country($countryCode))->setMonth((int)date('Ym')));
        }

        foreach ($packages as $package) {
            $pkgstatsRequest->addPackage(
                (new Package())
                    ->setName($package)
                    ->setMonth((int)date('Ym'))
            );
        }

        return $pkgstatsRequest;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null): bool
    {
        return $type === PkgstatsRequest::class && $format === 'form';
    }

    /**
     * @return string[]
     */
    private function filterList(string $string): array
    {
        return array_filter(array_unique(explode("\n", trim($string))));
    }
}
