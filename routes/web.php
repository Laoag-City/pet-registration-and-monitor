<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DewormingController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\VaccinationController;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/features', function () {
    return view('features');
})->name('features');

Route::get('/how-it-works', function () {
    return view('how-it-works');
})->name('how-it-works');

// Guest (unauthenticated) routes
// Only name fields are public. Existing /pets/{pet} links remain protected.
Route::get('/scan/{reference?}', [QRCodeController::class, 'scan'])
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ])
    ->where('reference', '.*')->name('qrcodes.scan');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1')->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

// Shared authenticated routes (read-only for pet owners, richer for staff/admin)
Route::middleware(['auth', 'auth.session', \App\Http\Middleware\ShareReminderCount::class])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/mypets', [PetController::class, 'index'])->name('pets.index');
    Route::get('/pets/{pet}', [PetController::class, 'show'])->name('pets.show');
    Route::get('/vaccinations', [VaccinationController::class, 'index'])->name('vaccinations.index');
    Route::get('/deworming', [DewormingController::class, 'index'])->name('deworming.index');
    Route::get('/qrcodes', [QRCodeController::class, 'index'])->name('qrcodes.index');
    Route::get('/qrcodes/{pet}/download', [QRCodeController::class, 'show'])->name('qrcodes.download');
    Route::get('/qrcodes/{pet}/preview', [QRCodeController::class, 'preview'])->name('qrcodes.preview');
});

// Self-service personal details for regular pet owners.
Route::middleware(['auth', 'auth.session', 'role:user', \App\Http\Middleware\ShareReminderCount::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
});

// OCV Staff routes (staff + admin)
Route::middleware(['auth', 'auth.session', 'role:staff,admin'])->prefix('staff')->group(function () {
    foreach ([
        'animal-health-monitoring' => \App\Http\Controllers\AnimalHealthMonitoringController::class,
        'routine-services' => \App\Http\Controllers\RoutineServiceController::class,
    ] as $module => $controller) {
        Route::get('/'.$module.'/export', [$controller, 'export'])->name('staff.'.$module.'.export');
        Route::get('/'.$module.'/{record}/delete', [$controller, 'confirmDelete'])->whereNumber('record')->name('staff.'.$module.'.delete');
        Route::resource($module, $controller)->parameters([$module => 'record'])->names('staff.'.$module)->whereNumber('record');
    }
    Route::get('/dashboard', [StaffController::class, 'dashboard'])->name('staff.dashboard');
    Route::get('/users', [StaffController::class, 'users'])->name('staff.users');
    Route::get('/users/{user}', [StaffController::class, 'userDetails'])->name('staff.users.show');
    Route::get('/pets', [StaffController::class, 'allPets'])->name('staff.pets');
    Route::get('/pets/create', [StaffController::class, 'createPet'])->name('staff.pets.create');
    Route::post('/pets', [StaffController::class, 'storePet'])->name('staff.pets.store');
    Route::get('/pets/{pet}/edit', [StaffController::class, 'editPet'])->name('staff.pets.edit');
    Route::get('/pets/{pet}/remove', [StaffController::class, 'confirmRemoval'])->name('staff.pets.remove');
    Route::delete('/pets/{pet}', [StaffController::class, 'destroyPet'])->name('staff.pets.destroy');
    Route::put('/pets/{pet}', [StaffController::class, 'updatePet'])->name('staff.pets.update');
    Route::post('/vaccinations', [VaccinationController::class, 'store'])->name('vaccinations.store');
    Route::get('/vaccinations/{vaccination}/delete', [\App\Http\Controllers\MedicalDeletionController::class, 'vaccination'])->name('vaccinations.delete');
    Route::delete('/vaccinations/{vaccination}', [\App\Http\Controllers\MedicalDeletionController::class, 'destroyVaccination'])->name('vaccinations.destroy');
    Route::post('/vaccinations/{vaccination}/done', [\App\Http\Controllers\MedicalCompletionController::class, 'vaccination'])->name('vaccinations.done');
    Route::get('/vaccinations/export', [VaccinationController::class, 'export'])->name('vaccinations.export');
    Route::get('/vaccinations/create', [VaccinationController::class, 'create'])->name('vaccinations.create');
    Route::post('/vaccinations/review', [VaccinationController::class, 'review'])->name('vaccinations.review');
    Route::get('/vaccinations/{vaccination}/edit', [VaccinationController::class, 'edit'])->name('vaccinations.edit');
    Route::put('/vaccinations/{vaccination}', [VaccinationController::class, 'update'])->name('vaccinations.update');
    Route::post('/deworming', [DewormingController::class, 'store'])->name('deworming.store');
    Route::get('/deworming/{deworming}/delete', [\App\Http\Controllers\MedicalDeletionController::class, 'deworming'])->name('deworming.delete');
    Route::delete('/deworming/{deworming}', [\App\Http\Controllers\MedicalDeletionController::class, 'destroyDeworming'])->name('deworming.destroy');
    Route::post('/deworming/{deworming}/done', [\App\Http\Controllers\MedicalCompletionController::class, 'deworming'])->name('deworming.done');
    Route::get('/deworming/export', [DewormingController::class, 'export'])->name('deworming.export');
    Route::get('/deworming/create', [DewormingController::class, 'create'])->name('deworming.create');
    Route::post('/deworming/review', [DewormingController::class, 'review'])->name('deworming.review');
    Route::get('/deworming/{deworming}/edit', [DewormingController::class, 'edit'])->name('deworming.edit');
    Route::put('/deworming/{deworming}', [DewormingController::class, 'update'])->name('deworming.update');
});

// Admin-only routes
Route::middleware(['auth', 'auth.session', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/record-audit', [\App\Http\Controllers\RecordArchiveController::class, 'audit'])->name('admin.record-audit');
    Route::get('/record-archive/{type?}', [\App\Http\Controllers\RecordArchiveController::class, 'index'])->name('admin.record-archive');
    Route::post('/record-archive/{type}/{record}/restore', [\App\Http\Controllers\RecordArchiveController::class, 'restore'])->whereNumber('record')->name('admin.record-archive.restore');
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
});
