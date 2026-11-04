<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained('departments')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('position')->nullable()->after('phone');
            $table->string('status')->default('ativo')->after('position');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('category')->nullable();
            $table->string('type')->nullable();
            $table->string('color')->nullable();
            $table->string('plate')->unique();
            $table->string('renavam')->nullable()->unique();
            $table->string('chassis')->nullable()->unique();
            $table->string('fuel')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status')->default('disponivel');
            $table->unsignedInteger('current_km')->default(0);
            $table->date('last_maintenance_at')->nullable();
            $table->date('next_licensing_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnh')->unique();
            $table->string('cnh_category');
            $table->date('cnh_expires_at');
            $table->string('phone')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicle_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('solicitacao');
            $table->text('route_description');
            $table->string('origin');
            $table->string('destination');
            $table->date('date');
            $table->time('departure_time');
            $table->time('expected_arrival_time')->nullable();
            $table->unsignedSmallInteger('passengers_count')->default(1);
            $table->string('responsible');
            $table->unsignedInteger('initial_km')->nullable();
            $table->unsignedInteger('final_km')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['vehicle_id', 'date', 'departure_time']);
            $table->index(['department_id', 'status']);
        });

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->string('internal_code')->unique();
            $table->string('description');
            $table->string('unit');
            $table->string('supplier')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('minimum_quantity', 12, 2)->default(0);
            $table->string('location')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 12, 2);
            $table->string('supplier')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('destination')->nullable();
            $table->string('reason')->nullable();
            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['type', 'movement_date']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset_number')->unique();
            $table->string('description');
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('acquired_at')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('notes')->nullable();
            $table->timestamp('moved_at');
            $table->timestamps();
        });

        Schema::create('it_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name');
            $table->string('requester_phone')->nullable();
            $table->string('department_name')->nullable();
            $table->text('description');
            $table->string('category')->nullable();
            $table->string('priority')->default('media');
            $table->string('status')->default('aberto');
            $table->string('source')->default('manual');
            $table->string('whatsapp_conversation_id')->nullable()->index();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->unsignedInteger('service_time_minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status']);
            $table->index(['department_id', 'status']);
        });

        Schema::create('it_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction')->default('internal');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_ticket_messages');
        Schema::dropIfExists('it_tickets');
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('vehicle_requests');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('attachments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['phone', 'position', 'status']);
        });

        Schema::dropIfExists('departments');
    }
};
