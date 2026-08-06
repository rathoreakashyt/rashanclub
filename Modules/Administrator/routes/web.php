<?php

use Illuminate\Support\Facades\Route;
use Modules\Administrator\Http\Controllers\AdministratorController;
use Modules\Administrator\Http\Controllers\RoleController;
use Modules\Administrator\Http\Controllers\UserController;
use Modules\Administrator\Http\Controllers\AttendanceController;
use Modules\Administrator\Http\Controllers\SalaryController;
use Modules\Administrator\Http\Controllers\EmployeeAdvancePaymentController;
use Modules\Administrator\Models\Role;

Route::middleware(['auth'])->group(function () {
    Route::resource('administrators', AdministratorController::class)->names('administrator');

    // Role Controller
    Route::controller(RoleController::class)->group(function () {
        Route::get('role', 'index')->name('role.index')->middleware('permission:role-list');
        Route::get('role/create', 'create')->name('role.create')->middleware('permission:role-create');
        Route::post('role', 'store')->name('role.store')->middleware('permission:role-create');
        Route::get('role/{role}', 'show')->name('role.show')->middleware('permission:role-show');
        Route::get('role/{role}/edit', 'edit')->name('role.edit')->middleware('permission:role-edit');
        Route::put('role/{role}', 'update')->name('role.update')->middleware('permission:role-edit');
        Route::delete('role/{role}', 'destroy')->name('role.destroy')->middleware('permission:role-destroy');
    });

    // User Controller
    Route::controller(UserController::class)->group(function () {
        Route::get('user', 'index')->name('user.index')->middleware('permission:user-list');
        Route::get('user/create', 'create')->name('user.create')->middleware('permission:user-create');
        Route::post('user', 'store')->name('user.store')->middleware('permission:user-create');
        Route::get('user/{user}', 'show')->name('user.show')->middleware('permission:user-show');
        Route::get('user/{user}/edit', 'edit')->name('user.edit')->middleware('permission:user-edit');
        Route::put('user/{user}', 'update')->name('user.update')->middleware('permission:user-edit');
        Route::delete('user/{user}', 'destroy')->name('user.destroy')->middleware('permission:user-destroy');
        
        // Profile update routes (no permission check needed as user can only update their own profile)
        Route::get('user/profile/update', 'updateProfile')->name('user.update-profile')->middleware('permission:user-edit');
        Route::put('user/profile/update', 'updateProfileStore')->name('user.update-profile')->middleware('permission:user-edit');
    });

    // Attendance Controller
    Route::controller(AttendanceController::class)->group(function () {
        Route::get('attendance', 'index')->name('attendance.index')->middleware('permission:attendance-list');
        Route::get('attendance/create', 'create')->name('attendance.create')->middleware('permission:attendance-create');
        Route::post('attendance', 'store')->name('attendance.store')->middleware('permission:attendance-create');
        Route::get('attendance/{attendance}', 'show')->name('attendance.show')->middleware('permission:attendance-show');
        Route::get('attendance/{attendance}/edit', 'edit')->name('attendance.edit')->middleware('permission:attendance-edit');
        Route::put('attendance/{attendance}', 'update')->name('attendance.update')->middleware('permission:attendance-edit');
        Route::delete('attendance/{attendance}', 'destroy')->name('attendance.destroy')->middleware('permission:attendance-destroy');
    });

    // Salary Controller
    Route::controller(SalaryController::class)->group(function () {
        Route::get('salary', 'index')->name('salary.index')->middleware(['permission:salary-list', 'outlet_set', 'register_open']);
        Route::get('salary/advances-by-month', 'advancesByMonth')->name('salary.advances-by-month')->middleware(['permission:salary-create|salary-edit', 'outlet_set', 'register_open']);
        Route::get('salary/create', 'create')->name('salary.create')->middleware(['permission:salary-create', 'outlet_set', 'register_open']);
        Route::post('salary', 'store')->name('salary.store')->middleware(['permission:salary-create', 'outlet_set', 'register_open']);
        Route::get('salary/{salary}', 'show')->name('salary.show')->middleware(['permission:salary-show', 'outlet_set']);
        Route::get('salary/{salary}/edit', 'edit')->name('salary.edit')->middleware(['permission:salary-edit', 'outlet_set']);
        Route::put('salary/{salary}', 'update')->name('salary.update')->middleware(['permission:salary-edit', 'outlet_set']);
        Route::delete('salary/{salary}', 'destroy')->name('salary.destroy')->middleware(['permission:salary-destroy', 'outlet_set']);
    });

    // Employee Advance Payment Controller
    Route::controller(EmployeeAdvancePaymentController::class)->group(function () {
        Route::get('employee-advance-payment', 'index')->name('employee-advance-payment.index')->middleware(['permission:employee_advance_payment-list', 'outlet_set', 'register_open']);
        Route::get('employee-advance-payment/create', 'create')->name('employee-advance-payment.create')->middleware(['permission:employee_advance_payment-create', 'outlet_set', 'register_open']);
        Route::post('employee-advance-payment', 'store')->name('employee-advance-payment.store')->middleware(['permission:employee_advance_payment-create', 'outlet_set', 'register_open']);
        Route::get('employee-advance-payment/{employee_advance_payment}', 'show')->name('employee-advance-payment.show')->middleware(['permission:employee_advance_payment-show', 'outlet_set']);
        Route::get('employee-advance-payment/{employee_advance_payment}/edit', 'edit')->name('employee-advance-payment.edit')->middleware(['permission:employee_advance_payment-edit', 'outlet_set']);
        Route::put('employee-advance-payment/{employee_advance_payment}', 'update')->name('employee-advance-payment.update')->middleware(['permission:employee_advance_payment-edit', 'outlet_set']);
        Route::delete('employee-advance-payment/{employee_advance_payment}', 'destroy')->name('employee-advance-payment.destroy')->middleware(['permission:employee_advance_payment-destroy', 'outlet_set']);
    });
});
