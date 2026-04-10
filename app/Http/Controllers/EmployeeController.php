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
        $employeeAttendance = EmployeeAttendance::with('employee')->get();

        return response()->json([
            'message' => 'Employee Attendance',
            'data' => $employeeAttendance
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
