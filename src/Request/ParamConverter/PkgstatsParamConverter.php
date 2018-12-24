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
        $packages = $this->filterList($request->request->get('packages', ''));
        $modules = $this->filterList($request->request->get('modules', ''));
        $arch = $request->request->get('arch');
        $cpuArch = $request->request->get('cpuarch', $arch);
        $mirror = $this->filterUrl($request->request->get('mirror', ''));
        $quiet = $request->request->get('quiet') == 'true';

        $clientIp = $request->getClientIp();
        $user = (new User())
            ->setIp($this->clientIdGenerator->createClientId($clientIp))
            ->setTime(time())
            ->setArch($arch)
            ->setCpuarch($cpuArch)
            ->setCountrycode($this->geoIp->getCountryCode($clientIp))
            ->setMirror($mirror)
            ->setPackages(count($packages))
            ->setModules(count($modules));

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
     * @param string $string
     * @return array
     */
    private function filterList(string $string): array
    {
        return array_filter(array_unique(explode("\n", trim($string))));
    }

    /**
     * @param string $url
     * @return string|null
     */
    private function filterUrl(string $url): ?string
    {
        if (!empty($url) && !preg_match('#^(?:https?|ftp)://\S+#', $url)) {
            $url = null;
        } elseif (empty($url)) {
            $url = null;
        }
        return $url;
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
