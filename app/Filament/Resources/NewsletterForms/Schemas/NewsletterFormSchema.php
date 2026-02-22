<?php

namespace App\Filament\Resources\NewsletterForms\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class NewsletterFormSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ])->columns(2),

                Section::make('Content')
                    ->components([
                        TextInput::make('title'),
                        Textarea::make('description')
                            ->rows(3),
                        TextInput::make('button_text')
                            ->default('Subscribe')
                            ->required(),
                        TextInput::make('success_message')
                            ->default('Subscribed successfully!')
                            ->required(),
                    ]),
            ]);
    }
}
