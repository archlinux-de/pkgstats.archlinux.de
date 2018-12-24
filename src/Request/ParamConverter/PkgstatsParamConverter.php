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
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverter implements ParamConverterInterface
{
    /** @var GeoIp */
    private $geoIp;
    /** @var ClientIdGenerator */
    private $clientIdGenerator;
    /** @var ValidatorInterface */
    private $validator;

    /**
     * @param GeoIp $geoIp
     * @param ClientIdGenerator $clientIdGenerator
     * @param ValidatorInterface $validator
     */
    public function __construct(GeoIp $geoIp, ClientIdGenerator $clientIdGenerator, ValidatorInterface $validator)
    {
        $this->geoIp = $geoIp;
        $this->clientIdGenerator = $clientIdGenerator;
        $this->validator = $validator;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $pkgstatsver = str_replace('pkgstats/', '', $request->server->get('HTTP_USER_AGENT'));

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
        } elseif (empty($mirror)) {
            $mirror = null;
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

        $errors = $this->validator->validate($pkgstatsRequest);
        if ($errors->count() > 0) {
            throw new BadRequestHttpException((string)$errors);
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
