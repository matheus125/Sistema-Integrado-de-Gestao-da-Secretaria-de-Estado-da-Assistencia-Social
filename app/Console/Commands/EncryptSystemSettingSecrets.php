<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EncryptSystemSettingSecrets extends Command
{
    protected $signature = 'siges:encrypt-secrets
        {--apply : Gravar a conversao apos validar o backup}
        {--backup= : Caminho absoluto de um backup existente, legivel e nao vazio}
        {--rotate : Recriptografar valores protegidos usando a APP_KEY atual}';

    protected $description = 'Audita e migra segredos legados de configuracao sem exibir seus valores';

    public function handle(): int
    {
        $secrets = SystemSetting::query()->where('is_secret', true)->get();
        $legacyCount = $secrets->filter->hasLegacySecret()->count();
        $encryptedCount = $secrets->filter->hasEncryptedSecret()->count();
        $emptyCount = $secrets->count() - $legacyCount - $encryptedCount;

        foreach ($secrets->filter->hasEncryptedSecret() as $setting) {
            $setting->resolvedValue();
        }

        $this->line("Segredos legados para migrar: {$legacyCount}");
        $this->line("Segredos criptografados verificados: {$encryptedCount}");
        $this->line("Segredos vazios: {$emptyCount}");

        if (! $this->option('apply')) {
            $this->comment('Simulacao concluida. Nenhum registro foi alterado.');

            return self::SUCCESS;
        }

        if (! $this->validBackup($this->option('backup'))) {
            $this->error('Conversao recusada: informe --backup com um arquivo existente, legivel e nao vazio.');

            return self::FAILURE;
        }

        $rotate = (bool) $this->option('rotate');
        $processed = 0;

        DB::transaction(function () use ($rotate, &$processed): void {
            $settings = SystemSetting::query()
                ->where('is_secret', true)
                ->lockForUpdate()
                ->get();

            foreach ($settings as $setting) {
                if (! $setting->hasLegacySecret() && ! ($rotate && $setting->hasEncryptedSecret())) {
                    continue;
                }

                $originalValue = $setting->resolvedValue();
                $setting->setSecretValue($originalValue)->save();
                $setting->refresh();

                if ($setting->getRawOriginal('value') !== null
                    || ! $setting->hasEncryptedSecret()
                    || $setting->resolvedValue() !== $originalValue) {
                    throw new RuntimeException("Secret verification failed for setting [{$setting->key}].");
                }

                $processed++;
            }
        });

        $this->info("{$processed} segredo(s) criptografado(s) e verificado(s).");
        $this->comment('Preserve a APP_KEY e o backup. Em rotacao, mantenha a chave anterior em APP_PREVIOUS_KEYS ate concluir a verificacao.');

        return self::SUCCESS;
    }

    private function validBackup(mixed $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        $realPath = realpath($path);

        return $realPath !== false
            && is_file($realPath)
            && is_readable($realPath)
            && filesize($realPath) > 0;
    }
}
