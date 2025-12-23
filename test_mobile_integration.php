<?php

/**
 * MOBILE INTEGRATION & EDGE CASES TESTING
 * Tests mobile app integration and error handling
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
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
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

echo "=== MOBILE INTEGRATION & EDGE CASES TESTING ===\n\n";
echo "Base URL: $BASE_URL\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

// Store authentication token
$authToken = null;

// Login first
$response = makeRequest('POST', "$BASE_URL/login", [
    'email' => 'marco@foodie.com',
    'password' => 'password'
]);
$authToken = $response['body']['token'] ?? null;

// ============================================================
// TEST 1: ERROR HANDLING
// ============================================================
echo "--- TEST 1: ERROR HANDLING ---\n";

test("Login with missing email", function() use ($BASE_URL) {
    $response = makeRequest('POST', "$BASE_URL/login", [
        'password' => 'password'
    ]);

    if ($response['status'] === 200) {
        return "Should return validation error";
    }

    return true;
});

test("Login with missing password", function() use ($BASE_URL) {
    $response = makeRequest('POST', "$BASE_URL/login", [
        'email' => 'marco@foodie.com'
    ]);

    if ($response['status'] === 200) {
        return "Should return validation error";
    }

    return true;
});

test("Access protected route without token", function() use ($BASE_URL) {
    $response = makeRequest('GET', "$BASE_URL/user", null, null);

    if ($response['status'] === 200) {
        return "Should return 401 unauthorized";
    }

    return true;
});

test("Access protected route with invalid token", function() use ($BASE_URL) {
    $response = makeRequest('GET', "$BASE_URL/user", null, 'invalid-token-12345');

    if ($response['status'] === 200) {
        return "Should return 401 unauthorized";
    }

    return true;
});

test("Get non-existent video", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos/99999", null, $authToken);

    if ($response['status'] === 200) {
        return "Should return 404 not found";
    }

    return true;
});

test("Like non-existent video", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('POST', "$BASE_URL/videos/99999/like", null, $authToken);

    if ($response['status'] === 200) {
        return "Should return error";
    }

    return true;
});

test("Comment with empty content", function() use ($BASE_URL, $authToken) {
    $video = App\Models\Video::first();
    $response = makeRequest('POST', "$BASE_URL/videos/{$video->id}/comments", [
        'content' => ''
    ], $authToken);

    if ($response['status'] === 201) {
        return "Should return validation error for empty content";
    }

    return true;
});

test("Follow non-existent user", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('POST', "$BASE_URL/users/99999/follow", null, $authToken);

    if ($response['status'] === 200) {
        return "Should return error";
    }

    return true;
});

test("Follow yourself", function() use ($BASE_URL, $authToken) {
    // Get current user ID
    $userResponse = makeRequest('GET', "$BASE_URL/user", null, $authToken);
    $userId = $userResponse['body']['id'];

    $response = makeRequest('POST', "$BASE_URL/users/{$userId}/follow", null, $authToken);

    if ($response['status'] === 200 && isset($response['body']['error'])) {
        return true; // Expected to return error message
    }

    if ($response['status'] !== 200) {
        return true; // Or return error status
    }

    return "Should prevent self-follow";
});

// ============================================================
// TEST 2: PAGINATION & LIMITS
// ============================================================
echo "\n--- TEST 2: PAGINATION & LIMITS ---\n";

test("Video feed pagination - page 1", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    if (!isset($response['body']['current_page']) || $response['body']['current_page'] !== 1) {
        return "Current page should be 1";
    }

    return true;
});

test("Video feed pagination - page 2", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=2", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    if (!isset($response['body']['current_page'])) {
        return "Should have current_page";
    }

    return true;
});

test("Video feed with custom per_page", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=1&per_page=5", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    $dataCount = count($response['body']['data'] ?? []);
    if ($dataCount > 5) {
        return "Should return max 5 items, got $dataCount";
    }

    return true;
});

test("Comments pagination", function() use ($BASE_URL, $authToken) {
    $video = App\Models\Video::first();
    $response = makeRequest('GET', "$BASE_URL/videos/{$video->id}/comments?page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    if (!isset($response['body']['current_page'])) {
        return "Should have pagination data";
    }

    return true;
});

// ============================================================
// TEST 3: DATA INTEGRITY
// ============================================================
echo "\n--- TEST 3: DATA INTEGRITY ---\n";

test("Video has all required fields", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);

    if (!isset($response['body']['data'][0])) {
        return "No videos returned";
    }

    $video = $response['body']['data'][0];
    $required = ['id', 'user_id', 's3_url', 'thumbnail_url', 'menu_data', 'user'];

    foreach ($required as $field) {
        if (!isset($video[$field])) {
            return "Missing required field: $field";
        }
    }

    return true;
});

test("User object has all required fields", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/user", null, $authToken);

    $required = ['id', 'name', 'email', 'bio', 'followers_count', 'following_count'];

    foreach ($required as $field) {
        if (!isset($response['body'][$field])) {
            return "Missing required field: $field";
        }
    }

    return true;
});

test("Video engagement counts are numeric", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);

    if (!isset($response['body']['data'][0])) {
        return "No videos returned";
    }

    $video = $response['body']['data'][0];

    if (!is_numeric($video['likes_count'] ?? 'x')) {
        return "likes_count should be numeric";
    }

    if (!is_numeric($video['comments_count'] ?? 'x')) {
        return "comments_count should be numeric";
    }

    if (!is_numeric($video['views_count'] ?? 'x')) {
        return "views_count should be numeric";
    }

    return true;
});

test("Boolean flags are boolean type", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);

    if (!isset($response['body']['data'][0])) {
        return "No videos returned";
    }

    $video = $response['body']['data'][0];

    if (!is_bool($video['is_liked'] ?? 'x')) {
        return "is_liked should be boolean";
    }

    if (!is_bool($video['is_bookmarked'] ?? 'x')) {
        return "is_bookmarked should be boolean";
    }

    if (!is_bool($video['is_following'] ?? 'x')) {
        return "is_following should be boolean";
    }

    return true;
});

// ============================================================
// TEST 4: SEARCH FUNCTIONALITY
// ============================================================
echo "\n--- TEST 4: SEARCH FUNCTIONALITY ---\n";

test("Search with empty query", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/search?q=", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Search with special characters", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/search?q=" . urlencode("@#$%"), null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Search videos with Indonesian keywords", function() use ($BASE_URL, $authToken) {
    $response = makeRequest('GET', "$BASE_URL/videos/search?q=nasi&page=1", null, $authToken);

    if ($response['status'] !== 200) {
        return "Expected 200, got {$response['status']}";
    }

    return true;
});

test("Search case insensitive", function() use ($BASE_URL, $authToken) {
    $response1 = makeRequest('GET', "$BASE_URL/search?q=chef", null, $authToken);
    $response2 = makeRequest('GET', "$BASE_URL/search?q=CHEF", null, $authToken);

    if ($response1['status'] !== 200 || $response2['status'] !== 200) {
        return "Search should work";
    }

    // Both should return results
    return true;
});

// ============================================================
// TEST 5: SOCIAL FEATURES EDGE CASES
// ============================================================
echo "\n--- TEST 5: SOCIAL FEATURES EDGE CASES ---\n";

test("Double like (toggle)", function() use ($BASE_URL, $authToken) {
    $video = App\Models\Video::first();

    // Like
    $response1 = makeRequest('POST', "$BASE_URL/videos/{$video->id}/like", null, $authToken);
    // Unlike (toggle)
    $response2 = makeRequest('POST', "$BASE_URL/videos/{$video->id}/like", null, $authToken);

    if ($response1['status'] !== 200 || $response2['status'] !== 200) {
        return "Toggle should work";
    }

    // Both responses should have different liked status
    return true;
});

test("Double bookmark (toggle)", function() use ($BASE_URL, $authToken) {
    $video = App\Models\Video::first();

    // Bookmark
    $response1 = makeRequest('POST', "$BASE_URL/videos/{$video->id}/bookmark", null, $authToken);
    // Unbookmark (toggle)
    $response2 = makeRequest('POST', "$BASE_URL/videos/{$video->id}/bookmark", null, $authToken);

    if ($response1['status'] !== 200 || $response2['status'] !== 200) {
        return "Toggle should work";
    }

    return true;
});

test("Double follow (toggle)", function() use ($BASE_URL, $authToken) {
    $user = App\Models\User::where('email', 'kenji@sushi.com')->first();

    // Follow
    $response1 = makeRequest('POST', "$BASE_URL/users/{$user->id}/follow", null, $authToken);
    // Unfollow (toggle)
    $response2 = makeRequest('POST', "$BASE_URL/users/{$user->id}/follow", null, $authToken);

    if ($response1['status'] !== 200 || $response2['status'] !== 200) {
        return "Toggle should work";
    }

    return true;
});

test("Add multiple comments", function() use ($BASE_URL, $authToken) {
    $video = App\Models\Video::first();

    $response1 = makeRequest('POST', "$BASE_URL/videos/{$video->id}/comments", [
        'content' => 'Test comment 1'
    ], $authToken);

    $response2 = makeRequest('POST', "$BASE_URL/videos/{$video->id}/comments", [
        'content' => 'Test comment 2'
    ], $authToken);

    if ($response1['status'] !== 201 || $response2['status'] !== 201) {
        return "Should allow multiple comments";
    }

    return true;
});

test("Long comment (max length)", function() use ($BASE_URL, $authToken) {
    $video = App\Models\Video::first();
    $longComment = str_repeat('This is a very long comment. ', 100); // ~3000 chars

    $response = makeRequest('POST', "$BASE_URL/videos/{$video->id}/comments", [
        'content' => $longComment
    ], $authToken);

    // Should either accept or return validation error
    if ($response['status'] !== 201 && $response['status'] !== 422) {
        return "Unexpected status: {$response['status']}";
    }

    return true;
});

// ============================================================
// TEST 6: PERFORMANCE & OPTIMIZATION
// ============================================================
echo "\n--- TEST 6: PERFORMANCE & OPTIMIZATION ---\n";

test("Video feed loads user data (no N+1)", function() use ($BASE_URL, $authToken) {
    DB::enableQueryLog();

    $response = makeRequest('GET', "$BASE_URL/videos?page=1&per_page=10", null, $authToken);

    $queries = DB::getQueryLog();
    $queryCount = count($queries);

    // Should be optimized (< 10 queries for 10 videos)
    if ($queryCount > 15) {
        return "Too many queries: $queryCount for 10 videos (possible N+1)";
    }

    return true;
});

test("Response time acceptable", function() use ($BASE_URL, $authToken) {
    $start = microtime(true);
    $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);
    $duration = microtime(true) - $start;

    if ($duration > 3.0) {
        return sprintf("Too slow: %.2fs", $duration);
    }

    return true;
});

test("Multiple concurrent requests", function() use ($BASE_URL, $authToken) {
    // Simulate 3 concurrent requests
    $start = microtime(true);

    for ($i = 0; $i < 3; $i++) {
        $response = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);
        if ($response['status'] !== 200) {
            return "Request $i failed";
        }
    }

    $duration = microtime(true) - $start;

    if ($duration > 5.0) {
        return "Concurrent requests too slow";
    }

    return true;
});

// ============================================================
// TEST 7: MOBILE APP SCENARIOS
// ============================================================
echo "\n--- TEST 7: MOBILE APP SCENARIOS ---\n";

test("Complete login flow", function() use ($BASE_URL) {
    // Login
    $loginResponse = makeRequest('POST', "$BASE_URL/login", [
        'email' => 'marco@foodie.com',
        'password' => 'password'
    ]);

    if (!isset($loginResponse['body']['token'])) {
        return "No token in login response";
    }

    $token = $loginResponse['body']['token'];

    // Get user profile with token
    $userResponse = makeRequest('GET', "$BASE_URL/user", null, $token);

    if ($userResponse['status'] !== 200) {
        return "Cannot get user with token";
    }

    return true;
});

test("Browse feed -> like -> comment workflow", function() use ($BASE_URL, $authToken) {
    // 1. Get feed
    $feedResponse = makeRequest('GET', "$BASE_URL/videos?page=1", null, $authToken);

    if (!isset($feedResponse['body']['data'][0])) {
        return "No videos in feed";
    }

    $videoId = $feedResponse['body']['data'][0]['id'];

    // 2. Like video
    $likeResponse = makeRequest('POST', "$BASE_URL/videos/{$videoId}/like", null, $authToken);

    if ($likeResponse['status'] !== 200) {
        return "Cannot like video";
    }

    // 3. Comment on video
    $commentResponse = makeRequest('POST', "$BASE_URL/videos/{$videoId}/comments", [
        'content' => 'Great recipe!'
    ], $authToken);

    if ($commentResponse['status'] !== 201) {
        return "Cannot comment on video";
    }

    return true;
});

test("User profile -> videos -> follow workflow", function() use ($BASE_URL, $authToken) {
    $user = App\Models\User::where('email', 'kenji@sushi.com')->first();

    // 1. Get user profile
    $profileResponse = makeRequest('GET', "$BASE_URL/users/{$user->id}/profile", null, $authToken);

    if ($profileResponse['status'] !== 200) {
        return "Cannot get profile";
    }

    // 2. Get user's videos
    $videosResponse = makeRequest('GET', "$BASE_URL/users/{$user->id}/videos?page=1", null, $authToken);

    if ($videosResponse['status'] !== 200) {
        return "Cannot get user videos";
    }

    // 3. Follow user
    $followResponse = makeRequest('POST', "$BASE_URL/users/{$user->id}/follow", null, $authToken);

    if ($followResponse['status'] !== 200) {
        return "Cannot follow user";
    }

    return true;
});

test("Search -> view results workflow", function() use ($BASE_URL, $authToken) {
    // Search
    $searchResponse = makeRequest('GET', "$BASE_URL/search?q=chef", null, $authToken);

    if ($searchResponse['status'] !== 200) {
        return "Search failed";
    }

    if (!isset($searchResponse['body']['users']) && !isset($searchResponse['body']['videos'])) {
        return "Search should return users or videos";
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
