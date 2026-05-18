<?php

namespace App\Filament\Actions;

use App\Ai\Agents\SeoGenerator;
use App\Classes\SEO\ProseMirrorTextExtractor;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

trait GeneratesSeoMetadata
{
    public function generateSeoAction(): Action
    {
        return Action::make('generateSeo')
            ->label('Generate SEO Metadata')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->link()
            ->modalHeading('Generate SEO Metadata')
            ->modalDescription('Review the extracted page content before sending it to the AI. You can edit the text below.')
            ->modalSubmitActionLabel('Generate SEO')
            ->schema([
                Textarea::make('context')
                    ->label('Page Content')
                    ->rows(12)
                    ->autosize()
                    ->helperText('This is the plain-text version of your content. Edit it freely before generating SEO metadata.'),
            ])
            ->fillForm(fn (): array => [
                'context' => $this->extractContentForSeo(),
            ])
            ->action(function (array $data) {
                try {
                    $response = (new SeoGenerator)->prompt(
                        prompt: "Generate optimized SEO metadata for this page.\n\nTitle: {$this->data['title']}\n\nContent: {$data['context']}",
                    );

                    $this->form->fill([
                        'meta_title' => $response['meta_title'] ?? '',
                        'meta_description' => $response['meta_description'] ?? '',
                        'og_title' => $response['og_title'] ?? '',
                        'og_description' => $response['og_description'] ?? '',
                        'twitter_title' => $response['twitter_title'] ?? '',
                        'twitter_description' => $response['twitter_description'] ?? '',
                    ]);

                    Notification::make()
                        ->title('SEO metadata generated successfully!')
                        ->body('Review and edit the generated values before saving.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Failed to generate SEO metadata')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function extractContentForSeo(): string
    {
        return ProseMirrorTextExtractor::extract(
            content: $this->data['content'] ?? null,
        );
    }
}
