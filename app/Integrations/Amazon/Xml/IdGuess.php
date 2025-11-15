<?php

namespace App\Integrations\Amazon\Xml;

class IdGuess
{
    public static function type(?string $barcode): ?string
    {
        if(!$barcode) return null;
        $len = strlen(preg_replace('/\D/','', $barcode));
        return match($len){ 12 => 'UPC', 13 => 'EAN', 10 => 'ASIN', default => null };
    }
}
