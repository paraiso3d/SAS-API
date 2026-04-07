<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\FingerprintSDK;

class Fingerprint extends Model
{
    use HasFactory;

    protected $table = 'fingerprints';

    protected $fillable = [
        'student_id',
        'fingerprint_template',
    ];

    // 🔹 Relationship to Student
    public function student()
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    // 🔹 SDK Helper Methods
    public static function verify($rawScan, $template)
    {
        // This is your placeholder for the actual SDK call
        // e.g., DigitalPersona::compare($rawScan, $template)
        return FingerprintSDK::compare($rawScan, $template);
    }

    public static function createTemplate($rawScan)
    {
        // Placeholder to convert raw scan into template
        return FingerprintSDK::createTemplate($rawScan);
    }
}
