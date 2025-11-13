<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\GlassesController;
use App\Http\Controllers\Api\ReviewHistoryController;
use App\Http\Controllers\Api\CompaniesController;
use App\Http\Controllers\FeaturedActionController;
use App\Http\Controllers\Api\DiscountConfigController;
use App\Http\Controllers\BonusController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/phone-login-or-register', [AuthController::class, 'phoneLoginOrRegister']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/check-email', [AuthController::class, 'checkEmailAvailability']);
Route::post('/complete-registration', [AuthController::class, 'completeRegistration']);

// Protected routes that require authentication
Route::middleware(['auth:sanctum', \App\Http\Middleware\LogApiRequests::class])->group(function () {
    Route::get('/user/profile', [AuthController::class, 'getUserProfile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/diopter', [AuthController::class, 'updateDiopter']);
    Route::post('/user/bonus/app', [AuthController::class, 'awardAppBonus']);
    Route::get('/user/notifications', [AuthController::class, 'getNotificationSettings']);
    Route::put('/user/notifications', [AuthController::class, 'updateNotifications']);
    Route::get('/user/notifications/list', [AuthController::class, 'getNotifications']);
    Route::put('/user/notifications/mark-read', [AuthController::class, 'markNotificationAsRead']);
    Route::put('/user/notifications/mark-all-read', [AuthController::class, 'markAllNotificationsAsRead']);
    Route::put('/user/push-token', [AuthController::class, 'updatePushToken'])
        ->middleware(\App\Http\Middleware\LogPushTokenRequests::class);
    Route::post('/user/send-notification', [AuthController::class, 'sendPushNotification']);
    Route::get('/transactions/last', [TransactionController::class, 'getLastTransactions']);
    Route::get('/transactions/all', [TransactionController::class, 'getAllTransactions']);
    
    // Order routes (requires authentication for points processing)
    Route::post('/send-order', [OrderController::class, 'sendOrder']);
    
    // Reservation routes
    Route::post('/reservations/time-slots', [ReservationController::class, 'getAvailableTimeSlots']);
    Route::post('/reservations/create', [ReservationController::class, 'createReservation']);
    Route::get('/reservations/user', [ReservationController::class, 'getUserReservations']);
    // Backward-compatible alias used by frontend
    Route::get('/user/appointments', [ReservationController::class, 'getUserReservations']);
    Route::put('/reservations/update', [ReservationController::class, 'updateReservation']);
    Route::delete('/reservations/cancel', [ReservationController::class, 'cancelReservation']);
    
    // Glasses routes
    Route::get('/user/glasses', [GlassesController::class, 'getUserGlasses']);
    Route::get('/test-glasses-connection', [GlassesController::class, 'testConnection']);

    Route::get('/user/review-history', [ReviewHistoryController::class, 'getUserReviewHistory']);
    Route::get('/test-review-connection', [ReviewHistoryController::class, 'testConnection']);
});

// Featured action routes (public)
Route::get('/featured-action/config', [FeaturedActionController::class, 'getConfig']);

// Featured action admin routes (temporarily public for testing)
Route::put('/admin/featured-action/config', [FeaturedActionController::class, 'updateConfig']);
Route::post('/admin/featured-action/toggle', [FeaturedActionController::class, 'toggle']);

// Companies routes (public - sindikati firme)
Route::get('/companies', [CompaniesController::class, 'getCompanies']);

// Discount configuration routes (public)
Route::get('/discount/config', [DiscountConfigController::class, 'getConfig']);

// Discount admin routes (temporarily public for testing - should be protected in production)
Route::put('/admin/discount/config', [DiscountConfigController::class, 'updateConfig']);

// Bonus system routes (public)
Route::get('/bonus/config', [BonusController::class, 'getConfig']);

// Bonus admin routes (temporarily public for testing - should be protected in production)
Route::put('/admin/bonus/config', [BonusController::class, 'updateConfig']);
Route::post('/admin/bonus/toggle', [BonusController::class, 'toggle']); 