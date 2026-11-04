<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->foreignId('rejected_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('rejected_by')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('notes');
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('started_at')->nullable()->after('cancelled_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::create('vehicle_request_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_request_id')->constrained('vehicle_requests')->cascadeOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->unsignedTinyInteger('fuel_level')->nullable();
            $table->unsignedInteger('km');
            $table->boolean('documents_ok')->default(false);
            $table->boolean('tires_ok')->default(false);
            $table->boolean('lights_ok')->default(false);
            $table->boolean('bodywork_ok')->default(true);
            $table->boolean('tools_ok')->default(true);
            $table->boolean('has_damage')->default(false);
            $table->text('damages_description')->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['vehicle_request_id', 'type']);
            $table->index(['type', 'checked_at']);
        });

        Schema::create('vehicle_request_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_request_id')->constrained('vehicle_requests')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['vehicle_request_id', 'created_at'], 'vr_status_histories_request_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_request_status_histories');
        Schema::dropIfExists('vehicle_request_checklists');

        Schema::table('vehicle_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'rejection_reason',
                'cancellation_reason',
                'rejected_at',
                'started_at',
                'completed_at',
            ]);
        });
    }
};
