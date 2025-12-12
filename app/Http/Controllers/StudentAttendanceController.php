<?php

namespace App\Http\Controllers;

use App\Models\StudentAttendance;
use App\Models\Students;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StudentAttendanceController extends Controller
{
    /**
     * Time in a student.
     */
    public function timeIn(Request $request)
    {
        $request->validate([
            'rfid_tag_number' => 'required|string|max:250',
        ]);

        $student = Students::where('rfid_tag_number', $request->rfid_tag_number)
            ->where('is_archived', 0)
            ->first();

        if (!$student) {
            Log::warning('Student not found for RFID: ' . $request->rfid_tag_number);
            return response()->json(['message' => 'Student not found'], 404);
        }

        $today = Carbon::today()->toDateString();

        $attendance = StudentAttendance::firstOrNew([
            'rfid_tag_number' => $request->rfid_tag_number,
            'attendance_date' => $today
        ]);

        if ($attendance->exists && $attendance->time_in) {
            // Already timed in
            return response()->json(['message' => 'Student already timed in'], 200);
        }

        // Set the time_in only if not set
        if (!$attendance->time_in) {
            $attendance->student_number = $student->student_number;
            $attendance->time_in = Carbon::now();
            $attendance->status = 'present';
            $attendance->save();
        }

        return response()->json([
            'message' => 'Time in recorded and SMS sent',
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
