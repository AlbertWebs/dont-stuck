<?php

use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\ClientRecordController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisbursementController;
use App\Http\Controllers\Admin\LoanProductController;
use App\Http\Controllers\Admin\LoanTermsSettingController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PortfolioController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WebsitePageController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Client\ApplicationController as ClientApplicationController;
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DocumentController as ClientDocumentController;
use App\Http\Controllers\Client\LoanController as ClientLoanController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\PublicSiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('/about', [PublicSiteController::class, 'about'])->name('about');
Route::get('/loans', [PublicSiteController::class, 'products'])->name('products');
Route::get('/contact', [PublicSiteController::class, 'contact'])->name('contact');
Route::get('/sitemap.xml', fn () => response()->view('public.sitemap')->header('Content-Type', 'application/xml'))->name('sitemap');
Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /client/\nDisallow: /login\n\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain']))->name('robots');
Route::post('/contact', [PublicSiteController::class, 'submitContact'])->middleware('throttle:contact')->name('contact.submit');
Route::get('/apply/quote', [LoanApplicationController::class, 'quote'])->name('apply.quote');
Route::get('/apply', [LoanApplicationController::class, 'create'])->name('apply');
Route::post('/apply', [LoanApplicationController::class, 'store'])->middleware('throttle:loan-applications')->name('apply.store');
Route::get('/application-received/{reference}', [LoanApplicationController::class, 'received'])
    ->where('reference', 'DSS-[0-9]{8}-[A-Z0-9]{8}')
    ->name('apply.received');

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->middleware('guest')->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('guest')->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::prefix('client')->name('client.')->group(function (): void {
    Route::get('/login', [ClientAuthController::class, 'create'])->name('login');
    Route::get('/register', [ClientAuthController::class, 'registrationForm'])->name('register');
    Route::post('/register', [ClientAuthController::class, 'register'])->middleware(['guest', 'throttle:client-register'])->name('register.store');
    Route::post('/login/request-code', [ClientAuthController::class, 'requestCode'])->middleware(['guest', 'throttle:client-otp-request'])->name('login.request-code');
    Route::post('/login/verify', [ClientAuthController::class, 'verifyCode'])->middleware(['guest', 'throttle:client-otp-verify'])->name('login.verify');

    Route::middleware(['auth', 'active', 'client'])->group(function (): void {
        Route::post('/logout', [ClientAuthController::class, 'destroy'])->name('logout');
        Route::get('/', [ClientDashboardController::class, 'index'])->name('dashboard');
        Route::get('/applications', [ClientApplicationController::class, 'index'])->name('applications.index');
        Route::post('/applications/{application}/documents', [ClientApplicationController::class, 'uploadDocument'])
            ->middleware('throttle:client-document-upload')
            ->name('applications.documents.store');
        Route::post('/documents/applications/{application}', [ClientApplicationController::class, 'uploadDocument'])
            ->middleware('throttle:client-document-upload')
            ->name('documents.upload');
        Route::get('/documents', [ClientDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}', [ClientDocumentController::class, 'download'])->name('documents.show');
        Route::get('/loans', [ClientLoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/{loanAccount}', [ClientLoanController::class, 'show'])->name('loans.show');
        Route::get('/profile', [ClientProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [ClientProfileController::class, 'update'])->name('profile.update');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::get('/applications', [AdminApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/assign', [AdminApplicationController::class, 'assign'])->name('applications.assign');
    Route::post('/applications/{application}/review', [AdminApplicationController::class, 'review'])->name('applications.review');
    Route::post('/applications/{application}/transition', [AdminApplicationController::class, 'transition'])->name('applications.transition');
    Route::post('/applications/{application}/disbursement', [DisbursementController::class, 'store'])->name('applications.disbursement');
    Route::get('/documents/{document}', [AdminApplicationController::class, 'document'])->name('documents.show');
    Route::post('/loans/{loanAccount}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('/borrowers', [BorrowerController::class, 'index'])->name('borrowers.index');
    Route::get('/clients', [ClientRecordController::class, 'index'])->name('clients.index');
    Route::get('/clients/{borrower}', [ClientRecordController::class, 'show'])->name('clients.show');
    Route::get('/clients/{borrower}/export', [ClientRecordController::class, 'export'])->name('clients.export');
    Route::get('/clients/{borrower}/export/pdf', [ClientRecordController::class, 'pdf'])->name('clients.export.pdf');
    Route::get('/loans', [PortfolioController::class, 'loans'])->name('loans.index');
    Route::get('/repayments', [PortfolioController::class, 'repayments'])->name('repayments.index');
    Route::get('/overdue', [PortfolioController::class, 'overdue'])->name('overdue.index');
    Route::get('/products', [LoanProductController::class, 'index'])->name('products.index');
    Route::put('/products/{product}', [LoanProductController::class, 'update'])->name('products.update');
    Route::get('/loan-terms', [LoanTermsSettingController::class, 'edit'])->name('loan-terms.edit');
    Route::put('/loan-terms', [LoanTermsSettingController::class, 'update'])->name('loan-terms.update');
    Route::get('/website-content', [WebsitePageController::class, 'index'])->name('website-pages.index');
    Route::put('/website-content/{pageSlug}', [WebsitePageController::class, 'update'])->name('website-pages.update');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::get('/reports', [ReportExportController::class, 'index'])->name('reports');
    Route::get('/reports/excel', [ReportExportController::class, 'excel'])->name('reports.excel');
    Route::get('/reports/pdf', [ReportExportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/applications.csv', [DashboardController::class, 'exportApplicationsCsv'])->name('reports.applications.csv');
});
