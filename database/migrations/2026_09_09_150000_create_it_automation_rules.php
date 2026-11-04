<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('event', 40)->default('ticket_created');
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['event', 'active']);
        });
    }

    public function down(): void { Schema::dropIfExists('it_automation_rules'); }
};
