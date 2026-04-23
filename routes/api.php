<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashBoardController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\StudentAttendanceController;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Dashboard Route
Route::get('/dashboard', [DashBoardController::class, 'getDashboardData']);

//Auth
Route::post('/auth/create-admin', [AuthController::class, 'createAdmin']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');


//Students Routes
Route::get('/students', [StudentsController::class, 'getAllStudents']);
Route::get('/students/{id}', [StudentsController::class, 'getStudentById']);
Route::post('create/students', [StudentsController::class, 'createStudent']);
Route::post('update/students/{id}', [StudentsController::class, 'updateStudent']);
Route::post('archive/students/{id}', [StudentsController::class, 'archiveStudent']);
Route::get('/student-templates', [StudentsController::class, 'getStudentTemplates']);
Route::post('/verify-fingerprint', [StudentsController::class, 'verifyFingerprint']);
//Student Attendance Routes
Route::get('/attendance/recent', [StudentAttendanceController::class, 'getrecentattendance']);
Route::post('/attendance/time-in', [StudentAttendanceController::class, 'tapRFID']);
Route::post('/attendance/time-out', [StudentAttendanceController::class, 'timeOut']);
Route::get('/attendance/{rfid_tag_number}', [StudentAttendanceController::class, 'getAttendanceByRfid']);
Route::get('/attendance/today', [StudentAttendanceController::class, 'getTodayAttendance']);
Route::get('/attendance', [StudentAttendanceController::class, 'getAttendaces']);

//Fingerprint test
Route::post('/set-fingerprint', [StudentsController::class, 'setFingerprint']);


//Announcements Routes
Route::get('/announcements', [AnnouncementController::class, 'getannouncements']);
Route::post('/announcements', [AnnouncementController::class, 'createAnnouncement']);
Route::post('/announcements/update/{id}', [AnnouncementController::class, 'updateAnnouncement']);
Route::get('/announcements/admin', [AnnouncementController::class, 'getannouncementsAdmin']);
Route::post('/announcements/archive/{id}', [AnnouncementController::class, 'archiveAnnouncement']);

//Employee Routes
Route::get('/employee/attendance', [EmployeeController::class, 'getEmployeeAttendance']);
Route::get('/employees', [EmployeeController::class, 'getEmployeeList']);
Route::post('/employee/create', [EmployeeController::class, 'createEmployee']);
Route::post('/employee/update/{id}', [EmployeeController::class, 'updateEmployee']);
Route::post('/employee/archive/{id}', [EmployeeController::class, 'archiveEmployee']);
