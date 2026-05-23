<?php

namespace App\Models;

use Database\Factories\NewsletterFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $title
 * @property string|null $description
 * @property string $button_text
 * @property string $success_message
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NewsletterForm extends Model
{
    /** @use HasFactory<NewsletterFormFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
