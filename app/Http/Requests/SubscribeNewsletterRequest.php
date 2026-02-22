<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubscribeNewsletterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc'],
            'slug' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please provide your email address.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
