<?php

namespace DanielPetrica\LaravelActivityPub\Http\Requests;

use DanielPetrica\LaravelActivityPub\Helpers\InboxLogger;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class InboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'actor' => ['required', 'string', 'url'],
            'object' => ['required'],
            '@context' => ['present'],
            'id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Activity type is required.',
            'actor.required' => 'Activity actor is required.',
            'actor.url' => 'Activity actor must be a valid URL.',
            'object.required' => 'Activity object is required.',
            '@context.present' => 'Activity @context must be present.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->first();
        InboxLogger::logRejected(
            request: $this,
            statusCode: 422,
            reason: 'Validation failed: '.$errors,
        );

        throw ValidationException::withMessages($validator->errors()->toArray());
    }
}
