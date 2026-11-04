<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('agendada');
            $table->date('scheduled_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedInteger('next_due_km')->nullable();
            $table->date('next_due_at')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->text('description');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'status']);
            $table->index(['next_due_at', 'next_due_km']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
    }
};
