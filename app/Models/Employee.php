<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
        'rfid_tag_number',
        'fingerprint_id',
        'profile_picture',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'birthdate',
        'email',
        'contact_number',
        'department',
        'position',
        'employment_status',
        'is_active',
        'is_archived'
    ];

    public function employee_attendances()
    {
        return $this->hasMany(EmployeeAttendance::class);
    }
}
