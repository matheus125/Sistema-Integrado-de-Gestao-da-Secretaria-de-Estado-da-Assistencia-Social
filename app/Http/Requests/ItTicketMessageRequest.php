<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItTicketMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('informatica.visualizar') === true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
            'direction' => ['nullable', Rule::in(['internal', 'outbound', 'inbound'])],
            'visibility' => ['nullable', Rule::in(['public', 'internal'])],
        ];
    }
}
