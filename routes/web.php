<?php

use App\Http\Controllers\Back\AiChatController;
use App\Http\Controllers\Back\AiIntelligenceSuiteController;
use App\Http\Controllers\Back\AlertAiController;
use App\Http\Controllers\Back\AlertController;
use App\Http\Controllers\Back\BackController;
use App\Http\Controllers\Back\FinancialDashboardController;
use App\Http\Controllers\Back\MaintenanceIntelligenceController;
use App\Http\Controllers\Back\ZoneAiController;
use App\Http\Controllers\Back\ZoneController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FinancementController;
use App\Http\Controllers\Front\FrontController;
use App\Http\Controllers\Front\PublicZoneAlertController;
use App\Http\Controllers\InfrastructureController;
use App\Http\Controllers\InterventionController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MaintenanceInvitationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDoctorController;
use App\Http\Controllers\Technician\TechnicianInterventionController;
use App\Http\Controllers\TechnicienController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Model Binding
|--------------------------------------------------------------------------
|
| Le technicien n'est pas une entité séparée : c'est un utilisateur portant
| le rôle `technician`. Les routes « technicien » résolvent ces comptes,
| ainsi que les rares comptes qui assument une affectation d'intervenant
| sans porter le rôle (cas des affectations historiques).
|
*/
Route::bind('technicien', function ($value) {
    return \App\Models\User::where('id', $value)
        ->where(function ($query) {
            $query->where('role', 'technician')
                ->orWhereHas('maintenances')
                ->orWhereHas('interventions');
        })
        ->firstOrFail();
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});
Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.login');
Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
    Route::post('/profile/two-factor', [TwoFactorController::class, 'enable'])->name('two-factor.enable');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::post('/profile/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::get('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('two-factor.recovery-codes.show');
    Route::post('/profile/two-factor/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes.regenerate');
    Route::get('/dashboard/manager', [DashboardController::class, 'manager'])->middleware('role:manager,gestionnaire')->name('manager.dashboard');
    Route::get('/dashboard/citizen', [DashboardController::class, 'citizen'])->middleware('role:citizen,citoyen')->name('citizen.dashboard');

    // Notifications internes
    Route::put('/notifications/{notification}/read', function ($notification) {
        $notification = DB::table('notifications')->where('id', $notification)->where('notifiable_type', 'App\Models\User')->where('notifiable_id', auth()->id())->first();
        if ($notification) {
            DB::table('notifications')->where('id', $notification->id)->update(['read_at' => now()]);
        }

        return back();
    })->name('notifications.read');

    Route::put('/notifications/read-all', function () {
        DB::table('notifications')
            ->where('notifiable_type', 'App\Models\User')
            ->where('notifiable_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    })->name('notifications.read-all');
});

Route::get('/', [FrontController::class, 'home'])->name('home');
Route::get('/about', [FrontController::class, 'about'])->name('front.about');
Route::get('/services', [FrontController::class, 'services'])->name('front.services');
Route::get('/zones', [FrontController::class, 'zones'])->name('front.zones.index');
Route::get('/alerts', [FrontController::class, 'alerts'])->name('front.alerts.index');
Route::get('/alertes', [FrontController::class, 'alerts']);

// Pages et endpoints publics (citoyens) des zones et alertes.
// Les routes statiques DOIVENT précéder {zone} / {alert}.
Route::get('/zones/map-data', [PublicZoneAlertController::class, 'mapData'])->middleware('throttle:60,1')->name('front.zones.map-data');
Route::get('/zones/nearest', [PublicZoneAlertController::class, 'nearest'])->middleware('throttle:60,1')->name('front.zones.nearest');
Route::get('/zones/{zone}', [PublicZoneAlertController::class, 'showZone'])->name('front.zones.show');
Route::get('/alerts/live', [PublicZoneAlertController::class, 'live'])->middleware('throttle:60,1')->name('front.alerts.live');
Route::get('/alerts/{alert}', [PublicZoneAlertController::class, 'showAlert'])->name('front.alerts.show');
Route::get('/suivi', [PublicZoneAlertController::class, 'trackReport'])->name('front.track');

Route::middleware('auth')->group(function () {
    Route::get('/mes-zones', [PublicZoneAlertController::class, 'subscriptions'])->name('front.zones.subscriptions');
    Route::post('/mes-zones/{zone}', [PublicZoneAlertController::class, 'subscribe'])->name('front.zones.subscribe');
    Route::delete('/mes-zones/{zone}', [PublicZoneAlertController::class, 'unsubscribe'])->name('front.zones.unsubscribe');
});

Route::middleware('auth')->group(function () {
    Route::get('/incidents', [FrontController::class, 'incidents'])->name('front.incidents.index');
    Route::get('/incidents/create', [FrontController::class, 'createIncident'])->name('front.incidents.create');
    Route::post('/incidents', [FrontController::class, 'storeIncident'])->name('front.incidents.store');
    Route::get('/incidents/{incident}', [FrontController::class, 'showIncident'])->name('front.incidents.show');
});
Route::get('/infrastructures', [FrontController::class, 'infrastructures'])->name('front.infrastructures.index');
Route::get('/infrastructures/{infrastructure}', [FrontController::class, 'showInfrastructure'])->name('front.infrastructures.show');
Route::post('/infrastructures/{infrastructure}/report-malfunction', [FrontController::class, 'reportMalfunction'])->name('front.infrastructures.report');
Route::get('/projects', [FrontController::class, 'projects'])->name('front.projects.index');
Route::get('/projects/{project}', [FrontController::class, 'showProject'])->name('front.projects.show');
Route::get('/funding', [FrontController::class, 'funding'])->name('front.funding');
Route::get('/news', [FrontController::class, 'news'])->name('front.news');
Route::get('/contact', [FrontController::class, 'contact'])->name('front.contact');

// Brevo Email Assignment Invitation Workflow (Token protected)
Route::get('/maintenances/invitation/{token}', [MaintenanceInvitationController::class, 'show'])->name('maintenances.invitation.show');
Route::post('/maintenances/invitation/{token}/accept', [MaintenanceInvitationController::class, 'accept'])->name('maintenances.invitation.accept');
Route::post('/maintenances/invitation/{token}/decline', [MaintenanceInvitationController::class, 'decline'])->name('maintenances.invitation.decline');

Route::middleware(['auth', 'role:admin,manager,gestionnaire'])->prefix('back')->name('back.')->group(function () {
    Route::get('/', [BackController::class, 'dashboard'])->name('dashboard');
    Route::get('/incidents', [BackController::class, 'incidents'])->name('incidents');
    Route::post('/signalements/{signalement}/confirm', [BackController::class, 'confirmSignalement'])->name('signalements.confirm');
    Route::get('/infrastructures', [BackController::class, 'infrastructures'])->name('infrastructures');
    Route::get('/projects', [BackController::class, 'projects'])->name('projects');
    Route::get('/funding', [BackController::class, 'funding'])->name('funding');
    Route::get('/users', [BackController::class, 'users'])->name('users');

    // Routes statiques des zones et alertes. Elles DOIVENT précéder les
    // Route::resource() : sinon /zones/export ou /alerts/live serait avalé
    // par la liaison implicite {zone} / {alert}.
    Route::get('/zones/map-data', [ZoneController::class, 'mapData'])->name('zones.map-data');
    Route::get('/zones/export', [ZoneController::class, 'export'])->name('zones.export');
    Route::get('/alerts/export', [AlertController::class, 'export'])->name('alerts.export');
    Route::get('/alerts/live', [AlertController::class, 'live'])->middleware('throttle:120,1')->name('alerts.live');
    Route::post('/alerts/bulk', [AlertController::class, 'bulk'])->name('alerts.bulk');

    Route::get('/financial/dashboard', [FinancialDashboardController::class, '__invoke'])->name('financial.dashboard');
    Route::get('/pilotage', [PilotageController::class, '__invoke'])->name('pilotage');
    Route::resource('zones', ZoneController::class);
    Route::resource('alerts', AlertController::class);

    // Workflow de traitement des alertes (additif, colonnes NULLABLE).
    Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    Route::post('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    Route::post('/alerts/{alert}/reopen', [AlertController::class, 'reopen'])->name('alerts.reopen');

    // AquaSecure AI — intelligence des zones et des alertes (additif, throttle API IA)
    Route::post('/zones/{zone}/ai-analyze', [ZoneAiController::class, 'analyze'])->middleware('throttle:20,1')->name('zones.ai-analyze');
    Route::get('/zones/{zone}/ai-analysis', [ZoneAiController::class, 'analysis'])->name('zones.get-ai-analysis');
    Route::post('/alerts/{alert}/ai-analyze', [AlertAiController::class, 'analyze'])->middleware('throttle:20,1')->name('alerts.ai-analyze');
    Route::get('/alerts/{alert}/ai-analysis', [AlertAiController::class, 'analysis'])->name('alerts.get-ai-analysis');
});

// AquaSecure AI — assistant conversationnel (lecture seule, zones + alertes).
// Groupe distinct du back.* : le rôle technician est autorisé sans entrer dans le back-office.
Route::middleware(['auth', 'role:admin,manager,gestionnaire,technician'])->prefix('back')->name('back.')->group(function () {
    Route::post('/ai/chat', [AiChatController::class, 'chat'])->middleware('throttle:15,1')->name('ai.chat');
    Route::get('/ai/chat/status', [AiChatController::class, 'status'])->middleware('throttle:30,1')->name('ai.chat.status');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [BackController::class, 'dashboard'])->name('dashboard');
    Route::get('/incidents', [BackController::class, 'incidents'])->name('incidents.index');
    Route::get('/funding', [BackController::class, 'funding'])->name('funding.index');
    Route::patch('/signalements/{signalement}', [BackController::class, 'updateSignalement'])->name('signalements.update');
    Route::delete('/signalements/{signalement}', [BackController::class, 'destroySignalement'])->name('signalements.destroy');
    Route::patch('/incidents/{incident}', [BackController::class, 'updateIncident'])->name('incidents.update');
    Route::delete('/incidents/{incident}', [BackController::class, 'destroyIncident'])->name('incidents.destroy');
});

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::resource('users', UserController::class)->names('admin.users');
});

