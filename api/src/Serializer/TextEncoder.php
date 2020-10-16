<?php

namespace App\Serializer;

use Symfony\Component\Serializer\Encoder\EncoderInterface;

class TextEncoder implements EncoderInterface
{
    public function encode($data, $format, array $context = [])
    {
        if (!is_array($data)) {
            return print_r($data, true);
        }

        $result = '';
        if (isset($data['trace']) && isset($context['exception']) && $context['exception'] instanceof \Exception) {
            $data['message'] =  $context['exception']->getMessage();
            $data['trace'] = "\n" . $context['exception']->getTraceAsString();
        }
        ksort($data);
        foreach ($data as $key => $value) {
            $result .= $key . ": " . print_r($value, true) . "\n";
        }
        return $result;
    }

    public function supportsEncoding($format)
    {
        return 'text' === $format;
    }
}
