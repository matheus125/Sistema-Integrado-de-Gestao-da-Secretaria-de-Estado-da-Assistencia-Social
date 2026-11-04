<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'key']);
        });

        DB::table('whatsapp_message_templates')->insert([
            ['name' => 'Chamado recebido', 'key' => 'ticket_received', 'body' => 'Recebemos seu chamado #{{ticket_id}}. Em breve nossa equipe respondera.', 'variables' => json_encode(['ticket_id']), 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chamado resolvido', 'key' => 'ticket_resolved', 'body' => 'O chamado #{{ticket_id}} foi resolvido. Responda esta mensagem se precisar reabrir o atendimento.', 'variables' => json_encode(['ticket_id']), 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_templates');
    }
};
