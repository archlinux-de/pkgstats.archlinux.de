<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\IpUtils;

class ClientIdGenerator
{
    /**
     * @param string $ip
     * @return string
     */
    public function createClientId(string $ip): string
    {
        return sha1(IpUtils::anonymize($ip));
    }
}
