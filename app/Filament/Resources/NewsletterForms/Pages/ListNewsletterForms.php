<?php

namespace App\Filament\Resources\NewsletterForms\Pages;

use App\Filament\Resources\NewsletterForms\NewsletterFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListNewsletterForms extends ListRecords
{
    protected static string $resource = NewsletterFormResource::class;

    protected ?string $subheading = 'Create and manage embeddable newsletter subscription forms placed on pages via shortcodes. Each form has customizable title, description, button text, and success message.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
