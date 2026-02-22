<?php

namespace App\Filament\Resources\NewsletterForms;

use App\Filament\Resources\NewsletterForms\Pages\CreateNewsletterForm;
use App\Filament\Resources\NewsletterForms\Pages\EditNewsletterForm;
use App\Filament\Resources\NewsletterForms\Pages\ListNewsletterForms;
use App\Filament\Resources\NewsletterForms\Schemas\NewsletterFormSchema;
use App\Filament\Resources\NewsletterForms\Tables\NewsletterFormsTable;
use App\Models\NewsletterForm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NewsletterFormResource extends Resource
{
    protected static ?string $model = NewsletterForm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    public static function form(Schema $schema): Schema
    {
        return NewsletterFormSchema::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterFormsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterForms::route('/'),
            'create' => CreateNewsletterForm::route('/create'),
            'edit' => EditNewsletterForm::route('/{record}/edit'),
        ];
    }
}
