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

        // 🇵🇭 Use Philippine Time
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

        // 🔥 Get today's attendance
        $attendance = StudentAttendance::where('student_number', $student->student_number)
            ->where('attendance_date', $today)
            ->first();

        // =========================
        // ✅ CASE 1: NO RECORD → TIME IN
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
        // ✅ CASE 2: HAS TIME IN, NO TIME OUT → TIME OUT
        // =========================
        elseif (!$attendance->time_out) {

            // ⏱️ Enforce 5-minute rule using PH time
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
        // ❌ CASE 3: ALREADY COMPLETED
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

        // 🔥 OPTIONAL SMS (Semaphore)
        try {
            $number = $student->contact_number;

            if ($number && str_starts_with($number, '09')) {
                $number = '63' . substr($number, 1);
            }

            $message = "Student {$student->student_number} {$action} at " . $now->format('h:i A');

            Http::timeout(5)->post('https://semaphore.co/api/v4/messages', [
                'apikey' => env('SEMAPHORE_API_KEY'),
                'number' => $number,
                'message' => $message,
            ]);
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
}
