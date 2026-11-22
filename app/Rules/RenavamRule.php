<?php

namespace App\Rules;

use App\Support\Renavam;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RenavamRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Renavam::isValid($value)) {
            $fail('O campo :attribute deve conter um RENAVAM válido com 11 dígitos.');
        }
    }
}
