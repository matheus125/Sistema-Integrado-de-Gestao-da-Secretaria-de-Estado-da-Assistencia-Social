<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_ticket_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained('it_service_catalog')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pendente');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->string('approval_status', 20)->nullable()->after('service_catalog_id');
            $table->timestamp('approval_requested_at')->nullable()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approval_requested_at']);
        });
        Schema::dropIfExists('it_ticket_approvals');
    }
};
