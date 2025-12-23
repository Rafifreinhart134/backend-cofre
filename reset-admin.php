<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('email', 'admin@covre.com')->first();

if ($user) {
    $newPassword = 'Covre2024!';
    $user->password = bcrypt($newPassword);
    $user->is_admin = true;
    $user->email_verified_at = now();
    $user->save();

    echo "✓ Password berhasil direset!\n";
    echo "Email: admin@covre.com\n";
    echo "Password Baru: {$newPassword}\n";
    echo "\nSilakan login di: https://backend-covre.fly.dev/admin\n";
} else {
    echo "✗ User admin@covre.com tidak ditemukan!\n";
    echo "Membuat user baru...\n";

    $newPassword = 'Covre2024!';
    $user = \App\Models\User::create([
        'name' => 'Super Admin',
        'email' => 'admin@covre.com',
        'password' => bcrypt($newPassword),
        'is_admin' => true,
        'email_verified_at' => now(),
    ]);

    echo "✓ User admin berhasil dibuat!\n";
    echo "Email: admin@covre.com\n";
    echo "Password: {$newPassword}\n";
}
