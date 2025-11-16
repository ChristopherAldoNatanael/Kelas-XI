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
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('guru_id');
            $table->unsignedBigInteger('guru_asli_id')->nullable();
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->enum('status', ['hadir', 'telat', 'tidak_hadir', 'diganti'])->default('tidak_hadir');
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamps();

            // Indexes and constraints
            $table->unique(['schedule_id', 'guru_id', 'tanggal']);
            $table->index(['guru_id']);
            $table->index(['tanggal']);
            $table->index(['status']);

            // Foreign key constraints
            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('cascade');
            $table->foreign('guru_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('guru_asli_id')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
