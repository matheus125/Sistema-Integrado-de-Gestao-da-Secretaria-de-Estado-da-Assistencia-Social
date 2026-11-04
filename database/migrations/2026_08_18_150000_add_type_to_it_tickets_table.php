<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->string('type', 40)->default('incidente')->after('category_id');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn('type');
        });
    }
};
