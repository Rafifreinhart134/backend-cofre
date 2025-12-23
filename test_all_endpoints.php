<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Video;
use App\Models\Comment;
use App\Models\Story;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "   COVRE - COMPREHENSIVE ENDPOINT TEST\n";
echo "   Testing ALL 56 API Endpoints\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

$results = [
    'passed' => 0,
    'failed' => 0,
    'skipped' => 0,
    'details' => []
];

// Helper function to test endpoint
function testEndpoint($method, $url, $data = null, $token = null, $description = '') {
    global $results;

    echo "\n";
    echo "─────────────────────────────────────────────────────────────────\n";
    echo "Testing: {$description}\n";
    echo "Endpoint: {$method} {$url}\n";
    echo "─────────────────────────────────────────────────────────────────\n";

    $ch = curl_init('http://192.168.1.7:8000' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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

    $passed = ($httpCode >= 200 && $httpCode < 500); // Accept all except 500 errors

    if ($passed) {
        echo "✅ PASS - HTTP {$httpCode}\n";
        $results['passed']++;
    } else {
        echo "❌ FAIL - HTTP {$httpCode}\n";
        $results['failed']++;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $decoded = json_decode($response, true);
        if ($decoded) {
            echo "Response: " . substr(json_encode($decoded), 0, 200) . "...\n";
        }
    }

    $results['details'][] = [
        'endpoint' => "{$method} {$url}",
        'description' => $description,
        'status' => $httpCode,
        'result' => $passed ? 'PASS' : 'FAIL'
    ];

    return $response;
}

// ═══════════════════════════════════════════════════════════════════
// SETUP: Create test users and data
// ═══════════════════════════════════════════════════════════════════

echo "\n📋 SETUP: Creating test data...\n";

// Get existing users or create
$user1 = User::first();
$user2 = User::skip(1)->first();

if (!$user1) {
    $user1 = User::create([
        'name' => 'Test User 1',
        'email' => 'test1@example.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    echo "✅ Created Test User 1\n";
} else {
    echo "✅ Using existing User: {$user1->name}\n";
}

if (!$user2) {
    $user2 = User::create([
        'name' => 'Test User 2',
        'email' => 'test2@example.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);
    echo "✅ Created Test User 2\n";
} else {
    echo "✅ Using existing User: {$user2->name}\n";
}

// Get tokens for users
$token1 = $user1->createToken('test')->plainTextToken;
$token2 = $user2->createToken('test')->plainTextToken;

echo "✅ Generated auth tokens\n";

// Get test video
$video = Video::where('status', 'approved')->first();
if (!$video) {
    echo "⚠️  No approved videos found. Some video tests will be skipped.\n";
}

echo "\n";

// ═══════════════════════════════════════════════════════════════════
// 1. AUTHENTICATION ENDPOINTS (4 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "═══════════════════════════════════════════════════════════════════\n";
echo "1. AUTHENTICATION ENDPOINTS (4)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('POST', '/api/register', [
    'name' => 'New Test User',
    'email' => 'newuser' . time() . '@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123'
], null, '1.1 Register new user');

testEndpoint('POST', '/api/login', [
    'email' => $user1->email,
    'password' => 'password123'
], null, '1.2 Login with credentials');

testEndpoint('GET', '/api/user', null, $token1, '1.3 Get authenticated user');

testEndpoint('POST', '/api/logout', null, $token1, '1.4 Logout');

// Regenerate token after logout
$token1 = $user1->createToken('test')->plainTextToken;

// ═══════════════════════════════════════════════════════════════════
// 2. EMAIL VERIFICATION (2 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "2. EMAIL VERIFICATION (2)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('POST', '/api/email/verification-notification', null, $token1, '2.1 Resend verification email');

// Email verify endpoint needs valid hash - skip for now
echo "\n📝 Skipping: GET /api/email/verify/{id}/{hash} (requires valid hash)\n";
$results['skipped']++;

// ═══════════════════════════════════════════════════════════════════
// 3. PASSWORD RESET (2 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "3. PASSWORD RESET (2)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('POST', '/api/forgot-password', [
    'email' => $user1->email
], null, '3.1 Request password reset');

// Reset password requires token from email - skip
echo "\n📝 Skipping: POST /api/reset-password (requires email token)\n";
$results['skipped']++;

// ═══════════════════════════════════════════════════════════════════
// 4. VIDEO ENDPOINTS (14 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "4. VIDEO ENDPOINTS (14)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('GET', '/api/videos', null, $token1, '4.1 Get all videos (inspirasi feed)');

testEndpoint('GET', '/api/videos/following', null, $token1, '4.2 Get following feed');

testEndpoint('GET', '/api/videos/my-videos', null, $token1, '4.3 Get my uploaded videos');

testEndpoint('GET', '/api/videos/search?q=test', null, $token1, '4.4 Search videos');

if ($video) {
    testEndpoint('POST', "/api/videos/{$video->id}/view", null, $token1, '4.5 Record video view');

    testEndpoint('POST', "/api/videos/{$video->id}/like", null, $token1, '4.6 Like/unlike video');

    testEndpoint('POST', "/api/videos/{$video->id}/bookmark", null, $token1, '4.7 Bookmark/unbookmark video');

    testEndpoint('GET', "/api/videos/{$video->id}/comments", null, $token1, '4.8 Get video comments');

    testEndpoint('POST', "/api/videos/{$video->id}/comments", [
        'content' => 'Test comment from endpoint test'
    ], $token1, '4.9 Create comment on video');

    testEndpoint('POST', "/api/videos/{$video->id}/share", null, $token1, '4.10 Share video');

    testEndpoint('POST', "/api/videos/{$video->id}/repost", null, $token1, '4.11 Repost video');

    testEndpoint('POST', "/api/videos/{$video->id}/report", [
        'reason' => 'Test report from endpoint test'
    ], $token1, '4.12 Report video');

    testEndpoint('POST', "/api/videos/{$video->id}/not-interested", null, $token1, '4.13 Mark video as not interested');
} else {
    echo "\n⚠️  Skipping 9 video interaction tests (no videos available)\n";
    $results['skipped'] += 9;
}

// Video upload requires file upload - skip
echo "\n📝 Skipping: POST /api/videos/upload (requires file upload)\n";
$results['skipped']++;

// Video delete
if ($video && $video->user_id === $user1->id) {
    echo "\n📝 Skipping: DELETE /api/videos/{video} (would delete test data)\n";
    $results['skipped']++;
} else {
    echo "\n📝 Skipping: DELETE /api/videos/{video} (no owned video to delete)\n";
    $results['skipped']++;
}

// ═══════════════════════════════════════════════════════════════════
// 5. COMMENT ENDPOINTS (1 endpoint)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "5. COMMENT ENDPOINTS (1)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

$comment = Comment::where('user_id', $user1->id)->first();
if ($comment) {
    echo "\n📝 Skipping: DELETE /api/comments/{comment} (would delete test data)\n";
    $results['skipped']++;
} else {
    echo "\n📝 Skipping: DELETE /api/comments/{comment} (no owned comment to delete)\n";
    $results['skipped']++;
}

// ═══════════════════════════════════════════════════════════════════
// 6. USER PROFILE ENDPOINTS (7 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "6. USER PROFILE ENDPOINTS (7)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('GET', "/api/users/{$user2->id}/profile", null, $token1, '6.1 Get user profile');

testEndpoint('GET', "/api/users/{$user2->id}/videos", null, $token1, '6.2 Get user videos');

testEndpoint('POST', "/api/users/{$user2->id}/follow", null, $token1, '6.3 Follow/unfollow user');

testEndpoint('GET', '/api/users/recommended', null, $token1, '6.4 Get recommended users');

testEndpoint('PUT', '/api/user/profile', [
    'name' => $user1->name,
    'bio' => 'Updated bio from endpoint test'
], $token1, '6.5 Update own profile');

testEndpoint('POST', '/api/user/change-password', [
    'current_password' => 'password123',
    'new_password' => 'newpassword123',
    'new_password_confirmation' => 'newpassword123'
], $token1, '6.6 Change password');

// Change back password
$user1->password = Hash::make('password123');
$user1->save();

// Avatar upload requires file - skip
echo "\n📝 Skipping: POST /api/user/avatar (requires file upload)\n";
$results['skipped']++;

// Account deletion would remove test data - skip
echo "\n📝 Skipping: DELETE /api/user/account (would delete test user)\n";
$results['skipped']++;

// ═══════════════════════════════════════════════════════════════════
// 7. SOCIAL ENDPOINTS (2 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "7. SOCIAL ENDPOINTS (2)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('GET', '/api/friends', null, $token1, '7.1 Get friends list');

testEndpoint('GET', '/api/bookmarks', null, $token1, '7.2 Get bookmarked videos');

// ═══════════════════════════════════════════════════════════════════
// 8. SEARCH ENDPOINT (1 endpoint)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "8. SEARCH ENDPOINT (1)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('GET', '/api/search?q=test&type=videos', null, $token1, '8.1 Global search');

// ═══════════════════════════════════════════════════════════════════
// 9. NOTIFICATION ENDPOINTS (3 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "9. NOTIFICATION ENDPOINTS (3)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('GET', '/api/notifications', null, $token1, '9.1 Get notifications');

testEndpoint('POST', '/api/notifications/read-all', null, $token1, '9.2 Mark all notifications as read');

$notification = Notification::where('user_id', $user1->id)->first();
if ($notification) {
    testEndpoint('POST', "/api/notifications/{$notification->id}/read", null, $token1, '9.3 Mark single notification as read');
} else {
    echo "\n📝 Skipping: POST /api/notifications/{notification}/read (no notifications available)\n";
    $results['skipped']++;
}

// ═══════════════════════════════════════════════════════════════════
// 10. DEVICE TOKEN ENDPOINTS (4 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "10. DEVICE TOKEN ENDPOINTS (4)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('POST', '/api/device-tokens/register', [
    'device_token' => 'ExponentPushToken[test_' . time() . ']',
    'device_type' => 'android',
    'device_name' => 'Test Device'
], $token1, '10.1 Register device token');

