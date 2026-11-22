<?php

namespace App\Rules;

use App\Support\BrazilianVehiclePlate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BrazilianVehiclePlateRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! BrazilianVehiclePlate::isValid($value)) {
            $fail('O campo :attribute deve usar o formato ABC-1234 ou ABC1D23.');
        }
    }
}
