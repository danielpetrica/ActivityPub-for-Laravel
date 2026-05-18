<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected ?string $subheading = 'Create site-wide or tag-specific announcement banners displayed at the top of the site. Cross-site announcements appear everywhere, while tag-scoped ones show only on matching tag pages.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
