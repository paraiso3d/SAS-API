<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Students;
use App\Models\StudentAttendance;
use App\Models\AbsenceReasons;

class DashBoardController extends Controller
{
    public function getDashboardData()
    {
        $today = Carbon::now('Asia/Manila')->toDateString();

        // TOTAL STUDENTS
        $totalStudents = Students::where('is_archived', 0)->count();

        //  PRESENT TODAY (has record today)
        $presentToday = StudentAttendance::where('attendance_date', $today)
            ->count();

        // TIMED IN ONLY (no timeout yet)
        $timeInOnly = StudentAttendance::where('attendance_date', $today)
            ->whereNull('time_out')
            ->count();

        //  COMPLETED (timed in + out)
        $completed = StudentAttendance::where('attendance_date', $today)
            ->whereNotNull('time_out')
            ->count();

        // RECENT ATTENDANCE (latest 5)
        $recent = StudentAttendance::with('student')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($attendance) {
                $student = $attendance->student;

                return [
                    'student_number' => $attendance->student_number,
                    'full_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                    'profile_picture_url' => $student && $student->profile_picture
                        ? asset($student->profile_picture)
                        : null,
                ];
            });

        return response()->json([
            'total_students' => $totalStudents,
            'present_today' => $presentToday,
            'time_in_only' => $timeInOnly,
            'completed' => $completed,
            'recent_attendance' => $recent
        ], 200);
    }
}
