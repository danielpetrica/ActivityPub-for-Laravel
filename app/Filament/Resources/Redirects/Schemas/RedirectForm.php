<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
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

                        Select::make('status_code')
                            ->options([
                                301 => '301 Permanent',
                                302 => '302 Temporary',
                            ])
                            ->default(302)
                            ->required(),

                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true),
                    ]),
            ]);
    }
}
