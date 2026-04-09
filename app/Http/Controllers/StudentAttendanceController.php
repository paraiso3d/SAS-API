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


class StudentAttendanceController extends Controller
{




    public function getrecentattendance()
    {
        $recentAttendance = StudentAttendance::with('student')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'student_number' => $attendance->student_number,
                    'attendance_date' => $attendance->attendance_date,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,

                    // 🔥 Add names here
                    'first_name' => $attendance->student->first_name ?? null,
                    'last_name' => $attendance->student->last_name ?? null,

                    // optional (cleaner)
                    'full_name' => ($attendance->student->first_name ?? '') . ' ' . ($attendance->student->last_name ?? ''),
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

        // 🔥 Find student
        $student = Students::where('rfid_tag_number', $request->rfid_tag_number)
            ->where('is_archived', 0)
            ->first();

        if (!$student) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'RFID not recognized'
            ], 404);
        }

        // 🔥 Get attendance
        $attendance = StudentAttendance::where('student_number', $student->student_number)
            ->where('attendance_date', $today)
            ->first();

        // =========================
        // TIME IN
        // =========================
        if (!$attendance) {

            $attendance = StudentAttendance::create([
                'student_number' => $student->student_number,
                'attendance_date' => $today,
                'time_in' => $now,
                'status' => 'present'
            ]);

            $action = 'TIME IN';
        }

        // =========================
        // TIME OUT
        // =========================
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
                'time_out' => $now
            ]);

            $action = 'TIME OUT';
        }

        // =========================
        // DONE
        // =========================
        else {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Already timed in and out today'
            ], 200);
        }

        Log::info("ACTION: $action", [
            'student_number' => $student->student_number
        ]);

        // =========================
        // 🔥 SMS (iTexMo)
        // =========================
        try {

            // 👤 Full name
            $fullName = "{$student->first_name} {$student->last_name}";

            // 📚 Extra details
            $details = "{$student->course_name} - {$student->section_name}";

            // 📱 Choose guardian number first
            $number = $student->guardian_contact_number ?? $student->contact_number;

            // Convert 09 → 639
            if ($number && str_starts_with($number, '09')) {
                $number = '63' . substr($number, 1);
            }

            // 🔥 Message
            if ($action === 'TIME OUT') {
                $message = "Notice: {$fullName} ({$student->student_number}) has TIMED OUT at "
                    . $now->format('h:i A') . ". Course: {$details}.";
            } else {
                $message = "Notice: {$fullName} ({$student->student_number}) has TIMED IN at "
                    . $now->format('h:i A') . ". Course: {$details}.";
            }

            $response = Http::asForm()->post('https://www.itexmo.com/php_api/api.php', [
                '1' => $number,
                '2' => $message,
                '3' => env('ITEXMO_API_CODE'),
            ]);

            if ($response->body() != "0") {
                Log::error('iTexMo Error Code: ' . $response->body());
            } else {
                Log::info('SMS sent successfully');
            }
        } catch (\Exception $e) {
            Log::error('SMS failed: ' . $e->getMessage());
        }

        return response()->json([
            'isSuccess' => true,
            'message' => $action . ' recorded successfully',
            'attendance' => $attendance,
            'student' => [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'profile_picture_url' => $student->profile_picture
                    ? asset($student->profile_picture)
                    : null,
            ],
            'action' => $action
        ], 200);
    }

    /**
     * Get attendance records by RFID tag number.
     */
    public function getAttendanceByRfid($rfid_tag_number)
    {
        $attendance = StudentAttendance::where('rfid_tag_number', $rfid_tag_number)->get();

        return response()->json($attendance, 200);
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
