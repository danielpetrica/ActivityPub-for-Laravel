<?php

namespace App\Filament\Resources\Tools;

use App\Filament\Resources\Tools\Pages\CreateTool;
use App\Filament\Resources\Tools\Pages\EditTool;
use App\Filament\Resources\Tools\Pages\ListTools;
use App\Filament\Resources\Tools\Schemas\ToolForm;
use App\Filament\Resources\Tools\Tables\ToolsTable;
use App\Models\Tool;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return 'Tools';
    }

    public static function form(Schema $schema): Schema
    {
        return ToolForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToolsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTools::route('/'),
            'create' => CreateTool::route('/create'),
            'edit' => EditTool::route('/{record}/edit'),
        ];
    }
}
