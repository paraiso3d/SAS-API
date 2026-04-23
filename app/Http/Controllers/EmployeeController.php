<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use Carbon\Carbon;

class EmployeeController extends Controller
{


    public function getEmployeeAttendanceSummary(Request $request)
    {
        $query = EmployeeAttendance::query();

        // =========================
        // FILTERS (optional, same pattern)
        // =========================
        if ($request->filled('employee_number')) {
            $query->where('employee_number', 'like', '%' . $request->employee_number . '%');
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
        // SUMMARY (DB-level, fast)
        // =========================
        $totalRecords = (clone $query)->count();

        // Present = has time_in
        $totalPresent = (clone $query)->whereNotNull('time_in')->count();

        // Timed Out = has time_out
        $totalTimedOut = (clone $query)->whereNotNull('time_out')->count();

        return response()->json([
            'message' => 'Employee Attendance Summary',
            'data' => [
                'total_records' => $totalRecords,
                'present' => $totalPresent,
                'timed_out' => $totalTimedOut
            ]
        ], 200);
    }

    //Employee Attendace
    public function getEmployeeAttendance(Request $request)
    {
        $query = EmployeeAttendance::with('employee');

        // =========================
        // SEARCH
        // =========================
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'LIKE', "%$search%")
                    ->orWhereHas('employee', function ($emp) use ($search) {
                        $emp->where('first_name', 'LIKE', "%$search%")
                            ->orWhere('middle_name', 'LIKE', "%$search%")
                            ->orWhere('last_name', 'LIKE', "%$search%");
                    });
            });
        }

        // =========================
        // STATUS FILTER
        // =========================
        if ($request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', $request->status);
        }

        // =========================
        // DATE FILTER
        // =========================
        if ($request->filled('start_date')) {
            $query->whereDate('attendance_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('attendance_date', '<=', $request->end_date);
        }

        $records = $query->orderBy('attendance_date', 'desc')->get();

        // =========================
        // CALCULATE TOTAL HOURS
        // =========================
        $totalMinutes = 0;

        foreach ($records as $attendance) {
            if ($attendance->time_in && $attendance->time_out) {
                $timeIn = Carbon::parse($attendance->time_in);
                $timeOut = Carbon::parse($attendance->time_out);

                $totalMinutes += $timeOut->diffInMinutes($timeIn);
            }
        }

        $totalHoursWorked = round($totalMinutes / 60, 2);

        // =========================
        // MAP DATA
        // =========================
        $employeeAttendance = $records->map(function ($attendance) {

            $employee = $attendance->employee;

            return [
                'id' => $attendance->id,
                'employee_number' => $attendance->employee_number,
                'attendance_date' => $attendance->attendance_date,
                'time_in' => $attendance->time_in,
                'time_out' => $attendance->time_out,
                'status' => $attendance->status,

                'first_name' => $employee->first_name ?? null,
                'middle_name' => $employee->middle_name ?? null,
                'last_name' => $employee->last_name ?? null,

                'full_name' => $employee
                    ? trim(
                        $employee->first_name . ' ' .
                            ($employee->middle_name ? $employee->middle_name . ' ' : '') .
                            $employee->last_name
                    )
                    : null,

                'profile_picture_url' => $employee && $employee->profile_picture
                    ? asset($employee->profile_picture)
                    : null,

                'created_at' => $attendance->created_at,
            ];
        });

        return response()->json([
            'isSuccess' => true,
            'message' => 'Employee Attendance',
            'data' => $employeeAttendance,

            // 🔥 NEW RESPONSE FIELD
            'summary' => [
                'total_hours_worked' => $totalHoursWorked,
                'total_minutes_worked' => $totalMinutes
            ]
        ], 200);
    }


    //Get Employee List
    public function getEmployeeList()
    {
        $employees = Employee::where('is_archived', 0)->get();

        return response()->json([
            'message' => 'Employee List',
            'data' => $employees
        ], 200);
    }

    // =========================
    // CREATE EMPLOYEE
    // =========================
    public function createEmployee(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag_number' => 'nullable|string|max:250',
            'employee_number' => 'required|string|max:50|unique:employees,employee_number',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|string|max:20',
            'birthdate' => 'required|date',
            'email' => 'required|email|max:255|unique:employees,email',
            'contact_number' => 'required|string|max:50',
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'employment_status' => 'required|string|max:50',
            'is_active' => 'nullable|boolean'
        ]);

        $employee = Employee::create($validated);

        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => $employee
        ], 201);
    }

    // =========================
    // UPDATE EMPLOYEE
    // =========================
    public function updateEmployee(Request $request, $id)
    {
        $employee = Employee::where('id', $id)
            ->where('is_archived', 0)
            ->first();

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found or archived'
            ], 404);
        }

        $validated = $request->validate([
            'rfid_tag_number' => 'nullable|string|max:250',
            'employee_number' => 'required|string|max:50|unique:employees,employee_number,' . $id,
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|string|max:20',
            'birthdate' => 'required|date',
            'email' => 'required|email|max:255|unique:employees,email,' . $id,
            'contact_number' => 'required|string|max:50',
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'employment_status' => 'required|string|max:50',
            'is_active' => 'nullable|boolean'
        ]);

        $employee->update($validated);

        return response()->json([
            'message' => 'Employee updated successfully',
            'employee' => $employee
        ], 200);
    }

    // =========================
    // ARCHIVE EMPLOYEE
    // =========================
    public function archiveEmployee($id)
    {
        $employee = Employee::where('id', $id)
            ->where('is_archived', 0)
            ->first();

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found or already archived'
            ], 404);
        }

        $employee->update([
            'is_archived' => 1
        ]);

        return response()->json([
            'message' => 'Employee archived successfully'
        ], 200);
    }
}
