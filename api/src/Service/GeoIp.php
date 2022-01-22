<?php

namespace App\Service;

use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;

class GeoIp
{
    public function __construct(private Reader $reader, private LoggerInterface $logger)
    {
    }

    public function getCountryCode(string $clientIp): ?string
    {
        try {
            $response = $this->reader->get($clientIp);
            if (is_array($response) && isset($response['country']['iso_code'])) {
                return $response['country']['iso_code'];
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return null;
    }
}
