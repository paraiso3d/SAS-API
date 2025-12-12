<?php

namespace App\Http\Controllers;

use App\Models\Students;
use Illuminate\Http\Request;

class StudentsController extends Controller
{
    /**
     * Retrieve all active (non-archived) student records.
     */
    public function getAllStudents()
    {
        $students = Students::where('is_archived', 0)->get();
        return response()->json($students, 200);
    }

    /**
     * Retrieve a single student by ID (only if not archived).
     */
    public function getStudentById($id)
    {
        $student = Students::where('id', $id)->where('is_archived', 0)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or archived'], 404);
        }

        return response()->json($student, 200);
    }
//TEST
    /**
     * Create a new student record.
     */
    public function createStudent(Request $request)
    {
        $validated = $request->validate([
            'rfid_tag_number' => 'nullable|string|max:250',
            'student_number' => 'required|string|max:50|unique:students,student_number',
            'student_status' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
            'course_name' => 'required|string|max:255',
            'section_name' => 'required|string|max:255',
            'school_year' => 'required|string|max:20',
            'semester' => 'required|string|max:50',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|string|max:20',
            'birthdate' => 'required|date',
            'email' => 'required|email|max:255|unique:students,email',
            'contact_number' => 'required|string|max:50',
            'guardian_contact_number' => 'required|string|max:50',
        ]);

        $student = Students::create($validated);

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student
        ], 201);
    }

    /**
     * Update an existing student record.
     */
    public function updateStudent(Request $request, $id)
    {
        $student = Students::where('id', $id)->where('is_archived', 0)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or archived'], 404);
        }

        $validated = $request->validate([
            'rfid_tag_number' => 'nullable|string|max:250',
            'student_number' => 'required|string|max:50|unique:students,student_number,' . $id,
            'student_status' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
            'course_name' => 'required|string|max:255',
            'section_name' => 'required|string|max:255',
            'school_year' => 'required|string|max:20',
            'semester' => 'required|string|max:50',
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|string|max:20',
            'birthdate' => 'required|date',
            'email' => 'required|email|max:255|unique:students,email,' . $id,
            'contact_number' => 'required|string|max:50',
            'guardian_contact_number' => 'required|string|max:50',
        ]);

        $student->update($validated);

        return response()->json([
            'message' => 'Student updated successfully',
            'student' => $student
        ], 200);
    }

    /**
     * Archive a student record instead of deleting.
     */
    public function archiveStudent($id)
    {
        $student = Students::where('id', $id)->where('is_archived', 0)->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found or already archived'], 404);
        }

        $student->update(['is_archived' => 1]);

        return response()->json([
            'message' => 'Student archived successfully'
        ], 200);
    }
}
