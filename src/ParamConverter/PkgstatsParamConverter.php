<?php

namespace App\ParamConverter;

use App\Entity\Package;
use App\Entity\User;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use App\Service\ClientIdGenerator;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverter implements ParamConverterInterface
{
    /** @var GeoIp */
    private $geoIp;

    /** @var ClientIdGenerator */
    private $clientIdGenerator;

    /** @var ValidatorInterface */
    private $validator;

    /** @var MirrorUrlFilter */
    private $mirrorUrlFilter;

    /**
     * @param GeoIp $geoIp
     * @param ClientIdGenerator $clientIdGenerator
     * @param ValidatorInterface $validator
     * @param MirrorUrlFilter $mirrorUrlFilter
     */
    public function __construct(
        GeoIp $geoIp,
        ClientIdGenerator $clientIdGenerator,
        ValidatorInterface $validator,
        MirrorUrlFilter $mirrorUrlFilter
    ) {
        $this->geoIp = $geoIp;
        $this->clientIdGenerator = $clientIdGenerator;
        $this->validator = $validator;
        $this->mirrorUrlFilter = $mirrorUrlFilter;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $pkgstatsver = str_replace('pkgstats/', '', $request->server->get('HTTP_USER_AGENT', ''));
        $packages = $this->filterList($request->request->get('packages', ''));
        $arch = $request->request->get('arch', '');
        $cpuArch = $request->request->get('cpuarch', $arch);
        $mirror = $this->mirrorUrlFilter->filter($request->request->get('mirror', ''));
        $quiet = $request->request->get('quiet') == 'true';

        $clientIp = $request->getClientIp() ?? '127.0.0.1';
        $user = (new User())
            ->setIp($this->clientIdGenerator->createClientId($clientIp))
            ->setTime(time())
            ->setArch($arch)
            ->setCpuarch($cpuArch)
            ->setCountrycode($this->geoIp->getCountryCode($clientIp))
            ->setMirror($mirror)
            ->setPackages(count($packages));

        $pkgstatsRequest = new PkgstatsRequest($pkgstatsver, $user);
        $pkgstatsRequest->setQuiet($quiet);

        foreach ($packages as $package) {
            $pkgstatsRequest->addPackage(
                (new Package())
                    ->setName($package)
                    ->setMonth((int)date('Ym', $user->getTime()))
            );
        }

        $errors = $this->validator->validate($pkgstatsRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        $request->attributes->set(
            $configuration->getName(),
            $pkgstatsRequest
        );

        return true;
    }

    /**
     * @param string $string
     * @return string[]
     */
    private function filterList(string $string): array
    {
        return array_filter(array_unique(explode("\n", trim($string))));
    }

    /**
     * @param ParamConverter $configuration
     * @return bool
     */
    public function supports(ParamConverter $configuration)
    {
        return $configuration->getClass() == PkgstatsRequest::class;
    }
}
