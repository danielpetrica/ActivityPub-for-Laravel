<?php

namespace App\Filament\Resources\CaseStudies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CaseStudyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->components([
                        Grid::make(2)
                            ->components([
                                TextInput::make('title')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->unique(ignoreRecord: true),
                            ]),
                        RichEditor::make('description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Media & Relationships')
                    ->components([
                        FileUpload::make('images')
                            ->multiple()
                            ->image()
                            ->directory('case-studies')
                            ->columnSpanFull(),
                        Select::make('services')
                            ->multiple()
                            ->relationship('services', 'name')
                            ->preload(),
                    ]),

                Section::make('Status')
                    ->components([
                        Toggle::make('is_active')
                            ->required()
                            ->default(true),
                    ]),
            ]);
    }
}
