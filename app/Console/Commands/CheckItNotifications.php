<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckItNotifications extends Command
{
    protected $signature = 'it:notifications-health {--production : Exige SMTP real e configuração de produção}';

    protected $description = 'Verifica SMTP, fila e infraestrutura das notificações do Help Desk';

    public function handle(): int
    {
        $checks = [
            'mail_mailer' => (string) config('mail.default'),
            'smtp_host' => (string) config('mail.mailers.smtp.host'),
            'from_address' => (string) config('mail.from.address'),
            'queue_connection' => (string) config('queue.default'),
            'notification_queue' => (string) config('notifications.queue'),
            'jobs_table' => Schema::hasTable(config('queue.connections.database.table', 'jobs')) ? 'ok' : 'ausente',
        ];

        foreach ($checks as $name => $value) {
            $this->line(sprintf('%-20s %s', $name, $value ?: '(não configurado)'));
        }

        $errors = [];
        if ($this->option('production') && $checks['mail_mailer'] !== 'smtp') {
            $errors[] = 'MAIL_MAILER deve ser smtp em produção.';
        }
        if ($this->option('production') && in_array('', [$checks['smtp_host'], $checks['from_address']], true)) {
            $errors[] = 'SMTP_HOST e MAIL_FROM_ADDRESS devem estar preenchidos.';
        }
        if ($checks['jobs_table'] !== 'ok') {
            $errors[] = 'A tabela de jobs não existe; execute as migrations.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $this->info($this->option('production') ? 'Infraestrutura de produção pronta para o worker.' : 'Infraestrutura local detectada; use --production para validação estrita.');
        return self::SUCCESS;
    }
}
