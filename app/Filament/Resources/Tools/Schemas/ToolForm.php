<?php

namespace App\Filament\Resources\Tools\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ToolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tool Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Textarea::make('html_content')
                            ->required()
                            ->rows(10)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('SEO')
                    ->components([
                        TextInput::make('seo_metadata.title')
                            ->label('SEO Title'),
                        TextInput::make('seo_metadata.description')
                            ->label('SEO Description'),
                        TextInput::make('seo_metadata.keywords')
                            ->label('SEO Keywords'),
                    ]),
            ]);
    }
}
