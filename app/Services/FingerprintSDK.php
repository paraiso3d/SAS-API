<?php

namespace App\Services;

class FingerprintSDK
{
    // Convert raw scan to a template (stub for now)
    public static function createTemplate(string $rawScan): string
    {
        return $rawScan; // In real case, call SDK logic here
    }

    // Compare raw scan with a saved template
    public static function compare(string $rawScan, string $template): bool
    {
        return $rawScan === $template; // Stub logic
    }

    // Optional: Validate raw scan
    public static function isValidScan(string $rawScan): bool
    {
        return !empty($rawScan);
    }
}
