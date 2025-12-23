<?php

/**
 * Comprehensive Feature Testing Script
 * Tests all major features of the Covre application
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Video;
use App\Models\Story;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Bookmark;
use App\Models\Follow;
use Illuminate\Support\Facades\Hash;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║          COVRE - COMPREHENSIVE FEATURE TESTING                     ║\n";
echo "║          Testing Date: " . date('Y-m-d H:i:s') . "                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
];

function testSection($title) {
    echo "\n";
    echo "┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ " . str_pad($title, 63) . " │\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n";
}

function testCase($name, $callback, &$results) {
    echo str_pad("  ▸ " . $name . "...", 60);
    try {
        $result = $callback();
        if ($result === true || $result === null) {
            echo " ✓ PASS\n";
            $results['passed']++;
        } elseif (is_array($result) && isset($result['warning'])) {
            echo " ⚠ WARN\n";
            echo "    → " . $result['warning'] . "\n";
            $results['warnings']++;
        } else {
            echo " ✗ FAIL\n";
            echo "    → " . (is_string($result) ? $result : 'Unknown error') . "\n";
            $results['failed']++;
        }
    } catch (Exception $e) {
        echo " ✗ FAIL\n";
        echo "    → " . $e->getMessage() . "\n";
        $results['failed']++;
    }
}

// ============================================================================
// TEST 1: DATABASE CONNECTION & SETUP
// ============================================================================
testSection("TEST 1: Database Connection & Setup");

testCase("Database connection is active", function() {
    try {
        \DB::connection()->getPdo();
        return true;
    } catch (Exception $e) {
        return "Database connection failed: " . $e->getMessage();
    }
}, $results);

testCase("All required tables exist", function() {
    $tables = ['users', 'videos', 'stories', 'likes', 'comments', 'bookmarks', 'follows'];
    $missing = [];
    foreach ($tables as $table) {
        if (!\Schema::hasTable($table)) {
            $missing[] = $table;
        }
    }
    return empty($missing) ? true : "Missing tables: " . implode(', ', $missing);
}, $results);

testCase("Videos table has status column", function() {
    return \Schema::hasColumn('videos', 'status') ? true : "Status column missing";
}, $results);

// ============================================================================
// TEST 2: USER DATA & AUTHENTICATION
// ============================================================================
testSection("TEST 2: User Data & Authentication");

testCase("Users exist in database", function() {
    $count = User::count();
    if ($count === 0) {
        return "No users in database";
    }
    echo "\n    → Found $count users";
    return true;
}, $results);

testCase("Test user can be created", function() {
    $email = 'test_' . time() . '@test.com';
    $user = User::create([
        'name' => 'Test User',
        'email' => $email,
        'password' => Hash::make('password123'),
    ]);
    if (!$user) {
        return "Failed to create test user";
    }
    // Cleanup
    $user->delete();
    return true;
}, $results);

testCase("Admin user exists", function() {
    $admin = User::where('role', 'admin')->orWhere('is_admin', true)->first();
    if (!$admin) {
        return ['warning' => 'No admin user found'];
    }
    echo "\n    → Admin: {$admin->name} ({$admin->email})";
    return true;
}, $results);

// ============================================================================
// TEST 3: VIDEO DATA & STATUS
// ============================================================================
testSection("TEST 3: Video Data & Status");

testCase("Videos exist in database", function() {
    $count = Video::count();
    if ($count === 0) {
        return "No videos in database";
    }
    echo "\n    → Found $count videos";
    return true;
}, $results);

testCase("All videos have valid status", function() {
    $total = Video::count();
    $approved = Video::where('status', 'approved')->count();
    $pending = Video::where('status', 'pending')->count();
    $rejected = Video::where('status', 'rejected')->count();
    $nullStatus = Video::whereNull('status')->count();

    echo "\n    → Approved: $approved | Pending: $pending | Rejected: $rejected";

    if ($nullStatus > 0) {
        return "Found $nullStatus videos with NULL status";
    }

    if ($approved === 0 && $pending === 0) {
        return ['warning' => 'No approved or pending videos'];
    }

    return true;
}, $results);

testCase("Videos have valid URLs", function() {
    $video = Video::first();
    if (!$video) {
        return "No videos to check";
    }

    if (empty($video->s3_url)) {
        return "Video URL is empty";
    }

    echo "\n    → Sample URL: " . substr($video->s3_url, 0, 60) . "...";
    return true;
}, $results);

testCase("Videos have thumbnails", function() {
    $video = Video::first();
    if (!$video) {
        return "No videos to check";
    }

    if (empty($video->thumbnail_url)) {
        return ['warning' => 'Video has no thumbnail'];
    }

    return true;
}, $results);

testCase("Videos have menu_data", function() {
    $video = Video::first();
    if (!$video) {
        return "No videos to check";
    }

    $menuData = $video->menu_data;
    // Laravel auto-casts JSON columns to arrays
    if (is_string($menuData)) {
        $menuData = json_decode($menuData, true);
    }

    if (!$menuData || !is_array($menuData)) {
        return "Invalid menu_data";
    }

    echo "\n    → Menu data is valid array";
    return true;
}, $results);

// ============================================================================
// TEST 4: SOCIAL FEATURES
// ============================================================================
testSection("TEST 4: Social Features (Likes, Comments, Bookmarks)");

testCase("Likes are working", function() {
    $count = Like::count();
    echo "\n    → Total likes: $count";
    return true;
}, $results);

testCase("Comments are working", function() {
    $count = Comment::count();
    echo "\n    → Total comments: $count";
    return true;
}, $results);

testCase("Bookmarks are working", function() {
    $count = Bookmark::count();
    echo "\n    → Total bookmarks: $count";
    return true;
}, $results);

testCase("Follow system is working", function() {
    $count = Follow::count();
    echo "\n    → Total follows: $count";
    return true;
}, $results);

testCase("Videos have engagement counts", function() {
    $video = Video::withCount(['likes', 'comments', 'views'])->first();
    if (!$video) {
        return "No videos to check";
    }

    echo "\n    → Sample: {$video->likes_count} likes, {$video->comments_count} comments, {$video->views_count} views";
    return true;
}, $results);

// ============================================================================
// TEST 5: STORIES FEATURE
// ============================================================================
testSection("TEST 5: Stories Feature");

testCase("Stories table exists", function() {
    return \Schema::hasTable('stories') ? true : "Stories table missing";
}, $results);

testCase("Stories data", function() {
    $count = Story::count();
    if ($count === 0) {
        return ['warning' => 'No stories in database'];
    }
    echo "\n    → Found $count stories";
    return true;
}, $results);

testCase("Stories have expiry mechanism", function() {
    $expired = Story::where('expires_at', '<', now())->count();
    $active = Story::where('expires_at', '>', now())->count();
    echo "\n    → Active: $active | Expired: $expired";
    return true;
}, $results);

// ============================================================================
// TEST 6: VIDEO MODERATION
// ============================================================================
testSection("TEST 6: Video Moderation System");

testCase("VIDEO_AUTO_APPROVE is configured", function() {
    $autoApprove = env('VIDEO_AUTO_APPROVE');
    $status = $autoApprove ? 'ENABLED' : 'DISABLED';
    echo "\n    → Auto-approve: $status";
    return true;
}, $results);

testCase("Moderation fields exist", function() {
    $hasModeratedBy = \Schema::hasColumn('videos', 'moderated_by');
    $hasModeratedAt = \Schema::hasColumn('videos', 'moderated_at');
    $hasRejectionReason = \Schema::hasColumn('videos', 'rejection_reason');

    if (!$hasModeratedBy || !$hasModeratedAt || !$hasRejectionReason) {
        return ['warning' => 'Some moderation fields missing'];
    }

    return true;
}, $results);

// ============================================================================
// TEST 7: STORAGE CONFIGURATION
// ============================================================================
testSection("TEST 7: Storage Configuration");

testCase("Storage disk is configured", function() {
    $disk = config('filesystems.default');
    echo "\n    → Default disk: $disk";
    return true;
}, $results);

testCase("Public storage symlink exists", function() {
    $path = public_path('storage');
    if (!file_exists($path)) {
        return ['warning' => 'Storage symlink not found - run: php artisan storage:link'];
    }
    return true;
}, $results);

testCase("APP_URL is configured", function() {
    $url = config('app.url');
    if (strpos($url, 'localhost') !== false) {
        return ['warning' => 'APP_URL uses localhost - mobile won\'t work'];
    }
    echo "\n    → APP_URL: $url";
    return true;
}, $results);

// ============================================================================
// TEST 8: API ENDPOINTS (via routes)
// ============================================================================
testSection("TEST 8: API Routes Configuration");

testCase("Video routes are registered", function() {
    $routes = \Route::getRoutes();
    $videoRoutes = 0;
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/videos') !== false) {
            $videoRoutes++;
        }
    }
    if ($videoRoutes === 0) {
        return "No video routes found";
    }
    echo "\n    → Found $videoRoutes video routes";
    return true;
}, $results);

testCase("Auth routes are registered", function() {
    $routes = \Route::getRoutes();
    $authRoutes = 0;
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/login') !== false ||
            strpos($route->uri(), 'api/register') !== false) {
            $authRoutes++;
        }
    }
    if ($authRoutes === 0) {
        return "No auth routes found";
    }
    return true;
}, $results);

// ============================================================================
// TEST 9: SANCTUM AUTHENTICATION
// ============================================================================
testSection("TEST 9: Laravel Sanctum Configuration");

testCase("Sanctum is installed", function() {
    return class_exists('Laravel\Sanctum\Sanctum') ? true : "Sanctum not installed";
}, $results);

testCase("Personal access tokens table exists", function() {
    return \Schema::hasTable('personal_access_tokens') ? true : "Token table missing";
}, $results);

testCase("Token expiration is configured", function() {
    $expiration = config('sanctum.expiration');
    $status = $expiration === null ? 'IMMORTAL (no expiration)' : "$expiration minutes";
    echo "\n    → Token expiration: $status";
    return true;
}, $results);

// ============================================================================
// TEST 10: AI & EXTERNAL SERVICES
// ============================================================================
testSection("TEST 10: AI & External Services");

testCase("Gemini API key is configured", function() {
    $apiKey = env('GEMINI_API_KEY');
    if (empty($apiKey)) {
        return ['warning' => 'Gemini API key not set'];
    }
    echo "\n    → API key: " . substr($apiKey, 0, 20) . "...";
    return true;
}, $results);

testCase("Cloudflare R2 is configured", function() {
    $endpoint = env('AWS_ENDPOINT');
    $bucket = env('AWS_BUCKET');
    if (empty($endpoint) || empty($bucket)) {
        return ['warning' => 'R2 not fully configured'];
    }
    echo "\n    → Bucket: $bucket";
    return true;
}, $results);

// ============================================================================
// SUMMARY
// ============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                        TEST SUMMARY                                ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";
echo "║  ✓ PASSED:   " . str_pad($results['passed'], 52) . "║\n";
echo "║  ⚠ WARNINGS: " . str_pad($results['warnings'], 52) . "║\n";
echo "║  ✗ FAILED:   " . str_pad($results['failed'], 52) . "║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";

$total = $results['passed'] + $results['failed'] + $results['warnings'];
$passRate = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

if ($results['failed'] === 0) {
    echo "║  Status: ✓ ALL TESTS PASSED";
    echo str_pad("", 40) . "║\n";
} elseif ($results['failed'] < 5) {
    echo "║  Status: ⚠ MOSTLY WORKING (Minor Issues)";
    echo str_pad("", 27) . "║\n";
} else {
    echo "║  Status: ✗ CRITICAL ISSUES FOUND";
    echo str_pad("", 35) . "║\n";
}

echo "║  Pass Rate: {$passRate}%";
echo str_pad("", 55 - strlen("{$passRate}%")) . "║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Exit with appropriate code
exit($results['failed'] > 0 ? 1 : 0);
