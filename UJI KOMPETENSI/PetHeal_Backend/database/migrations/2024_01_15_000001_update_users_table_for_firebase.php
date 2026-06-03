<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->unique()->nullable()->after('id');
            $table->string('role')->default('user')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->string('photo')->nullable()->after('phone');
            $table->dropColumn('password');
            $table->dropColumn('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'role', 'phone', 'photo']);
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
        });
    }
};
