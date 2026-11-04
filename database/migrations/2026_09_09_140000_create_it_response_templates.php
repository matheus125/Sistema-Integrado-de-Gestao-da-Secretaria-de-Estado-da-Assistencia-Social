<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_response_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('key', 120)->nullable();
            $table->text('body');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('support_team_id')->nullable()->constrained('it_support_teams')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'support_team_id']);
            $table->index(['active', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_response_templates');
    }
};
