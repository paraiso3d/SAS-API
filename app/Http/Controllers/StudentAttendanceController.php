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

class StudentAttendanceController extends Controller
{

    /**
     * Time in a student.
     */
    public function timeIn(Request $request)
    {
        $request->validate([
            'fingerprint_scan' => 'required|string', // raw scan from frontend
        ]);

        $scan = $request->fingerprint_scan;

        // Get all fingerprints with related students who are not archived
        $fingerprints = Fingerprint::with('student')
            ->whereHas('student', fn($q) => $q->where('is_archived', 0))
            ->get();

        $matchedStudent = null;

        // Loop through templates to find a match
        foreach ($fingerprints as $fingerprint) {
            if (FingerprintSDK::match($scan, $fingerprint->fingerprint_template)) {
                $matchedStudent = $fingerprint->student;
                break;
            }
        }

        if (!$matchedStudent) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Fingerprint not recognized'
            ], 404);
        }

        $today = now()->toDateString();

        // Create or get attendance
        $attendance = StudentAttendance::firstOrCreate(
            [
                'student_number' => $matchedStudent->student_number,
                'attendance_date' => $today
            ],
            [
                'status' => 'present'
            ]
        );

        // Already timed in
        if ($attendance->time_in) {
            return response()->json([
                'isSuccess' => true,
                'message' => 'Student already timed in',
                'attendance' => $attendance
            ], 200);
        }

        // Record time in
        $attendance->update([
            'time_in' => now()
        ]);

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
