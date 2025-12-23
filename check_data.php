<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== DATABASE CONTENT CHECK ===\n\n";

// Check food content creators
$creators = [
    'marco@foodie.com',
    'kenji@sushi.com',
    'sarah@sweetlife.com',
    'hunter@streetfood.com',
    'rita@healthyeats.com',
    'mike@bbqmaster.com',
    'lisa@veganeats.com',
    'alex@asianfusion.com'
];

echo "FOOD CONTENT CREATORS:\n";
foreach ($creators as $email) {
    $user = App\Models\User::where('email', $email)->first();
    if ($user) {
        $videoCount = $user->videos()->count();
        echo "✓ {$user->name} ({$email}) - {$videoCount} videos\n";
    } else {
        echo "✗ {$email} - NOT FOUND\n";
    }
}

echo "\n";

// Check total counts
$totalVideos = App\Models\Video::count();
$totalLikes = App\Models\Like::count();
$totalComments = App\Models\Comment::count();
$totalViews = App\Models\View::count();
$totalBookmarks = App\Models\Bookmark::count();

echo "STATISTICS:\n";
echo "- Total Videos: {$totalVideos}\n";
echo "- Total Likes: {$totalLikes}\n";
echo "- Total Comments: {$totalComments}\n";
echo "- Total Views: {$totalViews}\n";
echo "- Total Bookmarks: {$totalBookmarks}\n";

echo "\n";

// Check sample videos
echo "SAMPLE VIDEOS:\n";
$sampleVideos = App\Models\Video::with('user')->limit(5)->get();
foreach ($sampleVideos as $video) {
    $menuData = is_string($video->menu_data)
        ? json_decode($video->menu_data, true)
        : $video->menu_data;
    $videoName = $menuData['name'] ?? 'Untitled';
    echo "- {$videoName} by {$video->user->name}\n";
    echo "  Likes: {$video->likes_count}, Views: {$video->views_count}, Comments: {$video->comments_count}\n";
}

echo "\n=== CHECK COMPLETE ===\n";
