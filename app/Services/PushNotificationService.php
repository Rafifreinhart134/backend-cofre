<?php

namespace App\Services;

use App\Models\User;
use App\Models\DeviceToken;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    protected $serverKey;
    protected $httpClient;

    public function __construct()
    {
        $this->serverKey = env('FCM_SERVER_KEY');
        $this->httpClient = new Client([
            'timeout' => 10,
            'verify' => false, // Set to true in production with proper SSL
        ]);
    }

    /**
     * Send push notification to a user
     *
     * @param User $user
     * @param string $title
     * @param string $body
     * @param array $data Additional data payload
     * @return array Result of sending notifications
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        // Get all active device tokens for this user
        $deviceTokens = $user->deviceTokens()->active()->get();

        if ($deviceTokens->isEmpty()) {
            Log::info("No active device tokens for user {$user->id}");
            return [
                'success' => false,
                'message' => 'No active device tokens',
                'sent_count' => 0,
            ];
        }

        $results = [];
        $successCount = 0;
        $failedTokens = [];

        foreach ($deviceTokens as $deviceToken) {
            try {
                $result = $this->sendToToken(
                    $deviceToken->device_token,
                    $title,
                    $body,
                    $data
                );

                if ($result['success']) {
                    $successCount++;
                    $deviceToken->markAsUsed();
                } else {
                    $failedTokens[] = $deviceToken->id;

                    // If token is invalid, deactivate it
                    if (isset($result['error']) &&
                        (str_contains($result['error'], 'InvalidRegistration') ||
                         str_contains($result['error'], 'NotRegistered'))) {
                        $deviceToken->deactivate();
                    }
                }

                $results[] = $result;
            } catch (\Exception $e) {
                Log::error("Failed to send push notification to device token {$deviceToken->id}: " . $e->getMessage());
                $failedTokens[] = $deviceToken->id;
            }
        }

        return [
            'success' => $successCount > 0,
            'sent_count' => $successCount,
            'failed_count' => count($failedTokens),
            'failed_tokens' => $failedTokens,
            'results' => $results,
        ];
    }

    /**
     * Send push notification to a specific device token
     *
     * @param string $deviceToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): array
    {
        if (empty($this->serverKey)) {
            Log::warning('FCM_SERVER_KEY not configured in .env');
            return [
                'success' => false,
                'error' => 'FCM not configured',
            ];
        }

        try {
            $payload = [
                'to' => $deviceToken,
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => 1,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // For React Native
                ],
                'data' => array_merge([
                    'title' => $title,
                    'body' => $body,
                ], $data),
            ];

            $response = $this->httpClient->post($this->fcmUrl, [
                'headers' => [
                    'Authorization' => 'key=' . $this->serverKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['success']) && $result['success'] > 0) {
                return [
                    'success' => true,
                    'message_id' => $result['results'][0]['message_id'] ?? null,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['results'][0]['error'] ?? 'Unknown error',
                ];
            }
        } catch (\Exception $e) {
            Log::error('FCM send error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToMultipleUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $users = User::whereIn('id', $userIds)->get();

        $totalSent = 0;
        $totalFailed = 0;
        $results = [];

        foreach ($users as $user) {
            $result = $this->sendToUser($user, $title, $body, $data);
            $totalSent += $result['sent_count'];
            $totalFailed += $result['failed_count'] ?? 0;
            $results[] = $result;
        }

        return [
            'success' => $totalSent > 0,
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'results' => $results,
        ];
    }
}
