<?php

namespace App\Serializer;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

class TextEncoder implements EncoderInterface
{
    public function encode(mixed $data, $format, array $context = []): string
    {
        if (!is_array($data)) {
            return (string)print_r($data, true);
        }

        $result = '';
        if (isset($data['trace']) && isset($context['exception']) && $context['exception'] instanceof \Exception) {
            $data['message'] = $context['exception']->getMessage();
            $data['trace'] = "\n" . $context['exception']->getTraceAsString();
        }
        ksort($data);
        foreach ($data as $key => $value) {
            $result .= $key . ': ' . print_r($value, true) . "\n";
        }
        return $result;
    }

    public function supportsEncoding(string $format): bool
    {
        return 'text' === $format;
    }
}
