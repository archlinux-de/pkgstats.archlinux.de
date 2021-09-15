<?php

namespace App\Request;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validateOperatingSystemArchitectures')]
#[Assert\Callback('validateSystemArchitectures')]
class PkgstatsRequest
{
    #[Assert\NotBlank]
    #[Assert\Regex('/^(2\.[345]|3(\.[0-9]+)?)(\.[0-9]+(-[\w-]+)?)?$/')]
    private string $version;

    /**
     * @var Package[]
     */
    #[Assert\Valid]
    #[Assert\Count(min:1, max:10000)]
    private array $packages = [];

    #[Assert\Valid]
    private ?Country $country = null;

    #[Assert\Valid]
    private ?Mirror $mirror = null;

    #[Assert\NotBlank]
    #[Assert\Valid]
    private OperatingSystemArchitecture $operatingSystemArchitecture;

    #[Assert\NotBlank]
    #[Assert\Valid]
    private SystemArchitecture $systemArchitecture;

    public function __construct(string $version)
    {
        $this->version = $version;
    }

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

    public function addPackage(Package $package): PkgstatsRequest
    {
        $this->packages[] = $package;
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): PkgstatsRequest
    {
        $this->country = $country;
        return $this;
    }

    public function getMirror(): ?Mirror
    {
        return $this->mirror;
    }

    public function setMirror(?Mirror $mirror): PkgstatsRequest
    {
        $this->mirror = $mirror;
        return $this;
    }

    public function getOperatingSystemArchitecture(): OperatingSystemArchitecture
    {
        return $this->operatingSystemArchitecture;
    }

    public function setOperatingSystemArchitecture(
        OperatingSystemArchitecture $operatingSystemArchitecture
    ): PkgstatsRequest {
        $this->operatingSystemArchitecture = $operatingSystemArchitecture;
        return $this;
    }

    public function getSystemArchitecture(): SystemArchitecture
    {
        return $this->systemArchitecture;
    }

    public function setSystemArchitecture(SystemArchitecture $systemArchitecture): PkgstatsRequest
    {
        $this->systemArchitecture = $systemArchitecture;
        return $this;
    }

    public function validateOperatingSystemArchitectures(ExecutionContextInterface $context): void
    {
        $validArchitectures = match ($this->getSystemArchitecture()->getName()) {
            'x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4' => ['x86_64', 'i686'],
            'i686' => ['i686'],
            'aarch64' => ['aarch64', 'armv7h', 'armv6h', 'arm'],
            'armv7' => ['armv7h', 'armv6h', 'arm'],
            'armv6' => ['armv6h', 'arm'],
            'armv5' => ['arm'],
            default => [],
        };

        if (!in_array($this->getOperatingSystemArchitecture(), $validArchitectures)) {
            $context->buildViolation('Invalid Operating System Architecture')
                ->atPath('operatingSystemArchitecture')
                ->addViolation();
        }
    }

    public function validateSystemArchitectures(ExecutionContextInterface $context): void
    {
        $validSystemArchitectures = match ($this->getOperatingSystemArchitecture()->getName()) {
            'x86_64' => ['x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'],
            'i686' => ['i686', 'x86_64', 'x86_64_v2', 'x86_64_v3', 'x86_64_v4'],
            'aarch64' => ['aarch64'],
            'armv6h' => ['armv6', 'armv7', 'aarch64'],
            'armv7h' => ['armv7', 'aarch64'],
            'arm' => ['armv5', 'armv6', 'armv7', 'aarch64'],
            default => [],
        };

        if (!in_array($this->getSystemArchitecture(), $validSystemArchitectures)) {
            $context->buildViolation('Invalid System Architecture')
                ->atPath('systemArchitecture')
                ->addViolation();
        }
    }
}
