<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Models\City;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Service Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        IconEntry::make('is_active')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
                Section::make('Content Preview')
                    ->description('Shown with placeholders replaced using the first available city.')
                    ->schema([
                        TextEntry::make('rendered_intro')
                            ->label('Intro Content')
                            ->html()
                            ->hidden(fn () => empty($this->getRenderedContent()['intro'])),
                        TextEntry::make('rendered_main')
                            ->label('Main Content')
                            ->html()
                            ->hidden(fn () => empty($this->getRenderedContent()['main'])),
                    ]),
                Section::make('Case Studies')
                    ->schema([
                        TextEntry::make('caseStudies')
                            ->label('')
                            ->html()
                            ->state(fn () => $this->renderCaseStudies()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function getRenderedContent(): array
    {
        $city = City::query()->first();

        if ($city === null) {
            return ['intro' => '', 'main' => ''];
        }

        $sameProvinceCities = City::query()
            ->where('province', '=', $city->province)
            ->where('id', '!=', $city->id)
            ->get();

        $sameRegionCapitals = City::query()
            ->where('region', '=', $city->region)
            ->where('is_capital', '=', true)
            ->where('id', '!=', $city->id)
            ->get();

        $record = $this->getRecord();
        $record->loadMissing('caseStudies');

        $placeholders = [
            'city_name' => $city->name,
            'city_name_slug' => $city->slug,
            'city_description' => $city->description ?? '',
            'same_province_list' => $this->generateCityLinks($sameProvinceCities),
            'same_region_big_cities' => $this->generateCityLinks($sameRegionCapitals),
        ];

        $intro = $record->intro_content;
        $main = $record->main_content;

        foreach ($placeholders as $key => $value) {
            if ($intro !== null) {
                $intro = str_replace(
                    search: ['{'.$key.'}', $key],
                    replace: $value,
                    subject: $intro,
                );
            }
            if ($main !== null) {
                $main = str_replace(
                    search: ['{'.$key.'}', $key],
                    replace: $value,
                    subject: $main,
                );
            }
        }

        return [
            'intro' => $intro ?? '',
            'main' => $main ?? '',
        ];
    }

    protected function generateCityLinks($cities): string
    {
        if ($cities->isEmpty()) {
            return '';
        }

        return '<ul>'.$cities->map(fn (City $c) => '<li>'.$c->name.'</li>'
        )->implode('').'</ul>';
    }

    protected function renderCaseStudies(): string
    {
        $record = $this->getRecord();
        $caseStudies = $record->caseStudies->where('is_active', true);

        if ($caseStudies->isEmpty()) {
            return '<p class="text-neutral-400 italic">No case studies linked.</p>';
        }

        return '<ul>'.$caseStudies->map(fn ($cs) => '<li><strong>'.$cs->title.'</strong>: '.$cs->description.'</li>'
        )->implode('').'</ul>';
    }
}
