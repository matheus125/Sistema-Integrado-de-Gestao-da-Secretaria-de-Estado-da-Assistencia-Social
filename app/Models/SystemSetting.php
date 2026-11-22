<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'label',
        'type',
        'value',
        'encrypted_value',
        'is_secret',
        'secret_encrypted_at',
        'description',
    ];

    protected $hidden = [
        'encrypted_value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_secret' => 'boolean',
            'secret_encrypted_at' => 'datetime',
        ];
    }

    public function resolvedValue(mixed $default = null): mixed
    {
        if ($this->is_secret && filled($this->encrypted_value)) {
            $payload = Crypt::decrypt($this->encrypted_value);

            if (! is_array($payload) || ! array_key_exists('value', $payload)) {
                throw new RuntimeException("Invalid encrypted setting payload for [{$this->key}].");
            }

            return $payload['value'];
        }

        return $this->value['value'] ?? $default;
    }

    public function setSecretValue(mixed $value): self
    {
        if (! $this->is_secret) {
            throw new RuntimeException("Setting [{$this->key}] is not marked as secret.");
        }

        $this->forceFill([
            'value' => null,
            'encrypted_value' => Crypt::encrypt(['value' => $value]),
            'secret_encrypted_at' => now(),
        ]);

        return $this;
    }

    public function hasEncryptedSecret(): bool
    {
        return $this->is_secret && filled($this->encrypted_value);
    }

    public function hasLegacySecret(): bool
    {
        return $this->is_secret
            && ! $this->hasEncryptedSecret()
            && filled($this->value['value'] ?? null);
    }
}
