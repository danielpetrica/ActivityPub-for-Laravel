<?php

namespace App\Filament\Resources\Cities\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('City Information')
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
                                TextInput::make('province')
                                    ->required(),
                                TextInput::make('region')
                                    ->required(),
                            ]),
                        Toggle::make('is_capital')
                            ->label('Is Province Capital?')
                            ->required()
                            ->default(false),
                        RichEditor::make('description')
                            ->columnSpanFull(),
                    ]),

                Section::make('Geographic Data')
                    ->components([
                        Grid::make(2)
                            ->components([
                                TextInput::make('latitude')
                                    ->numeric(),
                                TextInput::make('longitude')
                                    ->numeric(),
                            ]),
                    ]),
            ]);
    }
}
