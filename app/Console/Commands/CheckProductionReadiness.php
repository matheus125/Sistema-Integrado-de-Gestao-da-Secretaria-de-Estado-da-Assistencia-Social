<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\It\WhatsappVerifyToken;
use App\Services\Settings\SettingValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CheckProductionReadiness extends Command
{
    protected $signature = 'siges:production-check';

    protected $description = 'Check production configuration without changing records';

    public function handle(): int
    {
        $checks = [
            'APP_ENV=production' => app()->environment('production'),
            'APP_DEBUG=false' => ! config('app.debug'),
            'APP_KEY configured' => filled(config('app.key')),
            'HTTPS application URL' => str_starts_with(config('app.url'), 'https://'),
            'MySQL default connection' => config('database.default') === 'mysql',
            'Secure session cookie' => (bool) config('session.secure'),
            'Database queues' => config('queue.default') === 'database',
            'Job reservation exceeds worker timeout' => config('queue.connections.database.retry_after') > max(120, (int) config('notifications.timeout')),
            'Frontend production build exists' => is_file(base_path('frontend/quasar/dist/spa/index.html')),
            'Storage writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
            'Production mail transport' => ! in_array(config('mail.default'), ['log', 'array'], true),
        ];
        try {
            DB::select('SELECT 1');
            $checks['Database reachable'] = true;
            $checks['Dedicated database user'] = ! in_array(DB::connection()->getConfig('username'), ['root', 'dev'], true);
            $checks['No seeded default passwords'] = ! User::query()->whereIn('email', ['admin@siges.local', 'tecnico@siges.local'])->get()
                ->contains(fn (User $user) => Hash::check('password', $user->password));
            $checks['Strong WhatsApp webhook verify token'] = WhatsappVerifyToken::isSecure(
                SettingValue::get('whatsapp_business_verify_token'),
            );
        } catch (\Throwable) {
            $checks['Database reachable'] = false;
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'OK   ' : 'FAIL ').$label);
        }
        $this->comment('Also verify DNS/TLS, services, off-server backups and integrations on the target host.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
