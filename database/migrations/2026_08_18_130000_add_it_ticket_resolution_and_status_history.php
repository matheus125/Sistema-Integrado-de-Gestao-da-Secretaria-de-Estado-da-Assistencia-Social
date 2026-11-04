<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('resolved_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('resolved_by')->constrained('users')->nullOnDelete();
            $table->text('resolution_summary')->nullable()->after('description');
            $table->text('pending_reason')->nullable()->after('resolution_summary');
            $table->unsignedSmallInteger('reopen_count')->default(0)->after('service_time_minutes');
            $table->index(['priority', 'status']);
            $table->index(['assigned_to', 'sla_due_at']);
        });

        Schema::create('it_ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_ticket_status_histories');

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropIndex(['priority', 'status']);
            $table->dropIndex(['assigned_to', 'sla_due_at']);
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn([
                'resolution_summary',
                'pending_reason',
                'reopen_count',
            ]);
        });
    }
};
