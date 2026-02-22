<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Classes\Business\RedirectBusiness;
use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshCache')
                ->label('Refresh Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    RedirectBusiness::refreshCache();

                    Notification::make()
                        ->title('Redirects cache refreshed successfully!')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