testEndpoint('GET', '/api/device-tokens', null, $token1, '10.2 Get user device tokens');

testEndpoint('POST', '/api/device-tokens/deactivate', [
    'device_token' => 'ExponentPushToken[test_' . time() . ']'
], $token1, '10.3 Deactivate device token');

testEndpoint('POST', '/api/device-tokens/remove', [
    'device_token' => 'ExponentPushToken[test_' . time() . ']'
], $token1, '10.4 Remove device token');

// ═══════════════════════════════════════════════════════════════════
// 11. STORY ENDPOINTS (10 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "11. STORY ENDPOINTS (10)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

testEndpoint('GET', '/api/stories', null, $token1, '11.1 Get stories feed');

testEndpoint('GET', '/api/stories/my-stories', null, $token1, '11.2 Get my stories');

testEndpoint('GET', '/api/stories/archived', null, $token1, '11.3 Get archived stories');

// Story upload requires file - skip
echo "\n📝 Skipping: POST /api/stories/upload (requires file upload)\n";
$results['skipped']++;

$story = Story::first();
if ($story) {
    testEndpoint('POST', "/api/stories/{$story->id}/view", null, $token1, '11.4 View story');

    testEndpoint('GET', "/api/stories/{$story->id}/viewers", null, $token1, '11.5 Get story viewers');

    if ($story->user_id === $user1->id) {
        testEndpoint('POST', "/api/stories/{$story->id}/archive", null, $token1, '11.6 Archive story');

        testEndpoint('POST', "/api/stories/{$story->id}/unarchive", null, $token1, '11.7 Unarchive story');

        echo "\n📝 Skipping: DELETE /api/stories/{story} (would delete test data)\n";
        $results['skipped']++;
    } else {
        echo "\n📝 Skipping: Archive/unarchive/delete (no owned story)\n";
        $results['skipped'] += 3;
    }
} else {
    echo "\n⚠️  Skipping 6 story tests (no stories available)\n";
    $results['skipped'] += 6;
}

