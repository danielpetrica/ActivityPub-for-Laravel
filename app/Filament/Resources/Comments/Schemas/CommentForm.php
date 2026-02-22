<?php

namespace App\Filament\Resources\Comments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CommentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('post_id')
                    ->relationship(name: 'post', titleAttribute: 'title')
                    ->required()
                    ->searchable(),
                Select::make('user_id')
                    ->relationship(name: 'user', titleAttribute: 'name')
                    ->searchable(),
                TextInput::make('author_name')
                    ->placeholder('Guest Name'),
                Textarea::make('comment')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_approved')
                    ->label('Approved for Display')
                    ->default(false),
            ]);
    }
}
