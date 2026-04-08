<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAttendance extends Model
{
    use HasFactory;

    protected $table = 'student_attendance';

    protected $fillable = [
        'fingerprint_id',
        'rfid_tag_number',
        'student_number',
        'time_in',
        'time_out',
        'attendance_date',
        'status'
    ];

    public function student()
    {
        return $this->belongsTo(Students::class, 'student_number', 'student_number');
    }


    public $timestamps = true;
}
