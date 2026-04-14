<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeAttendance;

class EmployeeController extends Controller
{

    //Employee Attendace
    public function getEmployeeAttendance(Request $request)
    {
        $employeeAttendance = EmployeeAttendance::with('employee')
            ->get()
            ->map(function ($attendance) {

                $employee = $attendance->employee;

                return [
                    'id' => $attendance->id,
                    'employee_number' => $attendance->employee_number,
                    'attendance_date' => $attendance->attendance_date,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                    'status' => $attendance->status,

                    // 🔥 Employee Info
                    'first_name' => $employee->first_name ?? null,
                    'middle_name' => $employee->middle_name ?? null,
                    'last_name' => $employee->last_name ?? null,

                    'full_name' => $employee
                        ? trim($employee->first_name . ' ' . ($employee->middle_name ? $employee->middle_name . ' ' : '') . $employee->last_name)
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
            'data' => $employeeAttendance
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
