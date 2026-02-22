<?php

namespace App\Filament\Resources\NewsletterForms\Pages;

use App\Filament\Resources\NewsletterForms\NewsletterFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListNewsletterForms extends ListRecords
{
    protected static string $resource = NewsletterFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
