<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCommentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'post_id' => ['required', 'exists:posts,id'],
            'author_name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
