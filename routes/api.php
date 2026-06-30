<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SchoolAdminAuthController;
use App\Http\Controllers\Api\V1\Auth\ParentLookupController;
use App\Http\Controllers\Api\V1\PromoController;
use App\Http\Controllers\Api\V1\DaftarController;
use App\Http\Controllers\Api\V1\SchoolStudentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Bayar\PaymentController;
use App\Http\Controllers\Api\V1\Canvas\SchoolController;
use App\Http\Controllers\Api\V1\Bayar\PaymentVerificationController;
use App\Http\Controllers\Api\V1\Murid\AttendanceController;
use App\Http\Controllers\Api\V1\Murid\ProgressController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\StudentController;
use App\Http\Controllers\Api\V1\Admin\ClassController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\DiscountCodeController;
use App\Http\Controllers\Api\V1\ArticlePublicController;
use App\Http\Controllers\Api\V1\Admin\ArticleController;
use App\Http\Controllers\Api\V1\Karyawan\EmployeeAttendanceController;
use App\Http\Controllers\Api\V1\Murid\EReportController;
use App\Http\Controllers\Api\V1\Landing\LandingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\Sekolah\SchoolPortalController;
use App\Http\Controllers\Api\V1\Sekolah\SchoolPaymentController;

Route::prefix('v1')->group(function () {

    /* ---------- PUBLIK ---------- */


    Route::get('landing', [LandingController::class, 'index']);
    Route::get('landing/{section}', [LandingController::class, 'show']);
    // Auth internal (admin.robotiku.id)
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Auth Admin Sekolah (robotiku.id)
    Route::post('auth/school-admin/login', [SchoolAdminAuthController::class, 'login'])->middleware('throttle:login');

    // Lookup Orang Tua — passwordless, tanpa token
    Route::post('auth/parent/lookup', [ParentLookupController::class, 'lookup'])->middleware('throttle:login');

    // Daftar mandiri + cek promo
    Route::post('promo/check', [PromoController::class, 'check'])->middleware('throttle:api');
    Route::post('daftar', [DaftarController::class, 'mandiri'])->middleware('throttle:api');

    Route::post('bayar/tagihan', [PaymentController::class, 'parentTagihan'])->middleware('throttle:api');
    Route::post('bayar/upload', [PaymentController::class, 'parentUpload'])->middleware('throttle:api');

    Route::post('murid/progress', [ProgressController::class, 'parent'])->middleware('throttle:api');

    Route::post('e-rapot/parent', [EReportController::class, 'parentList'])->middleware('throttle:api');
    Route::post('e-rapot/{eReport}/parent-pdf', [EReportController::class, 'parentPdf'])->middleware('throttle:api');

    Route::get('artikel', [ArticlePublicController::class, 'index']);
    Route::get('artikel/{slug}', [ArticlePublicController::class, 'show']);


    Route::get('programs', [ProgramController::class, 'index'])->middleware('throttle:api');

    /* ---------- TERPROTEKSI (butuh token) ---------- */
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        Route::get('notifikasi', [NotificationController::class, 'index']);
        Route::get('notifikasi/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifikasi/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifikasi/{notification}/read', [NotificationController::class, 'markRead']);
        // Umum (internal & school admin)
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/school-admin/logout', [SchoolAdminAuthController::class, 'logout']);
        Route::get('sekolah/mou', [SchoolController::class, 'mou']);



        Route::get('sekolah/pembayaran', [SchoolPaymentController::class, 'index']);
        Route::post('sekolah/pembayaran/upload', [SchoolPaymentController::class, 'collectiveUpload']);

        Route::get('sekolah/dashboard', [SchoolPortalController::class, 'kpi']);
        Route::get('sekolah/murid', [SchoolPortalController::class, 'students']);
        // Admin Sekolah — daftar murid (controller cek instanceof SchoolAdmin)
        Route::post('sekolah/murid', [SchoolStudentController::class, 'store']);
        Route::post('sekolah/murid/preview-excel', [SchoolStudentController::class, 'previewExcel']);
        Route::post('sekolah/murid/import-excel', [SchoolStudentController::class, 'importExcel']);

        Route::get('bayar/sekolah/invoices', [PaymentController::class, 'schoolInvoices']);
        Route::post('bayar/sekolah/invoices/{invoice}/upload', [PaymentController::class, 'schoolUpload']);

        // Contoh route khusus role internal (placeholder uji RBAC)
        Route::get('ping/internal', fn() => response()->json(['status' => true, 'data' => 'pong', 'message' => 'OK']))
            ->middleware('role:super_admin,admin');

        Route::middleware('role:marketing,admin,super_admin')->group(function () {
            Route::get('canvas/schools', [SchoolController::class, 'index']);
            Route::post('canvas/schools', [SchoolController::class, 'store']);
            Route::get('canvas/schools/{school}', [SchoolController::class, 'show']);
            Route::patch('canvas/schools/{school}/status', [SchoolController::class, 'changeStatus']);
            Route::post('canvas/schools/{school}/notes', [SchoolController::class, 'addNote']);
        });

        Route::middleware('role:admin_keuangan,admin,super_admin')->group(function () {
            Route::get('bayar/payments', [PaymentVerificationController::class, 'index']);
            Route::get('bayar/payments/{payment}/proof', [PaymentVerificationController::class, 'proof']);
            Route::post('bayar/payments/{payment}/verify', [PaymentVerificationController::class, 'verify']);
            Route::get('bayar/invoices/{invoice}/wa', [PaymentVerificationController::class, 'waLink']);
        });

        Route::middleware('role:trainer')->group(function () {
            Route::get('murid', [AttendanceController::class, 'students']);
            Route::post('absensi', [AttendanceController::class, 'store']);
            Route::post('absensi-karyawan', [EmployeeAttendanceController::class, 'store']);
            Route::get('absensi-karyawan/today', [EmployeeAttendanceController::class, 'today']);
        });

        Route::get('sekolah/murid/{student}/progress', [ProgressController::class, 'school']);

        Route::middleware('role:trainer,admin,super_admin')->group(function () {
            Route::get('manajemen/murid/{student}/progress', [ProgressController::class, 'internal']);
            Route::get('e-rapot', [EReportController::class, 'index']);
            Route::post('e-rapot', [EReportController::class, 'store']);
            Route::get('e-rapot/{eReport}', [EReportController::class, 'show']);
            Route::put('e-rapot/{eReport}', [EReportController::class, 'update']);
            Route::get('e-rapot/{eReport}/pdf', [EReportController::class, 'pdf']);
        });

        Route::middleware('role:admin,super_admin')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::put('landing/{section}', [LandingController::class, 'update']);
            Route::post('landing-upload', [LandingController::class, 'upload']);
            Route::get('siswa/export/excel', [StudentController::class, 'exportExcel']);
            Route::get('siswa/export/pdf', [StudentController::class, 'exportPdf']);
            Route::get('siswa', [StudentController::class, 'index']);
            Route::get('siswa/{student}', [StudentController::class, 'show']);
            Route::patch('siswa/{student}/status', [StudentController::class, 'changeStatus']);
            Route::put('canvas/schools/{school}', [SchoolController::class, 'update']);
            Route::get('kelas', [ClassController::class, 'index']);
            Route::post('kelas', [ClassController::class, 'store']);
            Route::get('kelas/{kelas}', [ClassController::class, 'show']);
            Route::put('kelas/{kelas}', [ClassController::class, 'update']);
            Route::post('kelas/{kelas}/murid', [ClassController::class, 'assignStudents']);
            Route::delete('kelas/{kelas}/murid/{studentId}', [ClassController::class, 'removeStudent']);
            Route::put('kelas/{kelas}/harga', [ClassController::class, 'setBilling']);
            Route::get('admin/artikel', [ArticleController::class, 'index']);
            Route::post('admin/artikel', [ArticleController::class, 'store']);
            Route::get('admin/artikel/{article}', [ArticleController::class, 'show']);
            Route::put('admin/artikel/{article}', [ArticleController::class, 'update']);
            Route::delete('admin/artikel/{article}', [ArticleController::class, 'destroy']);
            Route::get('absensi-karyawan/rekap', [EmployeeAttendanceController::class, 'index']);
            Route::delete('absensi-karyawan/{employeeAttendance}', [EmployeeAttendanceController::class, 'destroy']);
            Route::get('promo', [DiscountCodeController::class, 'index']);
            Route::post('promo', [DiscountCodeController::class, 'store']);
            Route::get('promo/{discountCode}', [DiscountCodeController::class, 'show']);
            Route::put('promo/{discountCode}', [DiscountCodeController::class, 'update']);
            Route::delete('promo/{discountCode}', [DiscountCodeController::class, 'destroy']);
        });

        Route::middleware('role:super_admin')->group(function () {
            Route::get('akun', [UserController::class, 'index']);
            Route::post('akun', [UserController::class, 'store']);
            Route::put('akun/{user}', [UserController::class, 'update']);
            Route::patch('akun/{user}/password', [UserController::class, 'resetPassword']);
            Route::patch('akun/{user}/status', [UserController::class, 'toggleActive']);
        });
    });
});
