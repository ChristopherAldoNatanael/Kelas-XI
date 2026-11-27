<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_type', 30);
            $table->string('status', 20)->default('received');
            $table->text('description');
            $table->timestamp('requested_at');
            $table->timestamp('due_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('response_path', 255)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'privacy_requests_user_status_index');
            $table->index(['status', 'due_at'], 'privacy_requests_status_due_index');
            $table->index(['clinic_id', 'status'], 'privacy_requests_clinic_status_index');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->timestamp('legal_hold_release_requested_at')->nullable();
            $table->foreignId('legal_hold_release_requested_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('legal_hold_release_reason')->nullable();
            $table->timestamp('legal_hold_release_approved_at')->nullable();
            $table->foreignId('legal_hold_release_approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('consents', function (Blueprint $table) {
            $table->foreignId('privacy_request_id')->nullable()
                ->constrained('privacy_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('privacy_request_id');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_hold_release_requested_by');
            $table->dropConstrainedForeignId('legal_hold_release_approved_by');
            $table->dropColumn([
                'legal_hold_release_requested_at',
                'legal_hold_release_reason',
                'legal_hold_release_approved_at',
            ]);
        });

        Schema::dropIfExists('privacy_requests');
    }
};
