<?php

namespace App\Filament\Resources\Links\Schemas;

use App\Enums\LinkPosition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('position')
                    ->options(LinkPosition::class)
                    ->required()
                    ->native(false)
                    ->default(LinkPosition::Footer),
                TextInput::make('label')
                    ->required(),
                TextInput::make('url')
                    ->url()
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_external')
                    ->required(),
            ]);
    }
}
