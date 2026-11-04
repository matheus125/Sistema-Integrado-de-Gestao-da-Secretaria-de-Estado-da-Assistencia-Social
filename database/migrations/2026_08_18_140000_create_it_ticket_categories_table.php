<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('it_ticket_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->string('color', 40)->nullable();
            $table->string('icon', 80)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
            $table->index(['parent_id', 'sort_order']);
        });

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('department_name')
                ->constrained('it_ticket_categories')
                ->nullOnDelete();

            $table->index(['category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status']);
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('it_ticket_categories');
    }
};
