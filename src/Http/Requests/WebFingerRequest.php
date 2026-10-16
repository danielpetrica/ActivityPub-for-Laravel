<?php

namespace DanielPetrica\LaravelActivityPub\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WebFingerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resource' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'resource.required' => 'The "resource" parameter is required.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $resource = $this->query('resource');

            if ($resource === null) {
                return;
            }

            if (! is_string($resource)) {
                $validator->errors()->add('resource', 'Invalid resource format.');

                return;
            }

            $isAcct = preg_match('/^acct:[^@ ]+@[^@ ]+$/', $resource) === 1;
            $isUrl = preg_match('#^https?://\S+$#i', $resource) === 1;
            $isAccount = preg_match('/^[^@ ]+@[^@ ]+$/', $resource) === 1;

            if (! $isAcct && ! $isUrl && ! $isAccount) {
                $validator->errors()->add(
                    'resource',
                    'Unsupported resource format. Expected acct:user@domain, https://... or user@domain.',
                );
            }
        });
    }
}
