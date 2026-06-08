<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateTokens = DB::table('device_tokens')
            ->select('token', DB::raw('MAX(id) as keep_id'))
            ->groupBy('token')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateTokens as $duplicate) {
            DB::table('device_tokens')
                ->where('token', $duplicate->token)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('device_tokens', function (Blueprint $table) {
            $table->unique('token', 'uniq_device_tokens_token');
            $table->index(['user_id', 'updated_at'], 'idx_device_tokens_user_updated');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique('uniq_device_tokens_token');
            $table->dropIndex('idx_device_tokens_user_updated');
        });
    }
};
