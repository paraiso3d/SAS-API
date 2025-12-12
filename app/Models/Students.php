<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Students extends Model
{
    use HasFactory;

    protected $table = 'students';

    protected $fillable = [
        'rfid_tag_number',
        'student_number',
        'student_status',
        'is_active',
        'course_name',
        'section_name',
        'school_year',
        'semester',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'birthdate',
        'email',
        'contact_number',
        'guardian_contact_number',
    ];
}
