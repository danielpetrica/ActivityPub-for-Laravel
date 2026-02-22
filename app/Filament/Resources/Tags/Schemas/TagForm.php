<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tag Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        RichEditor::make('description')
                            ->columnSpanFull(),
                    ])->columns(2)
                    ->columnSpanFull(),

                Section::make('Media')
                    ->components([
                        FileUpload::make('image_path')
                            ->label('Tag Image')
                            ->image()
                            ->directory('tags'),
                    ]),

                Section::make('SEO')
                    ->components([
                        TextInput::make('meta_title')
                            ->label('SEO Title'),
                        TextInput::make('meta_description')
                            ->label('SEO Description'),
                        TextInput::make('og_title')
                            ->label('OG Title'),
                        TextInput::make('og_description')
                            ->label('OG Description'),
                        TextInput::make('og_image')
                            ->label('OG Image URL'),
                        TextInput::make('twitter_title')
                            ->label('Twitter Title'),
                        TextInput::make('twitter_description')
                            ->label('Twitter Description'),
                        TextInput::make('twitter_image')
                            ->label('Twitter Image URL'),
                        TextInput::make('canonical_url')
                            ->label('Canonical URL'),
                    ]),
            ]);
    }
}
