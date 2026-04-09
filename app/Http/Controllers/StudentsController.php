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
            'fingerprint_sample' => 'required|string',
            'student_templates' => 'nullable|array',
        ]);
        $sample = $request->fingerprint_sample; // already Base64 from frontend
        // Fetch fingerprints and student info
        $fingerprints = Fingerprint::with('student')->get();
        foreach ($fingerprints as $fingerprint) {
            $storedTemplate = $fingerprint->fingerprint_template;
            // Use SDK for proper comparison
            if (FingerprintSDK::compare($sample, $storedTemplate)) {
                return response()->json([
                    'matched_student' => [
                        'student_number' => $fingerprint->student->student_number,
                        'student_id' => $fingerprint->student->id,
                    ]
                ], 200);
            }
        }
        return response()->json([
            'message' => 'Fingerprint not recognized'
        ], 404);
    }


    // public function getStudentTemplates()
    // {
    //     $templates = Fingerprint::with('student:id,student_number')
    //         ->get()
    //         ->map(function ($f) {
    //             return [
    //                 'student_id' => $f->student_id,
    //                 'student_number' => $f->student->student_number,
    //                 'fingerprint_template' => $f->fingerprint_template,
    //             ];
    //         });

    //     return response()->json($templates, 200);
    // }

    public function getAllStudents()
    {
        $students = Students::where('is_archived', 0)->get();

        //  Attach full URL
        $students->transform(function ($student) {
            if ($student->profile_picture) {
                $student->profile_picture_url = asset($student->profile_picture);
            } else {
                $student->profile_picture_url = null;
            }
            return $student;
        });

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
            'fingerprint_id' => 'nullable|string',
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

            // 🔥 FILE FIELD
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // 🔥 HANDLE FILE
        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = $this->saveFileToPublic(
                $request->file('profile_picture'),
                'student'
            );
        }

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
        $student = Students::where('id', $id)
            ->where('is_archived', 0)
            ->first();

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

            // 🔥 FILE FIELD
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        // 🔥 HANDLE FILE UPDATE
        if ($request->hasFile('profile_picture')) {

            // optional: delete old file
            if ($student->profile_picture && file_exists(public_path($student->profile_picture))) {
                unlink(public_path($student->profile_picture));
            }

            $validated['profile_picture'] = $this->saveFileToPublic(
                $request->file('profile_picture'),
                'student'
            );
        }

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
