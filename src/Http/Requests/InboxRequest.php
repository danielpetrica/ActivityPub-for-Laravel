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
            'type' => ['required', 'string', 'min:1'],
            'actor' => ['required', 'string', 'url'],
            // `object` and `@context` are optional: several legitimate
            // activity shapes omit them (e.g. object-less activities), and
            // rejecting them here would drop valid federated traffic. The
            // 1 MB payload cap is still enforced in the controller.
            'object' => ['nullable'],
            '@context' => ['nullable'],
            'id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Activity type is required.',
            'actor.required' => 'Activity actor is required.',
            'actor.url' => 'Activity actor must be a valid URL.',
            'type.min' => 'Activity type must not be empty.',
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
