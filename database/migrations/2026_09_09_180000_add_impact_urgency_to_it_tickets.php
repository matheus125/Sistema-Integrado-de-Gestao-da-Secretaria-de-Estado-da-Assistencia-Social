<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->string('impact', 20)->nullable()->after('priority');
            $table->string('urgency', 20)->nullable()->after('impact');
            $table->index(['impact', 'urgency']);
        });
    }

    public function down(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropIndex(['impact', 'urgency']);
            $table->dropColumn(['impact', 'urgency']);
        });
    }
};
