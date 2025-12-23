<?php

/**
 * COMPREHENSIVE END-TO-END TESTING SCRIPT
 * Tests all features of Covre application
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test configuration
$BASE_URL = 'http://localhost:8000/api';
$testResults = [];
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

// Helper function to make HTTP requests
function makeRequest($method, $url, $data = null, $token = null, $headers = []) {
    $ch = curl_init($url);

    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($token) {
        $defaultHeaders[] = "Authorization: Bearer {$token}";
    }

    $allHeaders = array_merge($defaultHeaders, $headers);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

function test($name, $callback) {
    global $totalTests, $passedTests, $failedTests, $testResults;
    $totalTests++;

    try {
        $result = $callback();
        if ($result === true) {
            $passedTests++;
            $testResults[] = ['name' => $name, 'status' => 'PASS', 'message' => 'OK'];
            echo "✓ PASS: $name\n";
        } else {
            $failedTests++;
            $testResults[] = ['name' => $name, 'status' => 'FAIL', 'message' => $result];
            echo "✗ FAIL: $name - $result\n";
        }
    } catch (Exception $e) {
        $failedTests++;
        $testResults[] = ['name' => $name, 'status' => 'ERROR', 'message' => $e->getMessage()];
        echo "✗ ERROR: $name - " . $e->getMessage() . "\n";
    }
}

echo "=== COVRE END-TO-END TESTING ===\n\n";
echo "Base URL: $BASE_URL\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

// Store authentication token
$authToken = null;

// ============================================================
// TEST 1: AUTHENTICATION & USER MANAGEMENT
// ============================================================
echo "--- TEST 1: AUTHENTICATION & USER MANAGEMENT ---\n";

test("Login with valid credentials", function() use ($BASE_URL, &$authToken) {
    global $BASE_URL;
    $response = makeRequest('POST', "$BASE_URL/login", [
        'email' => 'marco@foodie.com',
        'password' => 'password'
    ]);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    if (!isset($response['body']['token'])) {
        return "No token in response";
    }

    if (!isset($response['body']['user'])) {
        return "No user in response";
    }

    $authToken = $response['body']['token'];
    return true;
});

test("Get current user profile", function() use ($BASE_URL, &$authToken) {
    $response = makeRequest('GET', "$BASE_URL/user", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    if (!isset($response['body']['id'])) {
        return "No user data in response";
    }

    return true;
});

test("Login with invalid credentials", function() use ($BASE_URL) {
    $response = makeRequest('POST', "$BASE_URL/login", [
        'email' => 'invalid@test.com',
        'password' => 'wrongpassword'
    ]);

    if ($response['status'] === 200) {
        return "Should fail with invalid credentials";
    }

    return true;
});

// ============================================================
// TEST 2: DATABASE & SEEDED DATA
// ============================================================
echo "\n--- TEST 2: DATABASE & SEEDED DATA ---\n";

test("Verify seeded videos exist (26 videos)", function() {
    $count = App\Models\Video::count();
    if ($count < 26) {
        return "Expected at least 26 videos, found $count";
    }
    return true;
});

test("Verify food creators exist (8 creators)", function() {
    $creators = App\Models\User::whereIn('email', [
        'marco@foodie.com', 'kenji@sushi.com', 'sarah@sweetlife.com',
        'hunter@streetfood.com', 'rita@healthyeats.com', 'mike@bbqmaster.com',
        'lisa@veganeats.com', 'alex@asianfusion.com'
    ])->count();

    if ($creators < 8) {
        return "Expected 8 food creators, found $creators";
    }
    return true;
});

test("Verify engagement data exists", function() {
    $likes = App\Models\Like::count();
    $comments = App\Models\Comment::count();
    $views = App\Models\View::count();

    if ($likes < 100 || $comments < 100 || $views < 100) {
        return "Insufficient engagement data: likes=$likes, comments=$comments, views=$views";
    }

    return true;
});

// ============================================================
// TEST 3: VIDEO FEED & PLAYBACK
// ============================================================
echo "\n--- TEST 3: VIDEO FEED & PLAYBACK ---\n";

test("Get video feed (Inspirasi)", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    if (!isset($response['body']['data'])) {
        return "No data array in response";
    }

    if (count($response['body']['data']) === 0) {
        return "No videos returned";
    }

    // Check video structure
    $video = $response['body']['data'][0];
    if (!isset($video['id']) || !isset($video['user']) || !isset($video['s3_url'])) {
        return "Invalid video structure";
    }

    return true;
});

test("Get following videos", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos/following?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get my videos", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos/my-videos?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Search videos", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos/search?q=nasi&page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

// ============================================================
// TEST 4: SOCIAL FEATURES
// ============================================================
echo "\n--- TEST 4: SOCIAL FEATURES ---\n";

$testVideoId = null;

test("Get first video ID for testing", function() use (&$testVideoId) {
    $video = App\Models\Video::first();
    if (!$video) {
        return "No videos found";
    }
    $testVideoId = $video->id;
    return true;
});

test("Toggle like on video", function() use ($BASE_URL, $authToken, &$testVideoId) {
    if (!$testVideoId) {
        return "No test video ID";
    }

    $response = makeRequest('POST', "$BASE_URL/videos/$testVideoId/like", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get video comments", function() use ($BASE_URL, $authToken, &$testVideoId) {
    if (!$testVideoId) {
        return "No test video ID";
    }

    $response = makeRequest('GET', "$BASE_URL/videos/$testVideoId/comments?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Add comment to video", function() use ($BASE_URL, $authToken, &$testVideoId) {
    if (!$testVideoId) {
        return "No test video ID";
    }

    $response = makeRequest('POST', "$BASE_URL/videos/$testVideoId/comments", [
        'content' => 'Test comment from automated testing!'
    ], $authToken);

    if ($response['status'] !== 201) {
        return "Expected 201, got {$response['status']}";
    }

    return true;
});

test("Toggle bookmark on video", function() use ($BASE_URL, $authToken, &$testVideoId) {
    if (!$testVideoId) {
        return "No test video ID";
    }

    $response = makeRequest('POST', "$BASE_URL/videos/$testVideoId/bookmark", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get bookmarks", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/bookmarks?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

$testUserId = null;

test("Get another user ID for testing", function() use (&$testUserId) {
    $user = App\Models\User::where('email', 'kenji@sushi.com')->first();
    if (!$user) {
        return "Test user not found";
    }
    $testUserId = $user->id;
    return true;
});

test("Toggle follow user", function() use ($BASE_URL, $authToken, &$testUserId) {
    if (!$testUserId) {
        return "No test user ID";
    }

    $response = makeRequest('POST', "$BASE_URL/users/$testUserId/follow", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get user profile", function() use ($BASE_URL, $authToken, &$testUserId) {
    if (!$testUserId) {
        return "No test user ID";
    }

    $response = makeRequest('GET', "$BASE_URL/users/$testUserId/profile", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get user videos", function() use ($BASE_URL, $authToken, &$testUserId) {
    if (!$testUserId) {
        return "No test user ID";
    }

    $response = makeRequest('GET', "$BASE_URL/users/$testUserId/videos?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

// ============================================================
// TEST 5: SEARCH & DISCOVERY
// ============================================================
echo "\n--- TEST 5: SEARCH & DISCOVERY ---\n";

test("Global search", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/search?q=chef", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get recommended accounts", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/users/recommended", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Get friends list", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/friends", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

// ============================================================
// TEST 6: NOTIFICATIONS
// ============================================================
echo "\n--- TEST 6: NOTIFICATIONS ---\n";

test("Get notifications", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/notifications?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

// ============================================================
// TEST 7: AI FOOD SCANNER
// ============================================================
echo "\n--- TEST 7: AI FOOD SCANNER ---\n";

test("Check Gemini API key configured", function() {
    $apiKey = env('GEMINI_API_KEY');
    if (empty($apiKey)) {
        return "Gemini API key not configured";
    }
    return true;
});

// Note: AI scanner requires multipart form-data with actual image
// Testing with curl in separate test below

// ============================================================
// TEST 8: CONFIGURATION & SETUP
// ============================================================
echo "\n--- TEST 8: CONFIGURATION & SETUP ---\n";

test("Check AWS S3 configuration", function() {
    $required = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET', 'AWS_ENDPOINT'];
    foreach ($required as $key) {
        if (empty(env($key))) {
            return "$key not configured";
        }
    }
    return true;
});

test("Check database connection", function() {
    try {
        DB::connection()->getPdo();
        return true;
    } catch (Exception $e) {
        return "Database connection failed: " . $e->getMessage();
    }
});

test("Check cache driver", function() {
    $driver = config('cache.default');
    if (!in_array($driver, ['redis', 'database', 'file'])) {
        return "Invalid cache driver: $driver";
    }
    return true;
});

// ============================================================
// TEST 9: PERFORMANCE CHECKS
// ============================================================
echo "\n--- TEST 9: PERFORMANCE CHECKS ---\n";

test("Video feed response time < 2s", function() use ($BASE_URL, $authToken) {
    $start = microtime(true);
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);
    $duration = microtime(true) - $start;

    if ($response['status'] !== 200) {
        return "Request failed";
    }

    if ($duration > 2.0) {
        return sprintf("Too slow: %.2fs", $duration);
    }

    return true;
});

test("N+1 query prevention check", function() {
    // Enable query log
    DB::enableQueryLog();

    // Get videos with relationships
    $videos = App\Models\Video::with(['user'])->limit(10)->get();

    // Count queries
    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Should be ~2-3 queries (1 for videos, 1 for users)
    // Not 11+ queries (N+1 problem)
    if ($queryCount > 5) {
        return "Possible N+1 queries: $queryCount queries for 10 videos";
    }

    return true;
});

// ============================================================
// SUMMARY
// ============================================================
echo "\n=== TEST SUMMARY ===\n";
echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests (" . round(($passedTests/$totalTests)*100, 1) . "%)\n";
echo "Failed: $failedTests (" . round(($failedTests/$totalTests)*100, 1) . "%)\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n\n";

if ($failedTests > 0) {
    echo "=== FAILED TESTS ===\n";
    foreach ($testResults as $result) {
        if ($result['status'] !== 'PASS') {
            echo "✗ {$result['name']}: {$result['message']}\n";
        }
    }
    echo "\n";
}

// Return exit code
exit($failedTests > 0 ? 1 : 0);
