<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page Details')
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        RichEditor::make('content')
                            ->required()
                            ->saveRelationshipsUsing(null)
                            ->columnSpanFull(),
                        RichEditor::make('excerpt')
                            ->columnSpanFull(),
                    ])->columns(2)
                    ->columnSpanFull(),

                Section::make('Featured Image')
                    ->components([
                        FileUpload::make('feature_image_path')
                            ->label('Feature Image')
                            ->image()
                            ->directory('pages'),
                        TextInput::make('feature_image_alt')
                            ->label('Feature Image Alt Text'),
                        TextInput::make('feature_image_caption')
                            ->label('Feature Image Caption'),
                    ])->columns(2),

                Section::make('Settings')
                    ->components([
                        Select::make('status')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft->value)
                            ->required(),
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

                Section::make('Code Injection')
                    ->components([
                        RichEditor::make('codeinjection_head')
                            ->label('Header Injection'),
                        RichEditor::make('codeinjection_foot')
                            ->label('Footer Injection'),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }
}
