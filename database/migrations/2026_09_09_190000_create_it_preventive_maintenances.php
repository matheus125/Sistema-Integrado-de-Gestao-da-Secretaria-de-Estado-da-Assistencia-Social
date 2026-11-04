<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_preventive_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_ticket_id')->nullable()->constrained('it_tickets')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedInteger('frequency_days');
            $table->date('last_maintenance_at')->nullable();
            $table->date('next_maintenance_at');
            $table->unsignedInteger('lead_days')->default(7);
            $table->string('status', 20)->default('ativo');
            $table->boolean('auto_create_ticket')->default(true);
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'next_maintenance_at']);
            $table->index(['asset_id', 'status']);
        });

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('preventive_maintenance_id')->nullable()->after('primary_asset_id')->constrained('it_preventive_maintenances')->nullOnDelete();
            $table->index('preventive_maintenance_id');
        });
    }

    public function down(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropForeign(['preventive_maintenance_id']);
            $table->dropIndex(['preventive_maintenance_id']);
            $table->dropColumn('preventive_maintenance_id');
        });
        Schema::dropIfExists('it_preventive_maintenances');
    }
};
