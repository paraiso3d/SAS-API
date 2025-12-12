<?php

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

//Students Routes
Route::get('/students', [StudentsController::class, 'getAllStudents']);
Route::get('/students/{id}', [StudentsController::class, 'getStudentById']);
Route::post('create/students', [StudentsController::class, 'createStudent']);
Route::post('update/students/{id}', [StudentsController::class, 'updateStudent']);
Route::post('archive/students/{id}', [StudentsController::class, 'archiveStudent']);

//Student Attendance Routes
Route::post('/attendance/time-in', [StudentAttendanceController::class, 'timeIn']);
Route::post('/attendance/time-out', [StudentAttendanceController::class, 'timeOut']);
Route::get('/attendance/{rfid_tag_number}', [StudentAttendanceController::class, 'getAttendanceByRfid']);
Route::get('/attendance/today', [StudentAttendanceController::class, 'getTodayAttendance']);
