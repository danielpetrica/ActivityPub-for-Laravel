<?php

namespace App\Filament\Resources\Links\Pages;

use App\Enums\LinkPosition;
use App\Filament\Resources\Links\LinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLinks extends ListRecords
{
    protected static string $resource = LinkResource::class;

    protected ?string $subheading = 'Manage navigation links for the site\'s header and footer areas. Supports customizable ordering, position assignment, and external link indicators.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Links'),
            'header' => Tab::make('Header')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('position', LinkPosition::Header)),
            'footer' => Tab::make('Footer')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('position', LinkPosition::Footer)),
        ];
    }
}
