<?php

namespace App\Serializer;

use App\Entity\Package;
use App\Entity\User;
use App\Request\PkgstatsRequest;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PkgstatsRequestV3Denormalizer implements DenormalizerInterface, CacheableSupportsMethodInterface
{
    /** @var GeoIp */
    private $geoIp;

    /** @var MirrorUrlFilter */
    private $mirrorUrlFilter;

    /**
     * @param GeoIp $geoIp
     * @param MirrorUrlFilter $mirrorUrlFilter
     */
    public function __construct(GeoIp $geoIp, MirrorUrlFilter $mirrorUrlFilter)
    {
        $this->geoIp = $geoIp;
        $this->mirrorUrlFilter = $mirrorUrlFilter;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $packages = $this->filterList($data['pacman']['packages'] ?? []);
        $arch = ($data['os']['architecture'] ?? '');
        $cpuArch = $data['system']['architecture'] ?? '';
        $mirror = $this->mirrorUrlFilter->filter(($data['pacman']['mirror'] ?? ''));

        $clientIp = $context['clientIp'] ?? '127.0.0.1';
        $user = (new User())
            ->setTime(time())
            ->setArch($arch)
            ->setCpuarch($cpuArch)
            ->setCountrycode($this->geoIp->getCountryCode($clientIp))
            ->setMirror($mirror)
            ->setPackages(count($packages));

        $pkgstatsver = $data['version'] ?? '';
        $pkgstatsRequest = new PkgstatsRequest($pkgstatsver, $user);

        foreach ($packages as $package) {
            $pkgstatsRequest->addPackage(
                (new Package())
                    ->setName($package)
                    ->setMonth((int)date('Ym', $user->getTime()))
            );
        }

        return $pkgstatsRequest;
    }

    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return $type === PkgstatsRequest::class && $format === 'json';
    }

    /**
     * @param string[] $array
     * @return string[]
     */
    private function filterList(array $array): array
    {
        return array_filter(array_unique($array));
    }
}
