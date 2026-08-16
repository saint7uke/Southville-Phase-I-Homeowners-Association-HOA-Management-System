<?php

use App\Http\Controllers\Admin\PaymentExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Portal\AnnouncementController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\ComplaintAttachmentController;
use App\Http\Controllers\Portal\ComplaintController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\RegisterController;
use App\Http\Controllers\Portal\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->prefix('portal')->name('portal.')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->prefix('portal')->name('portal.')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::view('/status', 'portal.auth.status')->name('status');

    Route::middleware('homeowner.approved')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::resource('complaints', ComplaintController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('/complaints/{complaint}/attachment', ComplaintAttachmentController::class)->name('complaints.attachment');
        Route::resource('requests', ServiceRequestController::class)->parameters(['requests' => 'serviceRequest'])->only(['index', 'create', 'store', 'show']);
        Route::resource('announcements', AnnouncementController::class)->only(['index', 'show']);
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});

Route::get('/admin/reports/payments.xlsx', PaymentExportController::class)->middleware(['auth', 'role:hoa_admin', 'permission:export_reports', 'throttle:5,1'])->name('admin.reports.payments');
Route::get('/staff/reports/payments.xlsx', PaymentExportController::class)->middleware(['auth', 'role:hoa_staff', 'permission:export_reports', 'throttle:5,1'])->name('staff.reports.payments');
