<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Redirect Details')
                    ->description('Specify the path and destination URL for the redirect.')
                    ->schema([
                        TextInput::make('path')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('/my-custom-path')
                            ->helperText('The relative path on your website (e.g., /promo).'),

                        TextInput::make('destination_url')
                            ->label('Destination URL')
                            ->required()
                            ->url()
                            ->placeholder('https://example.com/target')
                            ->helperText('The full URL where the user will be redirected.'),

                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true),
                    ]),
            ]);
    }
}
