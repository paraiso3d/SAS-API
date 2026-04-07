<?php

namespace App\Services;

class FingerprintSDK
{
    /**
     * Convert incoming scan to a stored template
     */
    public static function createTemplate(string $rawScan): string
    {
        // Currently we just store the string as-is (already Base64 from front-end)
        return $rawScan;
    }

    /**
     * Basic validation of scan
     */
    public static function isValidScan(string $rawScan): bool
    {
        return !empty($rawScan);
    }

    /**
     * Compare incoming sample with stored template
     * 🔥 In real implementation, use DigitalPersona SDK fuzzy matching
     */
    public static function compare(string $sample, string $template): bool
    {
        // Temporary: exact string match (works with your current front-end)
        return $sample === $template;
    }
}
