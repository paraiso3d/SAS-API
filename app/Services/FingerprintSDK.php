<?php

namespace App\Services;

class FingerprintSDK
{
    /**
     * Convert raw scan to a stored template
     */
    public static function createTemplate(string $rawScan): string
    {
        // For now just return the raw scan
        // Later, you can integrate the actual DigitalPersona SDK logic
        return base64_encode($rawScan); // optional encoding
    }

    /**
     * Validate a raw fingerprint scan
     */
    public static function isValidScan(string $rawScan): bool
    {
        return !empty($rawScan); // simple check
    }

    /**
     * Compare a raw scan with a saved template
     */
    public static function compare(string $rawScan, string $template): bool
    {
        return base64_encode($rawScan) === $template; // match encoded string
    }
}