Route::middleware(['auth', 'verified', 'role:admin,manager,gestionnaire'])->prefix('admin')->group(function () {
    Route::resource('infrastructures', InfrastructureController::class)->names('infrastructures');
    Route::resource('maintenances', MaintenanceController::class)->names('maintenances');
    Route::post('/maintenances/{maintenance}/start', [MaintenanceController::class, 'start'])->name('maintenances.start');
    Route::post('/maintenances/{maintenance}/validate-completion', [MaintenanceController::class, 'validateCompletion'])->name('maintenances.validate-completion');
    Route::post('/maintenances/{maintenance}/request-corrections', [MaintenanceController::class, 'requestCorrections'])->name('maintenances.request-corrections');
    Route::post('/maintenances/{maintenance}/resend-invitation', [MaintenanceController::class, 'resendInvitation'])->name('maintenances.resend-invitation');

    // AI Maintenance Intelligence Center (Stage 1: Scan & Stage 2: Planner)
    Route::get('/maintenance-intelligence', [MaintenanceIntelligenceController::class, 'index'])->name('maintenance-intelligence.index');
    Route::post('/maintenance-intelligence/scan', [MaintenanceIntelligenceController::class, 'scan'])->middleware('throttle:15,1')->name('maintenance-intelligence.scan');
    Route::post('/maintenance-intelligence/plan', [MaintenanceIntelligenceController::class, 'generatePlan'])->middleware('throttle:20,1')->name('maintenance-intelligence.plan');
    Route::post('/maintenance-intelligence/store-maintenance', [MaintenanceIntelligenceController::class, 'storeMaintenance'])->name('maintenance-intelligence.store-maintenance');

    // AquaSecure AI Intelligence Suite
    // Feature 1: AI Infrastructure Health Analyst
    Route::post('/infrastructures/{infrastructure}/ai-health', [AiIntelligenceSuiteController::class, 'analyzeInfrastructure'])->middleware('throttle:20,1')->name('infrastructures.ai-health');
    Route::get('/infrastructures/{infrastructure}/ai-health', [AiIntelligenceSuiteController::class, 'getInfrastructureAnalysis'])->name('infrastructures.get-ai-health');

    // Feature 2: Smart Citizen Report Analyzer
    Route::post('/signalements/{signalement}/ai-analyze', [AiIntelligenceSuiteController::class, 'analyzeReport'])->middleware('throttle:20,1')->name('signalements.ai-analyze');
    Route::post('/signalements/{signalement}/ai-accept', [AiIntelligenceSuiteController::class, 'acceptReportSuggestions'])->name('signalements.ai-accept');
    Route::post('/signalements/{signalement}/ai-edit', [AiIntelligenceSuiteController::class, 'editReportClassification'])->name('signalements.ai-edit');

    // Feature 3: AI Technician Matchmaker
    Route::post('/maintenances/recommend-technicians', [AiIntelligenceSuiteController::class, 'recommendTechnicians'])->middleware('throttle:25,1')->name('maintenances.recommend-technicians');
    Route::post('/maintenances/{maintenance}/assign-technician', [AiIntelligenceSuiteController::class, 'assignTechnician'])->name('maintenances.assign-technician');

    Route::resource('projects', ProjectController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->names('projects');

    Route::post('/projects/{project}/submit', [ProjectController::class, 'submit'])->name('projects.submit');
    Route::post('/projects/{project}/decide', [ProjectController::class, 'decide'])->name('projects.decide');

    // Project Doctor — chatbot IA d'analyse de projets
    Route::post('/projects/doctor/message', [ProjectDoctorController::class, 'message'])
        ->middleware('throttle:30,1')
        ->name('projects.doctor.message');
    Route::get('/projects/doctor/status', [ProjectDoctorController::class, 'status'])
        ->name('projects.doctor.status');

    Route::resource('financements', FinancementController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->names('financements');

    Route::post('/financements/{financement}/submit', [FinancementController::class, 'submit'])->name('financements.submit');
    Route::post('/financements/{financement}/decide', [FinancementController::class, 'decide'])->name('financements.decide');

    Route::resource('interventions', InterventionController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->names('interventions');

    Route::resource('techniciens', TechnicienController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])
        ->names('techniciens');
});

// Dedicated Technician Workspace (Mes interventions)
Route::middleware(['auth', 'role:technician,admin'])->prefix('technicien')->name('technicien.')->group(function () {
    Route::get('/interventions', [TechnicianInterventionController::class, 'index'])->name('interventions.index');
    Route::get('/interventions/{maintenance}', [TechnicianInterventionController::class, 'show'])->name('interventions.show');
    Route::post('/interventions/{maintenance}/complete', [TechnicianInterventionController::class, 'submitCompletion'])->name('interventions.complete');
    Route::post('/interventions/{maintenance}/prolong', [TechnicianInterventionController::class, 'prolong'])->name('interventions.prolong');
});
