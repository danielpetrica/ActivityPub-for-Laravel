<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Actions\GeneratesSeoMetadata;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use GeneratesSeoMetadata;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateSeoAction(),
            DeleteAction::make(),
        ];
    }
}
