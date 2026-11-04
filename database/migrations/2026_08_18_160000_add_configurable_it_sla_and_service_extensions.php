<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_sla_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('weekdays')->nullable();
            $table->time('starts_at')->default('08:00:00');
            $table->time('ends_at')->default('17:00:00');
            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();
            $table->boolean('count_weekends')->default(false);
            $table->json('holidays')->nullable();
            $table->json('exceptions')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('it_sla_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->nullable()->constrained('it_sla_calendars')->nullOnDelete();
            $table->string('name');
            $table->string('priority')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('it_ticket_categories')->nullOnDelete();
            $table->string('type', 40)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->unsignedInteger('first_response_minutes')->default(0);
            $table->unsignedInteger('start_minutes')->default(0);
            $table->unsignedInteger('resolution_minutes')->default(0);
            $table->integer('rule_priority')->default(0);
            $table->boolean('is_fallback')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'is_fallback']);
            $table->index(['priority', 'type', 'department_id']);
        });

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('sla_rule_id')->nullable()->after('category_id')->constrained('it_sla_rules')->nullOnDelete();
            $table->foreignId('primary_asset_id')->nullable()->after('sla_rule_id')->constrained('assets')->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable()->after('first_response_at');
            $table->timestamp('started_at')->nullable()->after('first_response_due_at');
            $table->timestamp('start_due_at')->nullable()->after('started_at');
            $table->index(['sla_rule_id', 'status']);
            $table->index(['primary_asset_id', 'status']);
        });

        Schema::create('it_ticket_asset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->boolean('primary')->default(false);
            $table->timestamps();
            $table->unique(['ticket_id', 'asset_id']);
        });

        Schema::create('it_ticket_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('evaluated_at');
            $table->timestamps();
            $table->unique('ticket_id');
            $table->index(['rating', 'evaluated_at']);
        });

        Schema::create('it_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel')->default('system');
            $table->string('event');
            $table->string('recipient')->nullable();
            $table->text('message');
            $table->string('status')->default('registered');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'event']);
        });

        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained('it_tickets')->nullOnDelete();
            $table->string('recipient');
            $table->text('message');
            $table->string('status')->default('pending');
            $table->string('api_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['recipient', 'status']);
        });

        $now = now();
        $calendarId = DB::table('it_sla_calendars')->insertGetId([
            'name' => 'Horario comercial padrao',
            'weekdays' => json_encode([1, 2, 3, 4, 5]),
            'starts_at' => '08:00:00',
            'ends_at' => '17:00:00',
            'count_weekends' => false,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['Urgente', 'urgente', 30, 0, 240, 40],
            ['Alta', 'alta', 60, 0, 480, 30],
            ['Media', 'media', 240, 0, 1440, 20],
            ['Baixa', 'baixa', 480, 0, 4320, 10],
        ] as [$name, $priority, $firstResponse, $start, $resolution, $priorityOrder]) {
            DB::table('it_sla_rules')->insert([
                'calendar_id' => $calendarId,
                'name' => $name,
                'priority' => $priority,
                'first_response_minutes' => $firstResponse,
                'start_minutes' => $start,
                'resolution_minutes' => $resolution,
                'rule_priority' => $priorityOrder,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('it_sla_rules')->insert([
            'calendar_id' => $calendarId,
            'name' => 'Fallback padrao',
            'first_response_minutes' => 240,
            'start_minutes' => 0,
            'resolution_minutes' => 1440,
            'rule_priority' => -100,
            'is_fallback' => true,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('it_sla_rules')
            ->whereNotNull('priority')
            ->get(['id', 'priority'])
            ->each(fn ($rule) => DB::table('it_tickets')
                ->where('priority', $rule->priority)
                ->whereNull('sla_rule_id')
                ->update(['sla_rule_id' => $rule->id]));
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
        Schema::dropIfExists('it_notification_logs');
        Schema::dropIfExists('it_ticket_evaluations');
        Schema::dropIfExists('it_ticket_asset');

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropIndex(['sla_rule_id', 'status']);
            $table->dropIndex(['primary_asset_id', 'status']);
            $table->dropConstrainedForeignId('primary_asset_id');
            $table->dropConstrainedForeignId('sla_rule_id');
            $table->dropColumn(['first_response_due_at', 'started_at', 'start_due_at']);
        });

        Schema::dropIfExists('it_sla_rules');
        Schema::dropIfExists('it_sla_calendars');
    }
};
