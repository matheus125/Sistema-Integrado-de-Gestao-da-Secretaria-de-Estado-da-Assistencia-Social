<?php

namespace App\Rules;

use App\Support\BrazilianPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class BrazilianPhoneRule implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! BrazilianPhone::isValid($value)) {
            $fail('O campo :attribute deve conter um telefone brasileiro válido com DDD.');
        }
    }
}
