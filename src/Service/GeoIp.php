<?php

namespace App\Service;

use MaxMind\Db\Reader;
use Psr\Log\LoggerInterface;

class GeoIp
{
    /** @var Reader */
    private $reader;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Reader $reader
     * @param LoggerInterface $logger
     */
    public function __construct(Reader $reader, LoggerInterface $logger)
    {
        $this->reader = $reader;
        $this->logger = $logger;
    }

    /**
     * @param string $clientIp
     * @return null|string
     */
    public function getCountryCode(string $clientIp): ?string
    {
        try {
            $response = $this->reader->get($clientIp);
            if (isset($response['country']['iso_code'])) {
                return $response['country']['iso_code'];
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return null;
    }
}
