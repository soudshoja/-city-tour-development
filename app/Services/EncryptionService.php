<?php

namespace App\Services;

class EncryptionService
{   
    //generate 10 digit integer
    public function generateEncryptedNumber(): int
    {
        $timestamp = microtime(true);
        $hash = md5($timestamp);
        $numericHash = preg_replace('/[^0-9]/', '', base_convert(substr($hash, 0, 10), 16, 10));
        return (int) substr($numericHash, 0, 9); // Ensure within INT range
    }
}
