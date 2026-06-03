<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Track pet vaccinations for health monitoring and reminders.
     */
    public function up(): void
    {
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->string('vaccine_name')->comment('e.g., Rabies, Distemper, Parvovirus');
            $table->string('batch_number')->nullable()->comment('Vaccine batch/lot number');
            $table->date('date_administered')->comment('When vaccination was given');
            $table->date('next_due_date')->nullable()->comment('When next vaccination is due');
            $table->string('veterinarian')->nullable()->comment('Vet who administered vaccine');
            $table->text('notes')->nullable()->comment('Side effects, reactions, etc.');
            $table->boolean('reminder_sent')->default(false)->comment('Whether reminder was sent');
            $table->timestamps();
            
            $table->index(['pet_id', 'next_due_date'], 'idx_pet_vaccination_due');
            $table->index(['next_due_date', 'reminder_sent'], 'idx_upcoming_vaccinations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
    }
};
