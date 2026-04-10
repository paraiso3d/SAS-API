<?php

namespace App\Http\Controllers;

use App\Models\StudentAttendance;
use App\Models\Students;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Fingerprint;
use App\Models\ApiLog;
use App\Services\FingerprintSDK;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Models\Employee;
use App\Models\EmployeeAttendance;


class StudentAttendanceController extends Controller
{


    public function getrecentattendance()
    {
        $recentAttendance = StudentAttendance::with('student')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function ($attendance) {

                $student = $attendance->student;

                return [
                    'id' => $attendance->id,
                    'student_number' => $attendance->student_number,
                    'attendance_date' => $attendance->attendance_date,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,

                    //  Names
                    'first_name' => $student->first_name ?? null,
                    'last_name' => $student->last_name ?? null,

                    //  Full name
                    'full_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),

                    // Profile Image URL
                    'profile_picture_url' => $student && $student->profile_picture
                        ? asset($student->profile_picture)
                        : null,
                    'created_at' => $attendance->created_at,
                    'updated_at' => $attendance->updated_at,
                ];
            });

        return response()->json($recentAttendance, 200);
    }
    /**
     * Time in a student.
     */

    public function tapRFID(Request $request)
    {
        $request->validate([
            'rfid_tag_number' => 'required|string',
        ]);

        Log::info('RFID TAP RECEIVED');

        $now = Carbon::now('Asia/Manila');
        $today = $now->toDateString();

        // =========================
        // 🔍 CHECK STUDENT FIRST
        // =========================
        $student = Students::where('rfid_tag_number', $request->rfid_tag_number)
            ->where('is_archived', 0)
            ->first();

        if ($student) {

            $attendance = StudentAttendance::where('student_number', $student->student_number)
                ->where('attendance_date', $today)
                ->first();

            // TIME IN
            if (!$attendance) {
                $attendance = StudentAttendance::create([
                    'student_number' => $student->student_number,
                    'attendance_date' => $today,
                    'time_in' => $now,
                    'status' => 'Timed In'
                ]);
                $action = 'TIME IN';
            }
            // TIME OUT
            elseif (!$attendance->time_out) {
                $timeIn = Carbon::parse($attendance->time_in)->setTimezone('Asia/Manila');

                if ($timeIn->diffInMinutes($now) < 5) {
                    $remaining = 5 - $timeIn->diffInMinutes($now);

                    return response()->json([
                        'isSuccess' => false,
                        'message' => "Please wait {$remaining} more minute(s) before timing out"
                    ], 429);
                }

                $attendance->update([
                    'time_out' => $now,
                    'status' => 'Timed Out'
                ]);

                $action = 'TIME OUT';
            }
            // DONE
            else {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Already timed in and out today'
                ], 200);
            }

            Log::info("ACTION: $action", ['student_number' => $student->student_number]);

            // =========================
            // 📩 SMS (ONLY FOR STUDENT)
            // =========================
            try {
                $fullName = "{$student->first_name} {$student->last_name}";
                $details = "{$student->course_name} - {$student->section_name}";
                $number = $student->guardian_contact_number ?: $student->contact_number;

                if ($number) {
                    $number = preg_replace('/^0/', '63', $number);
                    $number = preg_replace('/\D/', '', $number);

                    $message = "Notice: {$fullName} ({$student->student_number}) has "
                        . ($action === 'TIME OUT' ? "TIMED OUT" : "TIMED IN")
                        . " at {$now->format('h:i A')}. Course: {$details}.";

                    $message = preg_replace('/[^\x20-\x7E]/', '', $message);

                    Log::info("Sending iTexMo SMS to {$number}", ['message' => $message]);

                    $payload = [
                        "Email"     => env('ITEXMO_EMAIL'),
                        "Password"  => env('ITEXMO_PASSWORD'),
                        "ApiCode"   => env('ITEXMO_API_CODE'),
                        "Recipients" => $number,
                        "Message"   => $message
                    ];

                    $ch = curl_init("https://api.itexmo.com/api/broadcast");
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($payload),
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 15
                    ]);

                    $curlResponse = curl_exec($ch);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($curlError) {
                        Log::error("iTexMo cURL Error: {$curlError}");
                    } else {
                        Log::info("iTexMo API Response: {$curlResponse}");
                    }
                }
            } catch (\Exception $e) {
                Log::error("SMS sending failed: " . $e->getMessage());
            }

            return response()->json([
                'type' => 'student',
                'isSuccess' => true,
                'message' => $action . ' recorded successfully',
                'attendance' => $attendance,
                'name' => $student->first_name . ' ' . $student->last_name
            ], 200);
        }

        // =========================
        // 🔍 CHECK EMPLOYEE
        // =========================
        $employee = Employee::where('rfid_tag_number', $request->rfid_tag_number)
            ->where('is_archived', 0)
            ->first();

        if ($employee) {

            $attendance = EmployeeAttendance::where('employee_number', $employee->employee_number)
                ->where('attendance_date', $today)
                ->first();

            // TIME IN
            if (!$attendance) {
                $attendance = EmployeeAttendance::create([
                    'employee_number' => $employee->employee_number,
                    'attendance_date' => $today,
                    'time_in' => $now,
                    'status' => 'Timed In'
                ]);
                $action = 'TIME IN';
            }
            // TIME OUT
            elseif (!$attendance->time_out) {
                $attendance->update([
                    'time_out' => $now,
                    'status' => 'Timed Out'
                ]);
                $action = 'TIME OUT';
            }
            // DONE
            else {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Already timed in and out today'
                ], 200);
            }

            Log::info("EMPLOYEE ACTION: $action", [
                'employee_number' => $employee->employee_number
            ]);

            // ❌ NO SMS HERE (as requested)

            return response()->json([
                'type' => 'employee',
                'isSuccess' => true,
                'message' => $action . ' recorded successfully',
                'attendance' => $attendance,
                'name' => $employee->first_name . ' ' . $employee->last_name
            ], 200);
        }

        // =========================
        // ❌ NOT FOUND
        // =========================
        return response()->json([
            'isSuccess' => false,
            'message' => 'RFID not recognized'
        ], 404);
    }



    /**
     * Get attendance records by RFID tag number.
     */
    public function getAttendanceByRfid($rfid_tag_number)
    {
        $attendance = StudentAttendance::where('rfid_tag_number', $rfid_tag_number)->get();

        return response()->json($attendance, 200);
    }

    public function getAttendaces()
    {
        $attendances = StudentAttendance::with('student')->get();

        return response()->json($attendances, 200);
    }

    /**
     * View today's attendance records.
     */
    public function getTodayAttendance()
    {
        $today = Carbon::today()->toDateString();
        $attendance = StudentAttendance::where('attendance_date', $today)->get();

        return response()->json($attendance, 200);
    }


    //HEL:PERS
    private function saveFileToPublic($fileInput, $prefix)
    {
        $directory = public_path('sas_files');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $saveSingleFile = function ($file) use ($directory, $prefix) {
            $filename = $prefix . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($directory, $filename);
            return 'sas_files/' . $filename;
        };

        //  Case 1: Multiple files
        if (is_array($fileInput)) {
            $paths = [];
            foreach ($fileInput as $file) {
                $paths[] = $saveSingleFile($file);
            }
            return $paths; // Return array of paths
        }

        // Case 2: Single file
        if ($fileInput instanceof \Illuminate\Http\UploadedFile) {
            return $saveSingleFile($fileInput);
        }

        return null;
    }
}
