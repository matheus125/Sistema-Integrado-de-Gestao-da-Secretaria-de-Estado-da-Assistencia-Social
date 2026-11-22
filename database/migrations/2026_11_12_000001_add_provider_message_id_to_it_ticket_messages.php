<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_ticket_messages', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->unique()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('it_ticket_messages', function (Blueprint $table) {
            $table->dropUnique(['provider_message_id']);
            $table->dropColumn('provider_message_id');
        });
    }
};
