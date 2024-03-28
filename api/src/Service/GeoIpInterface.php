<?php

namespace App\Service;

interface GeoIpInterface
{
    public function getCountryCode(string $clientIp): ?string;
}
