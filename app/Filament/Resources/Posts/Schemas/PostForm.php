<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Enums\PostStatus;
use App\Filament\Plugins\MediaBlocksRichContentPlugin;
use App\Models\Tag;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post Details')
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
                            ->plugins([
                                MediaBlocksRichContentPlugin::make(),
                            ])
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
                            ->directory('posts'),
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
                        DateTimePicker::make('published_at'),
                        Select::make('tags')
                            ->multiple()
                            ->relationship(name: 'tags', titleAttribute: 'name')
                            ->preload()
                            ->searchable()
                            ->live(),
                        Select::make('primary_tag_id')
                            ->label('Primary Tag')
                            ->options(fn (Get $get): array => Tag::query()
                                ->whereIn('id', $get('tags') ?? [])
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->hint('Select which of the assigned tags is the primary one.'),
                    ])->columns(2),

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
