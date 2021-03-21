<?php

namespace App\Request;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @Assert\Callback("validateOperatingSystemArchitectures")
 * @Assert\Callback("validateSystemArchitectures")
 */
class PkgstatsRequest
{
    /**
     * @var string
     * @Assert\NotBlank
     * @Assert\Regex("/^(2\.[345]|3(\.[0-9]+)?)(\.[0-9]+(-[\w-]+)?)?$/")
     */
    private $version;

    /**
     * @var Package[]
     * @Assert\Valid
     * @Assert\Count(min=1, max=10000)
     */
    private $packages = [];

    /**
     * @var Country|null
     * @Assert\Valid
     */
    private $country;

    /**
     * @var Mirror|null
     * @Assert\Valid
     */
    private $mirror;

    /**
     * @var OperatingSystemArchitecture
     * @Assert\NotBlank
     * @Assert\Valid
     */
    private $operatingSystemArchitecture;

    /**
     * @var SystemArchitecture
     * @Assert\NotBlank
     * @Assert\Valid
     */
    private $systemArchitecture;

    /**
     * @param string $version
     */
    public function __construct(string $version)
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return Package[]
     */
    public function getPackages(): array
    {
        return $this->packages;
    }

    /**
     * @param Package $package
     * @return PkgstatsRequest
     */
    public function addPackage(Package $package): PkgstatsRequest
    {
        $this->packages[] = $package;
        return $this;
    }

    /**
     * @return null|Country
     */
    public function getCountry(): ?Country
    {
        return $this->country;
    }

    /**
     * @param null|Country $country
     * @return PkgstatsRequest
     */
    public function setCountry(?Country $country): PkgstatsRequest
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return Mirror|null
     */
    public function getMirror(): ?Mirror
    {
        return $this->mirror;
    }

    /**
     * @param Mirror|null $mirror
     * @return PkgstatsRequest
     */
    public function setMirror(?Mirror $mirror): PkgstatsRequest
    {
        $this->mirror = $mirror;
        return $this;
    }

    /**
     * @return OperatingSystemArchitecture
     */
    public function getOperatingSystemArchitecture(): OperatingSystemArchitecture
    {
        return $this->operatingSystemArchitecture;
    }

    /**
     * @param OperatingSystemArchitecture $operatingSystemArchitecture
     * @return PkgstatsRequest
     */
    public function setOperatingSystemArchitecture(
        OperatingSystemArchitecture $operatingSystemArchitecture
    ): PkgstatsRequest {
        $this->operatingSystemArchitecture = $operatingSystemArchitecture;
        return $this;
    }

    /**
     * @return SystemArchitecture
     */
    public function getSystemArchitecture(): SystemArchitecture
    {
        return $this->systemArchitecture;
    }

    /**
     * @param SystemArchitecture $systemArchitecture
     * @return PkgstatsRequest
     */
    public function setSystemArchitecture(SystemArchitecture $systemArchitecture): PkgstatsRequest
    {
        $this->systemArchitecture = $systemArchitecture;
        return $this;
    }

    /**
     * @param ExecutionContextInterface $context
     */
    public function validateOperatingSystemArchitectures(ExecutionContextInterface $context): void
    {
        switch ($this->getSystemArchitecture()->getName()) {
            case 'x86_64': // @codeCoverageIgnore
            case 'x86_64_v2': // @codeCoverageIgnore
            case 'x86_64_v3': // @codeCoverageIgnore
            case 'x86_64_v4': // @codeCoverageIgnore
                $validArchitectures = ['x86_64', 'i686'];
                break;
            case 'i686': // @codeCoverageIgnore
                $validArchitectures = ['i686'];
                break;
            case 'aarch64': // @codeCoverageIgnore
                $validArchitectures = ['aarch64', 'armv7h', 'armv6h', 'arm'];
                break;
            case 'armv7': // @codeCoverageIgnore
                $validArchitectures = ['armv7h', 'armv6h', 'arm'];
                break;
            case 'armv6': // @codeCoverageIgnore
                $validArchitectures = ['armv6h', 'arm'];
                break;
            case 'armv5': // @codeCoverageIgnore
                $validArchitectures = ['arm'];
                break;
            default:
                $validArchitectures = [];
        }

        if (!in_array($this->getOperatingSystemArchitecture(), $validArchitectures)) {
            $context->buildViolation('Invalid Operating System Architecture')
                ->atPath('operatingSystemArchitecture')
                ->addViolation();
        }
    }

    /**
     * @param ExecutionContextInterface $context
     */
    public function validateSystemArchitectures(ExecutionContextInterface $context): void
    {
        switch ($this->getOperatingSystemArchitecture()->getName()) {
            case 'x86_64': // @codeCoverageIgnore
                $validSystemArchitectures = ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'];
                break;
            case 'i686': // @codeCoverageIgnore
                $validSystemArchitectures = ['i686', 'x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'];
                break;
            case 'aarch64': // @codeCoverageIgnore
                $validSystemArchitectures = ['aarch64'];
                break;
            case 'armv6h': // @codeCoverageIgnore
                $validSystemArchitectures = ['armv6', 'armv7', 'aarch64'];
                break;
            case 'armv7h': // @codeCoverageIgnore
                $validSystemArchitectures = ['armv7', 'aarch64'];
                break;
            case 'arm': // @codeCoverageIgnore
                $validSystemArchitectures = ['armv5', 'armv6', 'armv7', 'aarch64'];
                break;
            default:
                $validSystemArchitectures = [];
        }

        if (!in_array($this->getSystemArchitecture(), $validSystemArchitectures)) {
            $context->buildViolation('Invalid System Architecture')
                ->atPath('systemArchitecture')
                ->addViolation();
        }
    }
}
