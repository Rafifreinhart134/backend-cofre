<?php

/**
 * API Endpoint Testing Script
 * Tests actual HTTP endpoints that mobile app will use
 */

$baseUrl = 'http://192.168.1.7:8000/api';
$results = ['passed' => 0, 'failed' => 0];

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║               API ENDPOINT TESTING (HTTP)                          ║\n";
echo "║               Base URL: $baseUrl                ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

function testEndpoint($name, $method, $url, $data = null, $token = null, &$results) {
    echo str_pad("  ▸ $name...", 60);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HEADER, false);

    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo " ✗ FAIL\n";
        echo "    → cURL Error: $error\n";
        $results['failed']++;
        return null;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        echo " ✓ PASS ($httpCode)\n";
        $results['passed']++;
        return json_decode($response, true);
    } else {
        echo " ✗ FAIL ($httpCode)\n";
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['message'])) {
            echo "    → " . $decoded['message'] . "\n";
        }
        $results['failed']++;
        return null;
    }
}

echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ AUTHENTICATION ENDPOINTS                                         │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

// Test register
$registerData = [
    'name' => 'API Test User',
    'email' => 'apitest_' . time() . '@test.com',
    'password' => 'password123',
    'password_confirmation' => 'password123',
];
$registerResponse = testEndpoint(
    "POST /register",
    'POST',
    "$baseUrl/register",
    $registerData,
    null,
    $results
);

$token = null;
if ($registerResponse && isset($registerResponse['token'])) {
    $token = $registerResponse['token'];
    echo "    → Token: " . substr($token, 0, 20) . "...\n";
}

// Test login
$loginData = [
    'email' => $registerData['email'],
    'password' => 'password123',
];
testEndpoint(
    "POST /login",
    'POST',
    "$baseUrl/login",
    $loginData,
    null,
    $results
);

echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ VIDEO ENDPOINTS                                                  │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

// Test get videos (feed)
$feedResponse = testEndpoint(
    "GET /videos (Feed)",
    'GET',
    "$baseUrl/videos?page=1",
    null,
    $token,
    $results
);

if ($feedResponse && isset($feedResponse['data'])) {
    echo "    → Videos in feed: " . count($feedResponse['data']) . "\n";
    if (count($feedResponse['data']) > 0) {
        $video = $feedResponse['data'][0];
        echo "    → Sample video: ID={$video['id']}, status={$video['status']}\n";
    }
}

// Test get following videos
testEndpoint(
    "GET /videos/following",
    'GET',
    "$baseUrl/videos/following?page=1",
    null,
    $token,
    $results
);

// Test search videos
testEndpoint(
    "GET /videos/search",
    'GET',
    "$baseUrl/videos/search?q=food&page=1",
    null,
    $token,
    $results
);

// Test my videos
testEndpoint(
    "GET /videos/my-videos",
    'GET',
    "$baseUrl/videos/my-videos?page=1",
    null,
    $token,
    $results
);

echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ SOCIAL INTERACTION ENDPOINTS                                     │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

// Need a video ID for social interactions
if ($feedResponse && isset($feedResponse['data'][0]['id'])) {
    $videoId = $feedResponse['data'][0]['id'];

    // Test like video
    testEndpoint(
        "POST /videos/{id}/like",
        'POST',
        "$baseUrl/videos/$videoId/like",
        [],
        $token,
        $results
    );

    // Test bookmark video
    testEndpoint(
        "POST /videos/{id}/bookmark",
        'POST',
        "$baseUrl/videos/$videoId/bookmark",
        [],
        $token,
        $results
    );

    // Test add comment
    testEndpoint(
        "POST /videos/{id}/comments",
        'POST',
        "$baseUrl/videos/$videoId/comments",
        ['content' => 'Test comment from API test'],
        $token,
        $results
    );

    // Test get comments
    testEndpoint(
        "GET /videos/{id}/comments",
        'GET',
        "$baseUrl/videos/$videoId/comments?page=1",
        null,
        $token,
        $results
    );

    // Test record view
    testEndpoint(
        "POST /videos/{id}/view",
        'POST',
        "$baseUrl/videos/$videoId/view",
        [],
        $token,
        $results
    );
}

echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ USER & PROFILE ENDPOINTS                                         │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

// Test get recommended accounts
$accountsResponse = testEndpoint(
    "GET /users/recommended",
    'GET',
    "$baseUrl/users/recommended",
    null,
    $token,
    $results
);

// Test search content
testEndpoint(
    "GET /search",
    'GET',
    "$baseUrl/search?q=test",
    null,
    $token,
    $results
);

// Test get bookmarks
testEndpoint(
    "GET /bookmarks",
    'GET',
    "$baseUrl/bookmarks?page=1",
    null,
    $token,
    $results
);

// Test follow user (if we have recommended accounts)
if ($accountsResponse && count($accountsResponse) > 0) {
    $userId = $accountsResponse[0]['id'];
    testEndpoint(
        "POST /users/{id}/follow",
        'POST',
        "$baseUrl/users/$userId/follow",
        [],
        $token,
        $results
    );

    // Test get user profile
    testEndpoint(
        "GET /users/{id}/profile",
        'GET',
        "$baseUrl/users/$userId/profile",
        null,
        $token,
        $results
    );

    // Test get user videos
    testEndpoint(
        "GET /users/{id}/videos",
        'GET',
        "$baseUrl/users/$userId/videos?page=1",
        null,
        $token,
        $results
    );
}

echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ STORIES ENDPOINTS                                                │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

testEndpoint(
    "GET /stories",
    'GET',
    "$baseUrl/stories",
    null,
    $token,
    $results
);

testEndpoint(
    "GET /stories/my-stories",
    'GET',
    "$baseUrl/stories/my-stories",
    null,
    $token,
    $results
);

echo "\n┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ NOTIFICATIONS ENDPOINTS                                          │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n";

testEndpoint(
    "GET /notifications",
    'GET',
    "$baseUrl/notifications?page=1",
    null,
    $token,
    $results
);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                     API TEST SUMMARY                               ║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";
echo "║  ✓ PASSED:   " . str_pad($results['passed'], 52) . "║\n";
echo "║  ✗ FAILED:   " . str_pad($results['failed'], 52) . "║\n";
echo "╠════════════════════════════════════════════════════════════════════╣\n";

$total = $results['passed'] + $results['failed'];
$passRate = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

if ($results['failed'] === 0) {
    echo "║  Status: ✓ ALL API ENDPOINTS WORKING";
    echo str_pad("", 32) . "║\n";
} else {
    echo "║  Status: ⚠ SOME ENDPOINTS FAILING";
    echo str_pad("", 34) . "║\n";
}

echo "║  Pass Rate: {$passRate}%";
echo str_pad("", 55 - strlen("{$passRate}%")) . "║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

exit($results['failed'] > 0 ? 1 : 0);
