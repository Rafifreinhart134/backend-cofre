<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BadgeApplicationController extends Controller
{
    /**
     * Apply for creator badge
     */
    public function apply(Request $request)
    {
        $user = $request->user();

        // Check if user already has pending or approved application
        if ($user->badge_status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah mengajukan badge creator. Mohon tunggu review dari tim kami.',
            ], 400);
        }

        if ($user->badge_status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki badge creator yang disetujui.',
            ], 400);
        }

        // Validate application data
        $validated = $request->validate([
            'badge_application_reason' => 'required|string|min:50|max:500',
            'badge_is_culinary_creator' => 'required|boolean',
        ], [
            'badge_application_reason.required' => 'Alasan harus diisi',
            'badge_application_reason.min' => 'Alasan minimal 50 karakter',
            'badge_application_reason.max' => 'Alasan maksimal 500 karakter',
            'badge_is_culinary_creator.required' => 'Pilih apakah Anda creator konten kuliner',
        ]);

        // Update user with badge application
        $user->update([
            'badge_status' => 'pending',
            'badge_application_reason' => $validated['badge_application_reason'],
            'badge_is_culinary_creator' => $validated['badge_is_culinary_creator'],
            'badge_applied_at' => now(),
        ]);

        // Send push notification
        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUser(
                $user,
                'Permohonan Badge Creator',
                'Permohonan badge creator sedang ditinjau. Tunggu tim kami untuk mereview akun Anda.',
                ['type' => 'badge_pending', 'badge_status' => 'pending']
            );
        } catch (\Exception $e) {
            Log::error('Failed to send badge application notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Permohonan badge creator berhasil diajukan',
            'badge_status' => 'pending',
        ]);
    }

    /**
     * Get badge application status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'badge_status' => $user->badge_status,
            'badge_application_reason' => $user->badge_application_reason,
            'badge_is_culinary_creator' => $user->badge_is_culinary_creator,
            'badge_applied_at' => $user->badge_applied_at,
            'badge_rejection_reason' => $user->badge_rejection_reason,
            'show_badge' => $user->show_badge ?? true,
        ]);
    }

    /**
     * Reapply for badge after rejection
     */
    public function reapply(Request $request)
    {
        $user = $request->user();

        // Check if user was previously rejected
        if ($user->badge_status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Anda hanya dapat mengajukan ulang jika permohonan sebelumnya ditolak.',
            ], 400);
        }

        // Validate new application data
        $validated = $request->validate([
            'badge_application_reason' => 'required|string|min:50|max:500',
            'badge_is_culinary_creator' => 'required|boolean',
        ], [
            'badge_application_reason.required' => 'Alasan harus diisi',
            'badge_application_reason.min' => 'Alasan minimal 50 karakter',
            'badge_application_reason.max' => 'Alasan maksimal 500 karakter',
            'badge_is_culinary_creator.required' => 'Pilih apakah Anda creator konten kuliner',
        ]);

        // Update user with new application
        $user->update([
            'badge_status' => 'pending',
            'badge_application_reason' => $validated['badge_application_reason'],
            'badge_is_culinary_creator' => $validated['badge_is_culinary_creator'],
            'badge_applied_at' => now(),
            'badge_rejection_reason' => null, // Clear rejection reason
        ]);

        // Send push notification
        try {
            $pushService = app(PushNotificationService::class);
            $pushService->sendToUser(
                $user,
                'Permohonan Badge Creator',
                'Permohonan badge creator Anda sedang ditinjau kembali.',
                ['type' => 'badge_pending', 'badge_status' => 'pending']
            );
        } catch (\Exception $e) {
            Log::error('Failed to send badge reapplication notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Permohonan badge creator berhasil diajukan ulang',
            'badge_status' => 'pending',
        ]);
    }

    /**
     * Toggle badge visibility
     */
    public function toggleVisibility(Request $request)
    {
        $user = $request->user();

        // Only approved badges can be toggled
        if ($user->badge_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya badge yang disetujui yang dapat disembunyikan atau ditampilkan.',
            ], 400);
        }

        $validated = $request->validate([
            'show_badge' => 'required|boolean',
        ]);

        $user->update([
            'show_badge' => $validated['show_badge'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $validated['show_badge']
                ? 'Badge creator ditampilkan di profil Anda'
                : 'Badge creator disembunyikan dari profil Anda',
            'show_badge' => $validated['show_badge'],
        ]);
    }
}
