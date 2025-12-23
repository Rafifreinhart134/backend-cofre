<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    /**
     * Scan food image using Google Gemini AI
     */
    public function scan(Request $request)
    {
        // Validate request
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png|max:10240', // max 10MB
        ]);

        try {
            // Get uploaded image
            $image = $request->file('image');

            // Convert image to Base64
            $imageContent = file_get_contents($image->getRealPath());
            $base64Image = base64_encode($imageContent);

            // Get MIME type
            $mimeType = $image->getMimeType();

            // Prepare Gemini API request
            $apiKey = env('GEMINI_API_KEY');

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gemini API key not configured',
                ], 500);
            }

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

            // Prepare payload
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Analyze this food image and identify ALL individual food items/components visible. For each item, estimate calories separately.\n\nRETURN ONLY RAW JSON (No Markdown, No backticks):\n{\n  \"is_food\": true,\n  \"items\": [\n    {\"name\": \"Nasi Goreng\", \"calories\": 550, \"description\": \"Fried rice with vegetables\"},\n    {\"name\": \"Ayam Goreng\", \"calories\": 90, \"description\": \"Fried chicken piece\"},\n    {\"name\": \"Telur\", \"calories\": 65, \"description\": \"Fried egg\"}\n  ],\n  \"total_calories\": 705,\n  \"price\": 25000,\n  \"ingredients\": \"nasi, telur, ayam, bawang, kecap, sayuran\",\n  \"description\": \"Nasi goreng pedas dengan ayam dan telur\"\n}\n\nIMPORTANT:\n- \"price\" must be estimated in Indonesian Rupiah (IDR)\n- \"ingredients\" must list main ingredients used\n- \"description\" is overall dish description\n- Each item in \"items\" array needs name, calories, and description\n\nIf NOT food, return: { \"is_food\": false }"
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Send request to Gemini API
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('Gemini API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to analyze image',
                    'error' => $response->body()
                ], 500);
            }

            // Parse Gemini response
            $geminiResponse = $response->json();

            // Extract text from response
            $text = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Clean markdown formatting if present
            $text = preg_replace('/```json\s*/i', '', $text);
            $text = preg_replace('/```\s*$/i', '', $text);
            $text = trim($text);

            // Decode JSON
            $result = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON Decode Error', [
                    'text' => $text,
                    'error' => json_last_error_msg()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse AI response',
                    'raw_text' => $text
                ], 500);
            }

            // Return clean JSON result
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('AI Scan Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while scanning the image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
