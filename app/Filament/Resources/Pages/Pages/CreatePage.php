<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Actions\GeneratesSeoMetadata;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use GeneratesSeoMetadata;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateSeoAction(),
        ];
    }
}
