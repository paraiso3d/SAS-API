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

    /**
     * Time in a student.
     */

    public function timeIn(Request $request)
    {
        Log::info('STEP 1: timeIn hit');

        $request->validate([
            'rfid_tag_number' => 'required|string',
        ]);

        // 🔥 Find student via RFID
        $matchedStudent = Students::where('rfid_tag_number', $request->rfid_tag_number)
            ->where('is_archived', 0)
            ->first();

        Log::info('STEP 2: student query executed');

        if (!$matchedStudent) {
            Log::info('STEP 2.1: student NOT found');

            return response()->json([
                'isSuccess' => false,
                'message' => 'RFID not recognized'
            ], 404);
        }

        Log::info('STEP 3: student found', [
            'student_number' => $matchedStudent->student_number
        ]);

        $today = now()->toDateString();

        // ✅ Attendance
        $attendance = StudentAttendance::firstOrCreate(
            [
                'student_number' => $matchedStudent->student_number,
                'attendance_date' => $today
            ],
            [
                'status' => 'present'
            ]
        );

        Log::info('STEP 4: attendance created/fetched');

        // 🚫 Prevent double time-in
        if ($attendance->time_in) {
            Log::info('STEP 4.1: already timed in');

            return response()->json([
                'isSuccess' => true,
                'message' => 'Student already timed in',
                'attendance' => $attendance
            ], 200);
        }

        // ✅ Record time in
        $attendance->update([
            'time_in' => now()
        ]);

        Log::info('STEP 5: time_in updated');

        // 🔥 SEND SMS (Semaphore)
        try {
            $number = $matchedStudent->contact_number;

            Log::info('STEP 6: preparing SMS', ['raw_number' => $number]);

            if ($number) {

                // Normalize → 639XXXXXXXXX
                if (str_starts_with($number, '09')) {
                    $number = '63' . substr($number, 1);
                }

                $message = "Student {$matchedStudent->student_number} has timed in at " . now()->format('h:i A');

                Log::info('STEP 7: sending SMS', [
                    'number' => $number,
                    'message' => $message
                ]);

                $response = Http::timeout(5)->post('https://semaphore.co/api/v4/messages', [
                    'apikey' => env('SEMAPHORE_API_KEY'),
                    'number' => $number,
                    'message' => $message,
                ]);;

                Log::info('STEP 8: SMS response', [
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('STEP ERROR: SMS failed', [
                'error' => $e->getMessage()
            ]);
        }

        Log::info('STEP 9: finished');

        return response()->json([
            'isSuccess' => true,
            'message' => 'Time in recorded successfully',
            'attendance' => $attendance
        ], 201);
    }

    /**
     * Time out a student.
     */
    public function timeOut(Request $request)
    {
        $request->validate([
            'rfid_tag_number' => 'required|string|max:250',
        ]);

        $today = Carbon::today()->toDateString();

        $attendance = StudentAttendance::where('rfid_tag_number', $request->rfid_tag_number)
            ->where('attendance_date', $today)
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'No time in found for today'], 404);
        }

        if ($attendance->time_out) {
            return response()->json(['message' => 'Student already timed out'], 200);
        }

        $attendance->time_out = Carbon::now();
        $attendance->save();

        return response()->json([
            'message' => 'Time out recorded successfully',
            'attendance' => $attendance
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
