<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class TestLoginCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:login {email=admin@sekolah.com} {password=password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test login credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $this->info('🔐 Testing Login Credentials...');
        $this->info('===============================');
        $this->newLine();

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ User not found: {$email}");
            $this->newLine();
            $this->info("📋 Available users:");
            $users = User::select('email', 'nama', 'role', 'status')->get();
            foreach ($users as $u) {
                $this->line("   - {$u->email} ({$u->nama}) - Role: {$u->role} - Status: {$u->status}");
            }
            return 1;
        }

        $this->info("👤 User found:");
        $this->line("   ID: {$user->id}");
        $this->line("   Name: {$user->nama}");
        $this->line("   Email: {$user->email}");
        $this->line("   Role: {$user->role}");
        $this->line("   Status: {$user->status}");
        $this->newLine();

        // Test password
        $this->info("🔑 Testing password...");
        $passwordCheck = Hash::check($password, $user->password);

        if ($passwordCheck) {
            $this->info("✅ PASSWORD IS CORRECT!");
            $this->newLine();
            $this->info("🎉 Login credentials are valid:");
            $this->line("   Email: {$email}");
            $this->line("   Password: {$password}");

            // Test Laravel Auth attempt
            $this->newLine();
            $this->info("🔐 Testing Laravel Auth::attempt...");

            // Test 1: Basic attempt
            $attempt1 = Auth::attempt([
                'email' => $email,
                'password' => $password
            ]);

            $this->line("   Test 1 (basic): " . ($attempt1 ? '✅ SUCCESS' : '❌ FAILED'));

            // Test 2: With status check
            $attempt2 = Auth::attempt([
                'email' => $email,
                'password' => $password,
                'status' => 'active'
            ]);

            $this->line("   Test 2 (with status): " . ($attempt2 ? '✅ SUCCESS' : '❌ FAILED'));

            // Test 3: Manual check like AuthController
            Auth::logout(); // Reset auth state
            $manualUser = User::where('email', $email)->where('status', 'active')->first();
            $manualCheck = $manualUser && Hash::check($password, $manualUser->password);

            $this->line("   Test 3 (manual check): " . ($manualCheck ? '✅ SUCCESS' : '❌ FAILED'));

            if ($attempt1 || $attempt2) {
                $authUser = Auth::user();
                $this->line("   Authenticated as: {$authUser->nama}");
            } else {
                $this->error("   All Auth::attempt() tests FAILED!");
                $this->line("   This suggests there might be an issue with:");
                $this->line("   - User model configuration");
                $this->line("   - Auth guards configuration");
                $this->line("   - Database connection");

                // Additional debugging
                $this->newLine();
                $this->info("🔍 Additional debugging:");
                $this->line("   Auth guard: " . config('auth.defaults.guard'));
                $this->line("   Auth provider: " . config('auth.guards.web.provider'));
                $this->line("   User model: " . config('auth.providers.users.model'));
            }
        } else {
            $this->error("❌ Password is INCORRECT!");
            $this->line("   Hash in DB: " . substr($user->password, 0, 30) . "...");
            $this->line("   Testing with: {$password}");

            // Try to fix it
            $this->newLine();
            $this->info("🔧 Fixing password...");
            $user->password = Hash::make($password);
            $user->save();
            $this->info("✅ Password updated! Try logging in again.");
        }

        // Check user status
        if ($user->status !== 'active') {
            $this->newLine();
            $this->warn("⚠️  WARNING: User status is '{$user->status}' (should be 'active')");
            if ($this->confirm('🔧 Do you want to activate this user?')) {
                $user->status = 'active';
                $user->save();
                $this->info("✅ User activated!");
            }
        }

        return 0;
    }
}
