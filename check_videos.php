<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LATEST UPLOADED VIDEOS ===\n\n";

$videos = App\Models\Video::orderBy('created_at', 'desc')
    ->take(5)
    ->get(['id', 'title', 's3_url', 'thumbnail_url', 'media_type', 'created_at']);

foreach ($videos as $video) {
    echo "ID: {$video->id}\n";
    echo "Title: {$video->title}\n";
    echo "Media Type: {$video->media_type}\n";
    echo "Video URL: {$video->s3_url}\n";
    echo "Thumb URL: {$video->thumbnail_url}\n";
    echo "Created: {$video->created_at}\n";
    echo "-----------------------------------\n\n";
}

echo "\nTotal videos in database: " . App\Models\Video::count() . "\n";
