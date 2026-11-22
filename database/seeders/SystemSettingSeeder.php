<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\It\WhatsappVerifyToken;
use Illuminate\Database\Seeder;
use RuntimeException;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if (filled($verifyToken) && ! WhatsappVerifyToken::isSecure($verifyToken)) {
            throw new RuntimeException('WHATSAPP_BUSINESS_VERIFY_TOKEN must be a strong, non-default value.');
        }

        collect([
            ['group' => 'institucional', 'key' => 'institution_name', 'label' => 'Nome da instituicao', 'type' => 'text', 'value' => 'Secretaria de Estado da Assistencia Social'],
            ['group' => 'institucional', 'key' => 'system_name', 'label' => 'Nome do sistema', 'type' => 'text', 'value' => 'SIGES'],
            ['group' => 'institucional', 'key' => 'support_email', 'label' => 'Email de suporte', 'type' => 'text', 'value' => 'suporte@siges.local'],
            ['group' => 'notificacoes', 'key' => 'low_stock_enabled', 'label' => 'Alerta de estoque minimo', 'type' => 'boolean', 'value' => true],
            ['group' => 'notificacoes', 'key' => 'vehicle_license_days', 'label' => 'Dias para alertar licenciamento', 'type' => 'number', 'value' => 30],
            ['group' => 'notificacoes', 'key' => 'it_notifications_assignment', 'label' => 'Notificar atribuicoes de chamados', 'type' => 'boolean', 'value' => true],
            ['group' => 'notificacoes', 'key' => 'it_notifications_responses', 'label' => 'Notificar respostas e mudancas de status', 'type' => 'boolean', 'value' => true],
            ['group' => 'notificacoes', 'key' => 'it_notifications_sla', 'label' => 'Notificar risco e vencimento de SLA', 'type' => 'boolean', 'value' => true],
            ['group' => 'geral', 'key' => 'it_module_enabled', 'label' => 'Modulo Help Desk habilitado', 'type' => 'boolean', 'value' => true, 'description' => 'Controla a disponibilidade operacional do modulo de informatica.'],
            ['group' => 'geral', 'key' => 'it_default_department_scope', 'label' => 'Escopo padrao por setor', 'type' => 'boolean', 'value' => true, 'description' => 'Mantem o isolamento dos chamados por setor para perfis sem acesso global.'],
            ['group' => 'geral', 'key' => 'it_data_retention_days', 'label' => 'Retencao de chamados (dias)', 'type' => 'number', 'value' => 0, 'description' => 'Use zero para nao aplicar limpeza automatica.'],
            ['group' => 'sla', 'key' => 'it_sla_default_first_response_minutes', 'label' => 'Primeira resposta padrao (minutos)', 'type' => 'number', 'value' => 240],
            ['group' => 'sla', 'key' => 'it_sla_default_resolution_minutes', 'label' => 'Resolucao padrao (minutos)', 'type' => 'number', 'value' => 1440],
            ['group' => 'sla', 'key' => 'it_sla_alert_lead_minutes', 'label' => 'Alerta de SLA em risco (minutos)', 'type' => 'number', 'value' => 60],
            ['group' => 'sla', 'key' => 'it_sla_business_hours_enabled', 'label' => 'Considerar horario de atendimento', 'type' => 'boolean', 'value' => true],
            ['group' => 'chamados', 'key' => 'it_ticket_default_priority', 'label' => 'Prioridade padrao', 'type' => 'text', 'value' => 'media'],
            ['group' => 'chamados', 'key' => 'it_ticket_auto_assign', 'label' => 'Atribuicao automatica habilitada', 'type' => 'boolean', 'value' => true],
            ['group' => 'chamados', 'key' => 'it_ticket_require_resolution_summary', 'label' => 'Exigir resumo na resolucao', 'type' => 'boolean', 'value' => true],
            ['group' => 'chamados', 'key' => 'it_ticket_allow_requester_reopen', 'label' => 'Permitir reabertura pelo solicitante', 'type' => 'boolean', 'value' => true],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_enabled', 'label' => 'WhatsApp Business habilitado', 'type' => 'boolean', 'value' => false],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_phone', 'label' => 'Numero WhatsApp corporativo', 'type' => 'text', 'value' => ''],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_webhook_url', 'label' => 'URL publica do webhook WhatsApp', 'type' => 'text', 'value' => ''],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_phone_number_id', 'label' => 'Phone Number ID WhatsApp', 'type' => 'text', 'value' => ''],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_verify_token', 'label' => 'Verify Token do webhook', 'type' => 'password', 'value' => is_string($verifyToken) ? $verifyToken : '', 'is_secret' => true],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_token', 'label' => 'Token WhatsApp Business', 'type' => 'password', 'value' => '', 'is_secret' => true],
            ['group' => 'whatsapp', 'key' => 'whatsapp_business_api_version', 'label' => 'Versao da API WhatsApp', 'type' => 'text', 'value' => 'v20.0'],
            ['group' => 'automacoes', 'key' => 'it_automation_enabled', 'label' => 'Motor de automacoes habilitado', 'type' => 'boolean', 'value' => true],
            ['group' => 'automacoes', 'key' => 'it_automation_preventive_enabled', 'label' => 'Geracao automatica preventiva', 'type' => 'boolean', 'value' => true],
            ['group' => 'automacoes', 'key' => 'it_automation_user_reminder_hours', 'label' => 'Lembrete aguardando usuario (horas)', 'type' => 'number', 'value' => 24],
            ['group' => 'avaliacao', 'key' => 'it_evaluation_enabled', 'label' => 'Avaliacoes habilitadas', 'type' => 'boolean', 'value' => true],
            ['group' => 'avaliacao', 'key' => 'it_evaluation_required_on_close', 'label' => 'Solicitar avaliacao no encerramento', 'type' => 'boolean', 'value' => false],
            ['group' => 'avaliacao', 'key' => 'it_evaluation_scale', 'label' => 'Escala de avaliacao', 'type' => 'number', 'value' => 5],
            ['group' => 'integracoes', 'key' => 'govbr_enabled', 'label' => 'Login Gov.br habilitado', 'type' => 'boolean', 'value' => false],
            ['group' => 'integracoes', 'key' => 'ldap_enabled', 'label' => 'Active Directory/LDAP habilitado', 'type' => 'boolean', 'value' => false],
            ['group' => 'integracoes', 'key' => 'ldap_host', 'label' => 'Servidor LDAP', 'type' => 'text', 'value' => ''],
            ['group' => 'integracoes', 'key' => 'powerbi_workspace', 'label' => 'Workspace Power BI', 'type' => 'text', 'value' => ''],
            ['group' => 'seguranca', 'key' => 'session_timeout_minutes', 'label' => 'Tempo de sessao em minutos', 'type' => 'number', 'value' => 120],
            ['group' => 'seguranca', 'key' => 'password_min_length', 'label' => 'Tamanho minimo de senha', 'type' => 'number', 'value' => 8],
        ])->each(function (array $setting): void {
            $model = SystemSetting::query()->firstOrNew(['key' => $setting['key']]);

            $model->fill([
                'group' => $setting['group'],
                'label' => $setting['label'],
                'type' => $setting['type'],
                'is_secret' => $setting['is_secret'] ?? false,
                'description' => $setting['description'] ?? null,
            ]);

            if (! $model->exists) {
                if ($model->is_secret) {
                    $model->setSecretValue($setting['value']);
                } else {
                    $model->value = ['value' => $setting['value']];
                }
            }

            $model->save();
        });
    }
}
