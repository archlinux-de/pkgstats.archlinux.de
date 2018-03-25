<?php

namespace App\Service;

use geertw\IpAnonymizer\IpAnonymizer;

class ClientIdGenerator
{
    /** @var IpAnonymizer */
    private $ipAnonymizer;

    /**
     * @param IpAnonymizer $ipAnonymizer
     */
    public function __construct(IpAnonymizer $ipAnonymizer)
    {
        $this->ipAnonymizer = $ipAnonymizer;
    }

    /**
     * @param string $ip
     * @return string
     */
    public function createClientId(string $ip): string
    {
        return sha1($this->ipAnonymizer->anonymize($ip));
    }
}
