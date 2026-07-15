<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columnSpanFull()
                    ->components([
                        Grid::make(2)
                            ->components([
                                TextInput::make('name')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                        RichEditor::make('intro_content')
                            ->helperText('Placeholders: {city_name}, {city_name_slug}, {city_description}, {same_province_list}, {same_region_big_cities}')
                            ->columnSpanFull(),
                        RichEditor::make('main_content')
                            ->helperText('Placeholders: {city_name}, {city_name_slug}, {city_description}, {same_province_list}, {same_region_big_cities}')
                            ->columnSpanFull(),
                    ]),

                Section::make('Relationships')
                    ->components([
                        Select::make('caseStudies')
                            ->multiple()
                            ->relationship('caseStudies', 'title')
                            ->preload(),
                    ]),

                Section::make('Status & SEO')
                    ->components([
                        Toggle::make('is_active')
                            ->required()
                            ->default(true),
                        Grid::make(2)
                            ->components([
                                TextInput::make('seo_metadata.title')
                                    ->label('SEO Title'),
                                TextInput::make('seo_metadata.description')
                                    ->label('SEO Description'),
                            ]),
                    ]),
            ]);
    }
}
