<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('text')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                Toggle::make('is_cross_site')
                    ->label('Cross-site announcement')
                    ->default(true)
                    ->reactive()
                    ->required(),
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->hidden(fn (Get $get): bool => $get('is_cross_site'))
                    ->required(fn (Get $get): bool => ! $get('is_cross_site')),
            ]);
    }
}
