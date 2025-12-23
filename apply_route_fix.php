<?php
/**
 * CRITICAL FIX: Move public endpoints keluar dari auth middleware
 * This fixes the "Route [login] not defined" error
 */

echo "========================================\n";
echo "APPLYING CRITICAL API ROUTE FIX\n";
echo "========================================\n\n";

$routeFile = __DIR__ . '/routes/api.php';

if (!file_exists($routeFile)) {
    die("ERROR: routes/api.php not found!\n");
}

// Create backup
$backupFile = $routeFile . '.backup_' . date('YmdHis');
copy($routeFile, $backupFile);
echo "✓ Backup created: {$backupFile}\n\n";

// Read current content
$content = file_get_contents($routeFile);

// Define the search pattern (the problematic section)
$searchPattern = <<<'EOT'
// TEST ONLY: AI Scan without auth (REMOVE IN PRODUCTION)
Route::post('/ai/scan-test', [AiController::class, 'scan']);

Route::middleware('auth:sanctum')->group(function () {
    // User
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Global Search (rate limited to prevent DoS)
    Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:30,1'); // 30 searches per minute

    // Videos
    Route::get('/videos', [VideoController::class, 'index'])->middleware('throttle:60,1'); // 60 requests per minute
    Route::get('/videos/search', [VideoController::class, 'search'])->middleware('throttle:30,1'); // 30 searches per minute
EOT;

// Define the replacement (fixed version)
$replacement = <<<'EOT'
// TEST ONLY: AI Scan without auth (REMOVE IN PRODUCTION)
Route::post('/ai/scan-test', [AiController::class, 'scan']);

// ========== PUBLIC ENDPOINTS - Guest dapat akses (FIX APPLIED) ==========
Route::get('/videos', [VideoController::class, 'index'])->middleware('throttle:60,1'); // 60 requests per minute
Route::get('/videos/search', [VideoController::class, 'search'])->middleware('throttle:30,1'); // 30 searches per minute
Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:30,1'); // 30 searches per minute
// ========================================================================

Route::middleware('auth:sanctum')->group(function () {
    // User
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Videos (authenticated only)
EOT;

// Apply the fix
if (strpos($content, 'PUBLIC ENDPOINTS - Guest dapat akses') !== false) {
    echo "⚠️  Fix already applied! Skipping...\n";
} else {
    $newContent = str_replace($searchPattern, $replacement, $content);

    if ($newContent === $content) {
        echo "❌ ERROR: Pattern not found. File might have been modified.\n";
        echo "Please apply fix manually using QUICK_FIX_DEADLINE_5_DES.md\n";
    } else {
        file_put_contents($routeFile, $newContent);
        echo "✅ SUCCESS! API routes fixed!\n\n";
        echo "PUBLIC endpoints moved:\n";
        echo "  - GET /api/videos\n";
        echo "  - GET /api/videos/search\n";
        echo "  - GET /api/search\n\n";
        echo "These endpoints can now be accessed without authentication.\n\n";
    }
}

echo "========================================\n";
echo "NEXT STEPS:\n";
echo "========================================\n";
echo "1. Test API: curl http://192.168.1.7:8000/api/videos\n";
echo "2. Should return JSON (not HTML error)\n";
echo "3. Continue with mobile fixes (see QUICK_FIX guide)\n";
echo "========================================\n";
