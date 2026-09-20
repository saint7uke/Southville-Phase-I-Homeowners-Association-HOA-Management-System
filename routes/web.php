<?php

use App\Http\Controllers\Admin\AuditLogExportController;
use App\Http\Controllers\Admin\HoaReportController;
use App\Http\Controllers\Admin\PaymentExportController;
use App\Http\Controllers\Admin\QueuedReportDownloadController;
use App\Http\Controllers\BrandingLogoController;
use App\Http\Controllers\CaseAttachmentController;
use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HomeownerProfilePhotoController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\Portal\AnnouncementController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\ComplaintAttachmentController;
use App\Http\Controllers\Portal\ComplaintController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EmailVerificationController;
use App\Http\Controllers\Portal\PaymentProofController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\RegisterController;
use App\Http\Controllers\Portal\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/branding/logo', BrandingLogoController::class)->middleware('throttle:120,1')->name('branding.logo');
Route::post('/contact', ContactMessageController::class)->middleware('throttle:5,1')->name('contact.store');

Route::middleware('guest')->prefix('portal')->name('portal.')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/portal/status/resend-verification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth', 'throttle:3,1'])
    ->name('portal.verification.resend');

Route::middleware(['auth', 'portal.auth.session'])->prefix('portal')->name('portal.')->group(function (): void {
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
        Route::patch('/profile/password', [ProfileController::class, 'changePassword'])->middleware('throttle:5,1')->name('profile.password');
        Route::post('/dues-obligations/{duesObligation}/proof', [PaymentProofController::class, 'store'])->middleware('throttle:5,1')->name('dues-obligations.proof.store');
    });
});

Route::get('/portal/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['profile-photo.auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/homeowner-profile-photos/{homeowner}', HomeownerProfilePhotoController::class)
    ->middleware(['profile-photo.auth', 'throttle:60,1'])
    ->name('homeowner.profile-photo');

Route::get('/payments/{payment}/proof', [PaymentProofController::class, 'download'])
    ->middleware(['profile-photo.auth', 'throttle:60,1'])
    ->name('portal.payments.proof.download');
Route::get('/payments/{payment}/receipt', PaymentReceiptController::class)
    ->middleware(['profile-photo.auth', 'throttle:30,1'])
    ->name('payments.receipt.download');
Route::get('/certificates/{certificate}/download', CertificateDownloadController::class)
    ->middleware(['profile-photo.auth', 'signed', 'throttle:30,1'])
    ->name('certificates.download');
Route::get('/case-attachments/{caseAttachment}', CaseAttachmentController::class)
    ->middleware(['profile-photo.auth', 'throttle:60,1'])
    ->name('case-attachments.download');

Route::get('/admin/reports/payments.xlsx', PaymentExportController::class)->middleware(['auth:admin,staff,web', 'role:hoa_admin', 'permission:export_reports', 'throttle:5,1'])->name('admin.reports.payments');
Route::get('/admin/reports/audit-logs.csv', AuditLogExportController::class)->middleware(['auth:admin,staff,web', 'role:hoa_admin', 'permission:view_audit_logs', 'throttle:5,1'])->name('admin.reports.audit-logs');
Route::get('/admin/reports/queued/{reportExport}', QueuedReportDownloadController::class)->middleware(['auth:admin,staff,web', 'role:hoa_admin', 'permission:export_reports', 'throttle:10,1'])->name('admin.reports.queued.download');
Route::get('/admin/reports/{report}.{format}', HoaReportController::class)->middleware(['auth:admin,staff,web', 'role:hoa_admin', 'permission:export_reports', 'throttle:5,1'])->whereIn('format', ['csv', 'pdf'])->name('admin.reports.download');
Route::get('/admin/reports/{report}/preview', HoaReportController::class)->middleware(['auth:admin,staff,web', 'role:hoa_admin', 'permission:export_reports', 'throttle:10,1'])->name('admin.reports.preview');
Route::get('/staff/reports/payments.xlsx', PaymentExportController::class)->middleware(['auth:staff,admin,web', 'role:hoa_staff', 'permission:export_reports', 'throttle:5,1'])->name('staff.reports.payments');
Route::get('/staff/reports/queued/{reportExport}', QueuedReportDownloadController::class)->middleware(['auth:staff,admin,web', 'role:hoa_staff', 'permission:export_reports', 'throttle:10,1'])->name('staff.reports.queued.download');
Route::get('/staff/reports/{report}.{format}', HoaReportController::class)->middleware(['auth:staff,admin,web', 'role:hoa_staff', 'permission:export_reports', 'throttle:5,1'])->whereIn('format', ['csv', 'pdf'])->name('staff.reports.download');
Route::get('/staff/reports/{report}/preview', HoaReportController::class)->middleware(['auth:staff,admin,web', 'role:hoa_staff', 'permission:export_reports', 'throttle:10,1'])->name('staff.reports.preview');
