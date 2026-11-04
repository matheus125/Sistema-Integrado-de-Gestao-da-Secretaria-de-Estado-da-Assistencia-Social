<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('service_catalog_id')->nullable()->after('category_id')->constrained('it_service_catalog')->nullOnDelete();
            $table->index('service_catalog_id');
        });
    }

    public function down(): void
    {
        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_catalog_id');
        });
    }
};
