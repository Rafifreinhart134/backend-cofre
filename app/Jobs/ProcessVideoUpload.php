<?php

namespace App\Jobs;

use App\Models\Video;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;

class ProcessVideoUpload implements ShouldQueue
{
    use Queueable;

    protected $video;
    protected $videoPath;

    /**
     * Create a new job instance.
     */
    public function __construct(Video $video, string $videoPath)
    {
        $this->video = $video;
        $this->videoPath = $videoPath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting video processing for video ID: {$this->video->id}");

            // Get full path to video file
            $storagePath = storage_path('app/public/' . $this->videoPath);

            if (!file_exists($storagePath)) {
                Log::error("Video file not found: {$storagePath}");
                return;
            }

            // Check if FFmpeg is available
            if (!$this->isFfmpegInstalled()) {
                Log::warning("FFmpeg not installed. Skipping video processing for video ID: {$this->video->id}");
                $this->video->update(['status' => 'approved']);
                return;
            }

            // Initialize FFMpeg
            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
                'timeout'          => 3600,
                'ffmpeg.threads'   => 2,
            ]);

            $video = $ffmpeg->open($storagePath);

            // Get video duration
            $duration = $video->getFFProbe()
                ->format($storagePath)
                ->get('duration');

            Log::info("Video duration: {$duration} seconds");

            // Auto-cut to 1 minute (60 seconds) if longer
            if ($duration > 60) {
                Log::info("Video is longer than 60 seconds. Trimming to 60 seconds.");
                $video->clip(TimeCode::fromSeconds(0), TimeCode::fromSeconds(60));
            }

            // Create compressed format (360p or 480p)
            $format = new X264();
            $format->setKiloBitrate(800); // 800 kbps for good quality/size balance
            $format->setAudioKiloBitrate(128);

            // Generate processed filename
            $pathInfo = pathinfo($this->videoPath);
            $processedFilename = $pathInfo['filename'] . '_processed.' . $pathInfo['extension'];
            $processedPath = $pathInfo['dirname'] . '/' . $processedFilename;
            $processedStoragePath = storage_path('app/public/' . $processedPath);

            // Save processed video with compression (resize to 480p)
            $video
                ->filters()
                ->resize(new \FFMpeg\Coordinate\Dimension(854, 480))
                ->synchronize();

            $video->save($format, $processedStoragePath);

            Log::info("Video processed successfully. New path: {$processedPath}");

            // Delete original file
            if (file_exists($storagePath)) {
                unlink($storagePath);
            }

            // Update video record with new path
            $processedUrl = url('storage/' . $processedPath);
            $this->video->update([
                's3_url' => $processedUrl,
                'status' => 'approved',
            ]);

            Log::info("Video processing completed for video ID: {$this->video->id}");

        } catch (\Exception $e) {
            Log::error("Video processing failed for video ID: {$this->video->id}. Error: " . $e->getMessage());

            // Still approve the video even if processing failed
            $this->video->update(['status' => 'approved']);
        }
    }

    /**
     * Check if FFmpeg is installed
     */
    protected function isFfmpegInstalled(): bool
    {
        $ffmpegPath = env('FFMPEG_BINARY', '/usr/bin/ffmpeg');
        return file_exists($ffmpegPath) || !empty(shell_exec('which ffmpeg'));
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Video processing job failed for video ID: {$this->video->id}. Error: " . $exception->getMessage());

        // Approve video anyway so it's not stuck in pending
        $this->video->update(['status' => 'approved']);
    }
}
