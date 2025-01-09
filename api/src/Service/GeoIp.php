<?php

namespace App\Service;

use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;

readonly class GeoIp implements GeoIpInterface
{
    public function __construct(private Reader $reader, private LoggerInterface $logger)
    {
    }

    public function getCountryCode(string $clientIp): ?string
    {
        try {
            $response = $this->reader->get($clientIp);
            if (is_array($response) && isset($response['country']) && is_array($response['country']) && is_string($response['country']['iso_code'])) {
                return $response['country']['iso_code'];
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return null;
    }
}
