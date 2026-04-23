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


    public function getAttendanceSummary(Request $request)
    {
        $query = StudentAttendance::query();

        // =========================
        // SAME FILTERS (keep consistent)
        // =========================
        if ($request->filled('student_number')) {
            $query->where('student_number', 'like', '%' . $request->student_number . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [
                $request->date_from,
                $request->date_to
            ]);
        }

        // =========================
        // GET FILTERED DATA
        // =========================
        $records = $query->get();

        $totalRecords = $records->count();

        // Present = has time_in
        $totalPresent = $records->whereNotNull('time_in')->count();

        // Optional: Timed Out (if you want extra stat)
        $totalTimedOut = $records->whereNotNull('time_out')->count();

        return response()->json([
            'message' => 'Student Attendance Summary',
            'data' => [
                'total_records' => $totalRecords,
                'present' => $totalPresent,
                'timed_out' => $totalTimedOut
            ]
        ], 200);
    }


    public function getrecentattendance()
    {
        $today = Carbon::today()->toDateString();

        // STUDENT ATTENDANCE
        $recentStudentAttendance = StudentAttendance::with('student')
            ->whereDate('attendance_date', $today)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function ($attendance) {

                $student = $attendance->student;

                return [
                    'type' => 'student',
                    'id' => $attendance->id,
                    'student_number' => $attendance->student_number,
                    'attendance_date' => $attendance->attendance_date,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,

                    'first_name' => $student->first_name ?? null,
                    'last_name' => $student->last_name ?? null,
                    'full_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),

                    'profile_picture_url' => $student && $student->profile_picture
                        ? asset($student->profile_picture)
                        : null,

                    'created_at' => $attendance->created_at,
                ];
            });

        // EMPLOYEE ATTENDANCE
        $recentEmployeeAttendance = EmployeeAttendance::whereDate('attendance_date', $today)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get()
            ->map(function ($attendance) {

                return [
                    'type' => 'employee',
                    'id' => $attendance->id,
                    'employee_number' => $attendance->employee_number,
                    'attendance_date' => $attendance->attendance_date,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                    'status' => $attendance->status,
                    'created_at' => $attendance->created_at,
                ];
            });

        return response()->json([
            'students' => $recentStudentAttendance,
            'employees' => $recentEmployeeAttendance
        ], 200);
    }


    // /**
    //  * Time in GOIP
    //  */

    // public function tapRFID(Request $request)
    // {
    //     $request->validate([
    //         'rfid_tag_number' => 'required|string',
    //     ]);

    //     Log::info('RFID TAP RECEIVED');

    //     $now = Carbon::now('Asia/Manila');
    //     $today = $now->toDateString();

    //     // =========================
    //     // NORMALIZE RFID
    //     // =========================
    //     $rfid = trim($request->rfid_tag_number);
    //     $rfid = preg_replace('/\D/', '', $rfid);

    //     Log::info('RFID DEBUG', [
    //         'raw' => $request->rfid_tag_number,
    //         'normalized' => $rfid
    //     ]);

    //     // =========================
    //     // CHECK STUDENT FIRST
    //     // =========================
    //     $student = Students::whereRaw(
    //         "REPLACE(REPLACE(rfid_tag_number, ' ', ''), '-', '') = ?",
    //         [$rfid]
    //     )->where('is_archived', 0)->first();

    //     if ($student) {

    //         $attendance = StudentAttendance::where('student_number', $student->student_number)
    //             ->where('attendance_date', $today)
    //             ->orderBy('time_in', 'desc')
    //             ->first();

    //         // =========================
    //         // 5 MIN COOLDOWN (STUDENT)
    //         // =========================
    //         if ($attendance) {

    //             $lastScanTime = $attendance->time_out ?: $attendance->time_in;

    //             if ($lastScanTime) {
    //                 $lastScanTime = Carbon::parse($lastScanTime)->setTimezone('Asia/Manila');

    //                 if ($lastScanTime->diffInMinutes($now) < 5) {

    //                     $remaining = 5 - $lastScanTime->diffInMinutes($now);

    //                     return response()->json([
    //                         'type' => 'student',
    //                         'isSuccess' => false,
    //                         'message' => "Please wait {$remaining} more minute(s) before tapping again"
    //                     ], 429);
    //                 }
    //             }
    //         }

    //         // =========================
    //         // TIME IN / OUT
    //         // =========================
    //         if (!$attendance || $attendance->time_out) {

    //             $attendance = StudentAttendance::create([
    //                 'student_number' => $student->student_number,
    //                 'attendance_date' => $today,
    //                 'time_in' => $now,
    //                 'status' => 'Timed In'
    //             ]);

    //             $action = 'TIME IN';
    //         } else {

    //             $attendance->update([
    //                 'time_out' => $now,
    //                 'status' => 'Timed Out'
    //             ]);

    //             $action = 'TIME OUT';
    //         }

    //         Log::info("STUDENT ACTION: $action", [
    //             'student_number' => $student->student_number
    //         ]);

    //         // =========================
    //         // SMS
    //         // =========================
    //         $fullName = $student->first_name . ' ' . $student->last_name;

    //         $message = "{$fullName} has "
    //             . ($action === 'TIME OUT' ? "TIMED OUT" : "TIMED IN")
    //             . " at {$now->format('h:i A')}";

    //         $this->sendViaGoip($student->guardian_contact_number, $message);

    //         return response()->json([
    //             'type' => 'student',
    //             'isSuccess' => true,
    //             'message' => $action . ' recorded',
    //             'attendance' => $attendance,
    //             'name' => $fullName
    //         ], 200);
    //     }

    //     // =========================
    //     // CHECK EMPLOYEE
    //     // =========================
    //     $employee = Employee::whereRaw(
    //         "REPLACE(REPLACE(rfid_tag_number, ' ', ''), '-', '') = ?",
    //         [$rfid]
    //     )->where('is_archived', 0)->first();

    //     if ($employee) {

    //         $attendance = EmployeeAttendance::where('employee_number', $employee->employee_number)
    //             ->where('attendance_date', $today)
    //             ->orderBy('time_in', 'desc')
    //             ->first();

    //         // =========================
    //         // 5 MIN COOLDOWN (EMPLOYEE)
    //         // =========================
    //         if ($attendance) {

    //             $lastScanTime = $attendance->time_out ?: $attendance->time_in;

    //             if ($lastScanTime) {
    //                 $lastScanTime = Carbon::parse($lastScanTime)->setTimezone('Asia/Manila');

    //                 if ($lastScanTime->diffInMinutes($now) < 5) {

    //                     $remaining = 5 - $lastScanTime->diffInMinutes($now);

    //                     return response()->json([
    //                         'type' => 'employee',
    //                         'isSuccess' => false,
    //                         'message' => "Please wait {$remaining} more minute(s) before tapping again"
    //                     ], 429);
    //                 }
    //             }
    //         }

    //         // =========================
    //         // TIME IN / OUT
    //         // =========================
    //         if (!$attendance || $attendance->time_out) {

    //             $attendance = EmployeeAttendance::create([
    //                 'employee_number' => $employee->employee_number,
    //                 'attendance_date' => $today,
    //                 'time_in' => $now,
    //                 'status' => 'Timed In'
    //             ]);

    //             $action = 'TIME IN';
    //         } else {

    //             $attendance->update([
    //                 'time_out' => $now,
    //                 'status' => 'Timed Out'
    //             ]);

    //             $action = 'TIME OUT';
    //         }

    //         Log::info("EMPLOYEE ACTION: $action", [
    //             'employee_number' => $employee->employee_number
    //         ]);

    //         return response()->json([
    //             'type' => 'employee',
    //             'isSuccess' => true,
    //             'message' => $action . ' recorded',
    //             'attendance' => $attendance,
    //             'name' => $employee->first_name . ' ' . $employee->last_name
    //         ], 200);
    //     }

    //     // =========================
    //     // NOT FOUND
    //     // =========================
    //     return response()->json([
    //         'isSuccess' => false,
    //         'message' => 'RFID not recognized'
    //     ], 404);
    // }



    /**
     * Time in PHILSMS (backup)
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
        // NORMALIZE RFID
        // =========================
        $rfid = trim($request->rfid_tag_number);
        $rfid = preg_replace('/\D/', '', $rfid);

        Log::info('RFID DEBUG', [
            'raw' => $request->rfid_tag_number,
            'normalized' => $rfid
        ]);

        // =========================
        // GLOBAL COOLDOWN CHECK (5 MIN)
        // =========================

        $lastStudent = StudentAttendance::whereHas('student', function ($q) use ($rfid) {
            $q->whereRaw("REPLACE(rfid_tag_number, ' ', '') = ?", [$rfid]);
        })
            ->latest('created_at')
            ->first();

        $lastEmployee = EmployeeAttendance::whereHas('employee', function ($q) use ($rfid) {
            $q->whereRaw("REPLACE(rfid_tag_number, ' ', '') = ?", [$rfid]);
        })
            ->latest('created_at')
            ->first();

        $lastAttendance = collect([$lastStudent, $lastEmployee])
            ->filter()
            ->sortByDesc('created_at')
            ->first();

        if ($lastAttendance) {
            $lastTime = Carbon::parse($lastAttendance->created_at)->setTimezone('Asia/Manila');

            if ($lastTime->diffInMinutes($now) < 5) {
                $remaining = 5 - $lastTime->diffInMinutes($now);

                return response()->json([
                    'isSuccess' => false,
                    'message' => "Please wait {$remaining} more minute(s) before tapping again"
                ], 429);
            }
        }

        // =========================
        // CHECK STUDENT FIRST
        // =========================

        $student = Students::whereRaw("TRIM(rfid_tag_number) = ?", [$rfid])
            ->where('is_archived', 0)
            ->first();

        if ($student) {

            $attendance = StudentAttendance::where('student_number', $student->student_number)
                ->where('attendance_date', $today)
                ->orderBy('time_in', 'desc')
                ->first();

            if (!$attendance || $attendance->time_out) {
                $attendance = StudentAttendance::create([
                    'student_number' => $student->student_number,
                    'attendance_date' => $today,
                    'time_in' => $now,
                    'status' => 'Timed In'
                ]);
                $action = 'TIME IN';
            } else {
                $attendance->update([
                    'time_out' => $now,
                    'status' => 'Timed Out'
                ]);
                $action = 'TIME OUT';
            }

            Log::info("STUDENT ACTION: $action", [
                'student_number' => $student->student_number
            ]);

            // SEND SMS
            $this->sendSms(
                $student->first_name . ' ' . $student->last_name,
                $student->guardian_contact_number,
                $action,
                $now
            );

            return response()->json([
                'type' => 'student',
                'isSuccess' => true,
                'message' => $action . ' recorded',
                'attendance' => $attendance,
                'name' => $student->first_name . ' ' . $student->last_name
            ], 200);
        }

        // =========================
        // CHECK EMPLOYEE
        // =========================
        $employee = Employee::whereRaw("TRIM(rfid_tag_number) = ?", [$rfid])
            ->where('is_archived', 0)
            ->first();

        if ($employee) {

            $attendance = EmployeeAttendance::where('employee_number', $employee->employee_number)
                ->where('attendance_date', $today)
                ->orderBy('time_in', 'desc')
                ->first();

            if (!$attendance || $attendance->time_out) {
                $attendance = EmployeeAttendance::create([
                    'employee_number' => $employee->employee_number,
                    'attendance_date' => $today,
                    'time_in' => $now,
                    'status' => 'Timed In'
                ]);
                $action = 'TIME IN';
            } else {
                $attendance->update([
                    'time_out' => $now,
                    'status' => 'Timed Out'
                ]);
                $action = 'TIME OUT';
            }

            Log::info("EMPLOYEE ACTION: $action", [
                'employee_number' => $employee->employee_number
            ]);

            // SEND SMS
            $this->sendSms(
                $employee->first_name . ' ' . $employee->last_name,
                $employee->contact_number,
                $action,
                $now
            );

            return response()->json([
                'type' => 'employee',
                'isSuccess' => true,
                'message' => $action . ' recorded',
                'attendance' => $attendance,
                'name' => $employee->first_name . ' ' . $employee->last_name
            ], 200);
        }

        return response()->json([
            'isSuccess' => false,
            'message' => 'RFID not recognized'
        ], 404);
    }

    private function sendSms($fullName, $number, $action, $now)
    {
        try {
            if (!$number) return;

            // format to 63XXXXXXXXXX
            $number = preg_replace('/^0/', '63', $number);
            $number = preg_replace('/\D/', '', $number);

            $message = "{$fullName} has "
                . ($action === 'TIME OUT' ? "TIMED OUT" : "TIMED IN")
                . " at {$now->format('h:i A')}";

            $response = Http::timeout(5)->withHeaders([
                'Authorization' => 'Bearer ' . env('PHILSMS_API_TOKEN'),
                'Accept' => 'application/json',
            ])->post('https://dashboard.philsms.com/api/v3/sms/send', [
                'recipient' => $number,
                'sender_id' => env('PHILSMS_SENDER_ID'),
                'type' => 'plain',
                'message' => $message,
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? null) === 'success') {
                Log::info('SMS SENT', ['number' => $number]);
            } else {
                Log::error('SMS FAILED', [
                    'status' => $response->status(),
                    'response' => $data
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('SMS ERROR', [
                'error' => $th->getMessage()
            ]);
        }
    }


    private function sendViaGoip($number, $message)
    {
        try {
            if (!$number) return;

            // Ensure PH format (09XXXXXXXXX)
            $number = preg_replace('/^63/', '0', $number);
            $number = preg_replace('/\D/', '', $number);

            $url = "http://192.168.8.1/default/en_US/send.html";

            $params = [
                "u" => "admin",
                "p" => "admin",
                "l" => "1",
                "n" => $number,
                "m" => $message
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . "?" . http_build_query($params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                Log::error('GOIP ERROR: ' . curl_error($ch));
            } else {
                Log::info('SMS SENT VIA GOIP', [
                    'number' => $number,
                    'message' => $message,
                    'response' => $response
                ]);
            }

            curl_close($ch);
        } catch (\Throwable $e) {
            Log::error('GOIP FAILED', [
                'error' => $e->getMessage()
            ]);
        }
    }



    /**
     * Get attendance records by RFID tag number.
     */
    public function getAttendanceByRfid($rfid_tag_number)
    {
        $attendance = StudentAttendance::where('rfid_tag_number', $rfid_tag_number)->get();

        return response()->json($attendance, 200);
    }



    public function getAttendaces(Request $request)
    {
        $query = StudentAttendance::with('student');

        // =========================
        // FILTERS
        // =========================
        if ($request->filled('student_number')) {
            $query->where('student_number', 'like', '%' . $request->student_number . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('attendance_date', [
                $request->date_from,
                $request->date_to
            ]);
        }

        // =========================
        // 🔥 COMPUTE TOTAL HOURS PER STUDENT
        // =========================
        $summaryQuery = clone $query;
        $allRecords = $summaryQuery->get();

        $studentHours = [];

        foreach ($allRecords as $attendance) {

            if (!$attendance->time_in || !$attendance->time_out) {
                continue;
            }

            $timeIn = Carbon::parse($attendance->time_in);
            $timeOut = Carbon::parse($attendance->time_out);

            $minutes = $timeOut->diffInMinutes($timeIn);

            $studentNumber = $attendance->student_number;

            if (!isset($studentHours[$studentNumber])) {
                $studentHours[$studentNumber] = 0;
            }

            $studentHours[$studentNumber] += $minutes;
        }

        // convert minutes to hours
        foreach ($studentHours as $key => $minutes) {
            $studentHours[$key] = round($minutes / 60, 2);
        }

        // =========================
        // PAGINATION
        // =========================
        $query->orderBy('attendance_date', 'desc');

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $attendances = $query->paginate($perPage, ['*'], 'page', $page);

        // =========================
        // 🔥 ATTACH HOURS TO EACH RECORD
        // =========================
        $data = collect($attendances->items())->map(function ($attendance) use ($studentHours) {

            $studentNumber = $attendance->student_number;

            return [
                ...$attendance->toArray(),

                // 🔥 here’s your total hours per student
                'total_hours_rendered' => $studentHours[$studentNumber] ?? 0
            ];
        });

        // =========================
        // RESPONSE
        // =========================
        return response()->json([
            'message' => 'Student Attendance List',
            'data' => $data,
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'last_page' => $attendances->lastPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
            ]
        ], 200);
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
