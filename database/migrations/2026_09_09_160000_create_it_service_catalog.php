<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_service_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('key', 120)->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('it_ticket_categories')->nullOnDelete();
            $table->string('type', 40)->nullable();
            $table->foreignId('sla_rule_id')->nullable()->constrained('it_sla_rules')->nullOnDelete();
            $table->foreignId('checklist_template_id')->nullable()->constrained('it_checklist_templates')->nullOnDelete();
            $table->boolean('requires_approval')->default(false);
            $table->string('approval_role', 120)->nullable();
            $table->json('form_fields')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['active', 'category_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('it_service_catalog'); }
};
