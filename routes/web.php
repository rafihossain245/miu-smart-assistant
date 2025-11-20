<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\ChatbotController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;

Route::get('/', function () {
    return view('home');
});

// Frontend Routes
Route::get('/page/{page}', [PageController::class, 'show'])->name('page.show');

// Embedded Chatbot Routes (Public)
Route::get('/embed/{chatbot}', [ChatbotController::class, 'embedView'])->name('chatbot.embed.view');

// User Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Admin Authentication Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Protected Admin Routes
    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('pages', AdminPageController::class);
        Route::post('/pages/analyze-seo', [AdminPageController::class, 'analyzeSEO'])->name('pages.analyze-seo');
        
        // Admin Management
        Route::resource('admins', AdminController::class);
        Route::post('/admins/{admin}/change-password', [AdminController::class, 'changePassword'])->name('admins.change-password');
        
        // Profile Management for Current Admin
        Route::get('/profile/edit', [AdminController::class, 'editProfile'])->name('profile.edit');
        Route::post('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');
        Route::get('/profile/change-password', [AdminController::class, 'showChangePassword'])->name('profile.change-password');
        Route::post('/profile/change-password', [AdminController::class, 'updatePassword'])->name('profile.update-password');
        
        // User Management
        Route::resource('users', UserController::class);
        Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');

        // Chatbot Statistics
        Route::get('/chatbot-statistics', [App\Http\Controllers\Admin\ChatbotStatisticsController::class, 'index'])->name('chatbot-statistics.index');
        Route::get('/chatbot-statistics/{chatbot}', [App\Http\Controllers\Admin\ChatbotStatisticsController::class, 'show'])->name('chatbot-statistics.show');

        // Broadcast Settings
        Route::get('/settings/broadcast', [AdminController::class, 'broadcastSettings'])->name('settings.broadcast');
        Route::post('/settings/broadcast', [AdminController::class, 'updateBroadcastSettings'])->name('settings.broadcast.update');
    });
});

// API Routes for Broadcasting
Route::get('/api/broadcast-config', function () {
    // For now, directly return Reverb config from Laravel settings
    $broadcastDriver = config('broadcasting.default');

    if ($broadcastDriver === 'reverb') {
        return response()->json([
            'enabled' => true,
            'driver' => 'reverb',
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.scheme'),
        ]);
    } elseif ($broadcastDriver === 'pusher') {
        return response()->json([
            'enabled' => true,
            'driver' => 'pusher',
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'scheme' => config('broadcasting.connections.pusher.options.scheme'),
        ]);
    }

    return response()->json(['enabled' => false]);
});

// User Dashboard Routes (Authenticated Users)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    Route::resource('chatbots', ChatbotController::class);
    Route::get('/chatbots/{chatbot}/sources', [ChatbotController::class, 'sources'])->name('chatbots.sources');
    Route::post('/chatbots/{chatbot}/sources', [ChatbotController::class, 'storeSources'])->name('chatbots.sources.store');
    Route::post('/chatbots/{chatbot}/sources/{source}/sync', [ChatbotController::class, 'syncSource'])->name('chatbots.sources.sync');
    Route::post('/chatbots/{chatbot}/sources/sync-all', [ChatbotController::class, 'syncAllSources'])->name('chatbots.sources.sync-all');
    Route::get('/chatbots/{chatbot}/products', [ChatbotController::class, 'products'])->name('chatbots.products');
    Route::post('/chatbots/{chatbot}/products', [ChatbotController::class, 'storeProduct'])->name('chatbots.products.store');
    Route::put('/chatbots/{chatbot}/products/{product}', [ChatbotController::class, 'updateProduct'])->name('chatbots.products.update');
    Route::delete('/chatbots/{chatbot}/products/{product}', [ChatbotController::class, 'destroyProduct'])->name('chatbots.products.destroy');
    Route::get('/chatbots/{chatbot}/test', [ChatbotController::class, 'test'])->name('chatbots.test');
    Route::get('/chatbots/{chatbot}/embed', [ChatbotController::class, 'embed'])->name('chatbots.embed');
    Route::get('/chatbots/{chatbot}/analytics', [ChatbotController::class, 'analytics'])->name('chatbots.analytics');
    Route::get('/chatbots/{chatbot}/conversations', [ChatbotController::class, 'conversations'])->name('chatbots.conversations');
    Route::get('/chatbots/{chatbot}/training', [ChatbotController::class, 'training'])->name('chatbots.training');
    Route::post('/chatbots/{chatbot}/training', [ChatbotController::class, 'storeTraining'])->name('chatbots.training.store');
    Route::post('/chatbots/{chatbot}/feed-query-examples', [ChatbotController::class, 'feedQueryExamplesToAI'])->name('chatbots.feed-query-examples');
    Route::post('/chatbots/{chatbot}/import-conversations', [ChatbotController::class, 'importConversations'])->name('chatbots.import-conversations');
    Route::post('/chatbots/{chatbot}/extract-product-info', [ChatbotController::class, 'extractProductInfo'])->name('chatbots.extract-product-info');
});
