<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_root_causes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->foreignId('root_cause_id')->nullable()->after('primary_asset_id')->constrained('it_root_causes')->nullOnDelete();
            $table->boolean('requires_follow_up')->default(false)->after('resolution_summary');
            $table->boolean('recurring_problem')->default(false)->after('requires_follow_up');
            $table->string('identified_cause')->nullable()->after('recurring_problem');
            $table->text('root_cause')->nullable()->after('identified_cause');
            $table->text('applied_solution')->nullable()->after('root_cause');
            $table->text('executed_procedure')->nullable()->after('applied_solution');
            $table->string('replaced_part')->nullable()->after('executed_procedure');
            $table->text('internal_technical_note')->nullable()->after('replaced_part');
            $table->boolean('sla_overdue')->default(false)->after('sla_due_at');
            $table->foreignId('support_team_id')->nullable()->after('assigned_to');
            $table->boolean('is_major_incident')->default(false)->after('source');
            $table->foreignId('major_incident_id')->nullable()->after('is_major_incident')->constrained('it_tickets')->nullOnDelete();
            $table->timestamp('last_requester_reply_at')->nullable()->after('closed_at');
        });

        Schema::create('it_knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('it_ticket_categories')->nullOnDelete();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->longText('content');
            $table->json('tags')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('public')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['active', 'public']);
        });

        Schema::create('it_escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('remaining_percent')->nullable();
            $table->unsignedInteger('minutes_after_due')->nullable();
            $table->string('level')->default('n1');
            $table->boolean('notify_technician')->default(true);
            $table->boolean('notify_manager')->default(false);
            $table->boolean('mark_overdue')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('it_ticket_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('it_escalation_rules')->nullOnDelete();
            $table->string('level')->default('n1');
            $table->string('status')->default('registered');
            $table->timestamp('escalated_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'rule_id']);
        });

        Schema::create('it_support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('max_active_tickets')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('it_support_team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('it_support_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('specialties')->nullable();
            $table->boolean('available')->default(true);
            $table->timestamp('unavailable_until')->nullable();
            $table->unsignedSmallInteger('max_active_tickets')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('it_category_support_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('it_ticket_categories')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('it_support_teams')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['category_id', 'team_id']);
        });

        Schema::create('it_ticket_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ticket_id', 'user_id']);
        });

        Schema::table('it_ticket_messages', function (Blueprint $table) {
            $table->string('visibility')->default('public')->after('direction');
            $table->index(['ticket_id', 'visibility']);
        });

        Schema::create('it_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('it_ticket_categories')->nullOnDelete();
            $table->string('type', 40)->nullable();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('it_checklist_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('it_checklist_templates')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('it_ticket_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('it_checklist_template_items')->nullOnDelete();
            $table->string('label');
            $table->boolean('required')->default(false);
            $table->string('status')->default('pendente');
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::create('it_ticket_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('pendente');
            $table->string('priority')->default('media');
            $table->boolean('required')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
        });

        Schema::create('it_ticket_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->foreignId('related_ticket_id')->constrained('it_tickets')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'related_ticket_id', 'type']);
        });

        foreach ([
            'falha de hardware',
            'falha de software',
            'erro de configuracao',
            'usuario',
            'rede',
            'energia',
            'desgaste',
            'atualizacao',
            'permissao',
            'fornecedor externo',
            'causa desconhecida',
        ] as $cause) {
            DB::table('it_root_causes')->insert([
                'name' => ucfirst($cause),
                'slug' => str($cause)->slug()->toString(),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Suporte N1', 'Suporte N2', 'Infraestrutura', 'Redes', 'Sistemas', 'Seguranca', 'Telefonia'] as $team) {
            DB::table('it_support_teams')->insert([
                'name' => $team,
                'slug' => str($team)->slug()->toString(),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ([
            ['25% do SLA restante', 25, null, 'n1', true, false, false],
            ['10% do SLA restante', 10, null, 'n2', true, true, false],
            ['SLA vencido', null, 0, 'n2', true, true, true],
            ['Vencido ha 4 horas', null, 240, 'n3', true, true, true],
        ] as [$name, $remaining, $afterDue, $level, $notifyTech, $notifyManager, $markOverdue]) {
            DB::table('it_escalation_rules')->insert([
                'name' => $name,
                'remaining_percent' => $remaining,
                'minutes_after_due' => $afterDue,
                'level' => $level,
                'notify_technician' => $notifyTech,
                'notify_manager' => $notifyManager,
                'mark_overdue' => $markOverdue,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('it_ticket_relationships');
        Schema::dropIfExists('it_ticket_tasks');
        Schema::dropIfExists('it_ticket_checklist_items');
        Schema::dropIfExists('it_checklist_template_items');
        Schema::dropIfExists('it_checklist_templates');
        Schema::table('it_ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'visibility']);
            $table->dropColumn('visibility');
        });
        Schema::dropIfExists('it_ticket_followers');
        Schema::dropIfExists('it_category_support_team');
        Schema::dropIfExists('it_support_team_user');
        Schema::dropIfExists('it_support_teams');
        Schema::dropIfExists('it_ticket_escalations');
        Schema::dropIfExists('it_escalation_rules');
        Schema::dropIfExists('it_knowledge_articles');

        Schema::table('it_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('root_cause_id');
            $table->dropConstrainedForeignId('major_incident_id');
            $table->dropColumn([
                'requires_follow_up',
                'recurring_problem',
                'identified_cause',
                'root_cause',
                'applied_solution',
                'executed_procedure',
                'replaced_part',
                'internal_technical_note',
                'sla_overdue',
                'support_team_id',
                'is_major_incident',
                'last_requester_reply_at',
            ]);
        });

        Schema::dropIfExists('it_root_causes');
    }
};
