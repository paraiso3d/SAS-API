<?php

namespace App\Http\Controllers;

use App\Models\Students;
use Illuminate\Http\Request;
use App\Models\Fingerprint;
use App\Models\StudentAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\FingerprintSDK;

class StudentsController extends Controller
{
    /**
     * Retrieve all active (non-archived) student records.
     */
    public function verifyFingerprint(Request $request)
    {
        $request->validate([
            'fingerprint_sample' => 'required',
            'student_templates' => 'required|array',
        ]);

        // 🔥 Encode incoming sample to match stored templates (Base64)
        $sample = base64_encode($request->fingerprint_sample);

        foreach ($request->student_templates as $template) {

            // ✅ Prevent undefined array errors
            if (
                !isset($template['fingerprint_template']) ||
                !isset($template['student_number'])
            ) {
                continue;
            }

            $storedTemplate = $template['fingerprint_template'];

            // 🔥 Compare
            if (FingerprintSDK::compare($sample, $storedTemplate)) {
                return response()->json([
                    'matched_student' => [
                        'student_number' => $template['student_number'],
                        'fingerprint_template' => $storedTemplate
                    ]
                ], 200);
            }
        }

        return response()->json([
            'message' => 'Fingerprint not recognized'
        ], 404);
    }
    public function getStudentTemplates()
    {
        $templates = Fingerprint::with('student:id,student_number')
            ->get()
            ->map(function ($f) {
                return [
                    'student_id' => $f->student_id,
                    'student_number' => $f->student->student_number,
                    'fingerprint_template' => $f->fingerprint_template,
                ];
            });

        return response()->json($templates, 200);
    }

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

    public function setFingerprint(Request $request)
    {
        $validated = $request->validate([
            'fingerprint_id' => 'required|string|unique:fingerprints,fingerprint_id'
        ]);

        $fingerprint = Fingerprint::create([
            'fingerprint_id' => $validated['fingerprint_id']
        ]);

        return response()->json([
            'message' => 'Fingerprint saved successfully',
            'data' => $fingerprint
        ], 201);
    }


    /**
     * Create a new student record.
     */
    public function createStudent(Request $request)
    {
        $validated = $request->validate([
            'fingerprint_id' => 'required|string',
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

        $fingerprintRaw = $validated['fingerprint_id'];

        // Validate raw scan
        if (!FingerprintSDK::isValidScan($fingerprintRaw)) {
            return response()->json([
                'message' => 'Invalid fingerprint scan. Please try again.'
            ], 422);
        }

        // Create the student (without fingerprint_id in students table)
        unset($validated['fingerprint_id']); // remove before saving
        $student = Students::create($validated);

        // Generate and save fingerprint template in separate table
        Fingerprint::create([
            'student_id' => $student->id,
            'fingerprint_template' => FingerprintSDK::createTemplate($fingerprintRaw),
        ]);

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
