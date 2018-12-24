<?php

namespace App\Request\ParamConverter;

use App\Entity\Module;
use App\Entity\Package;
use App\Entity\User;
use App\Request\PkgstatsRequest;
use App\Service\ClientIdGenerator;
use App\Service\GeoIp;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PkgstatsParamConverter implements ParamConverterInterface
{
    /** @var GeoIp */
    private $geoIp;
    /** @var ClientIdGenerator */
    private $clientIdGenerator;

    /**
     * @param GeoIp $geoIp
     * @param ClientIdGenerator $clientIdGenerator
     */
    public function __construct(GeoIp $geoIp, ClientIdGenerator $clientIdGenerator)
    {
        $this->geoIp = $geoIp;
        $this->clientIdGenerator = $clientIdGenerator;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $pkgstatsver = str_replace('pkgstats/', '', $request->server->get('HTTP_USER_AGENT'));

        if (!in_array($pkgstatsver, ['2.3'])) {
            throw new BadRequestHttpException('Sorry, your version of pkgstats is not supported.');
        }

        $packages = array_unique(explode("\n", trim($request->request->get('packages'))));
        $packages = array_filter($packages);
        $packageCount = count($packages);

        $modules = array_unique(explode("\n", trim($request->request->get('modules'))));
        $modules = array_filter($modules);
        $moduleCount = count($modules);

        $arch = $request->request->get('arch');
        $cpuArch = $request->request->get('cpuarch', $arch);
        $mirror = $request->request->get('mirror', '');
        $quiet = $request->request->get('quiet') == 'true';

        if (!empty($mirror) && !preg_match('#^(?:https?|ftp)://\S+#', $mirror)) {
            $mirror = null;
        } elseif (!empty($mirror) && strlen($mirror) > 255) {
            throw new BadRequestHttpException($mirror . ' is too long.');
        } elseif (empty($mirror)) {
            $mirror = null;
        }

        if (!in_array($arch, ['x86_64'])) {
            throw new BadRequestHttpException($arch . ' is not a known architecture.');
        }
        if (!in_array($cpuArch, ['x86_64'])) {
            throw new BadRequestHttpException($cpuArch . ' is not a known architecture.');
        }

        if ($packageCount == 0) {
            throw new BadRequestHttpException('Your package list is empty.');
        }
        if ($packageCount > 10000) {
            throw new BadRequestHttpException('So, you have installed more than 10,000 packages?');
        }
        foreach ($packages as $package) {
            if (strlen($package) > 255 || !preg_match('/^[^-]+\S*$/', $package)) {
                throw new BadRequestHttpException($package . ' does not look like a valid package');
            }
        }

        if ($moduleCount > 5000) {
            throw new BadRequestHttpException('So, you have loaded more than 5,000 modules?');
        }
        foreach ($modules as $module) {
            if (strlen($module) > 255 || !preg_match('/^[\w\-]+$/', $module)) {
                throw new BadRequestHttpException($module . ' does not look like a valid module');
            }
        }

        $clientIp = $request->getClientIp();
        $countryCode = $this->geoIp->getCountryCode($clientIp);
        if (empty($countryCode)) {
            $countryCode = null;
        }

        $user = (new User())
            ->setIp($this->clientIdGenerator->createClientId($clientIp))
            ->setTime(time())
            ->setArch($arch)
            ->setCpuarch($cpuArch)
            ->setCountrycode($countryCode)
            ->setMirror($mirror)
            ->setPackages($packageCount)
            ->setModules($moduleCount);

        $pkgstatsRequest = new PkgstatsRequest($pkgstatsver, $user);
        $pkgstatsRequest->setQuiet($quiet);

        foreach ($packages as $package) {
            $pkgstatsRequest->addPackage(
                (new Package())
                    ->setPkgname($package)
                    ->setMonth(date('Ym', $user->getTime()))
            );
        }

        foreach ($modules as $module) {
            $pkgstatsRequest->addModule(
                (new Module())
                    ->setName($module)
                    ->setMonth(date('Ym', $user->getTime()))
            );
        }

        $request->attributes->set(
            $configuration->getName(),
            $pkgstatsRequest
        );

        return true;
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
