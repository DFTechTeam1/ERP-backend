/**
 * ========================================
 * Documentation Login Routes
 * ========================================
 * 
 * Add these routes to your routes/web.php file
 * 
 * These routes handle authentication for accessing
 * the Scalar API documentation page
 */

use App\Http\Controllers\LandingPageController;

// Documentation Login Routes
Route::get('login', [LandingPageController::class, 'showLoginForm'])
    ->name('documentation.login')
    ->middleware('guest');

Route::post('login', [LandingPageController::class, 'login'])
    ->name('documentation.login.submit')
    ->middleware('guest');

Route::post('logout', [LandingPageController::class, 'logout'])
    ->name('documentation.logout')
    ->middleware('auth');

/**
 * Example: Protect your Scalar documentation route
 * 
 * Add this to protect your API documentation page:
 */

Route::middleware(['auth'])->group(function () {
    // Your Scalar documentation route
    Route::get('/api/documentation', function () {
        // Return your scalar documentation view
        return view('scribe.index'); // or your scalar docs view
    })->name('api.documentation');
    
    // Other protected routes...
});