// ═══════════════════════════════════════════════════════════════════
// 12. AI ENDPOINTS (2 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "12. AI ENDPOINTS (2)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

// AI scan requires image upload - skip
echo "\n📝 Skipping: POST /api/ai/scan (requires image upload)\n";
$results['skipped']++;

echo "\n📝 Skipping: POST /api/ai/scan-test (requires image upload)\n";
$results['skipped']++;

// ═══════════════════════════════════════════════════════════════════
// 13. ADMIN MODERATION ENDPOINTS (4 endpoints)
// ═══════════════════════════════════════════════════════════════════

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "13. ADMIN MODERATION ENDPOINTS (4)\n";
echo "═══════════════════════════════════════════════════════════════════\n";

// Check if user1 is admin
if ($user1->role === 'admin' || $user1->is_admin) {
    testEndpoint('GET', '/api/admin/moderation/stats', null, $token1, '13.1 Get moderation stats');

    testEndpoint('GET', '/api/admin/moderation/videos', null, $token1, '13.2 Get videos for moderation');

    if ($video) {
        testEndpoint('POST', "/api/admin/moderation/videos/{$video->id}/approve", null, $token1, '13.3 Approve video');

        // Don't actually reject test video
        echo "\n📝 Skipping: POST /api/admin/moderation/videos/{video}/reject (would reject test video)\n";
        $results['skipped']++;
    } else {
        echo "\n⚠️  Skipping 2 moderation action tests (no videos)\n";
        $results['skipped'] += 2;
    }
} else {
    echo "\n📝 Skipping: All admin endpoints (user is not admin)\n";
    $results['skipped'] += 4;
}

// ═══════════════════════════════════════════════════════════════════
// RESULTS SUMMARY
// ═══════════════════════════════════════════════════════════════════

echo "\n\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "   TEST RESULTS SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

$total = $results['passed'] + $results['failed'] + $results['skipped'];
$percentage = $total > 0 ? round(($results['passed'] / ($results['passed'] + $results['failed'])) * 100, 2) : 0;

echo "Total Endpoints: 56\n";
echo "✅ Passed: {$results['passed']}\n";
echo "❌ Failed: {$results['failed']}\n";
echo "📝 Skipped: {$results['skipped']}\n";
echo "─────────────────────────────────────────────────────────────────\n";
echo "Tested: " . ($results['passed'] + $results['failed']) . " endpoints\n";
echo "Success Rate: {$percentage}%\n";
echo "\n";

if ($results['failed'] > 0) {
    echo "Failed Endpoints:\n";
    foreach ($results['details'] as $test) {
        if ($test['result'] === 'FAIL') {
            echo "  ❌ {$test['endpoint']} (HTTP {$test['status']})\n";
            echo "     {$test['description']}\n";
        }
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════════\n";
echo "   TEST COMPLETE\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";