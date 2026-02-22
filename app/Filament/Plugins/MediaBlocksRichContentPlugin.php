<?php

namespace App\Filament\Plugins;

use App\Tiptap\Nodes\Div;
use App\Tiptap\Nodes\Figcaption;
use App\Tiptap\Nodes\Figure;
use App\Tiptap\Nodes\Iframe;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Tiptap\Core\Extension;

/**
 * Adds support for media-related HTML blocks (figure/figcaption/iframe/div)
 * to Filament RichEditor (TipTap) so existing imported content loads cleanly.
 */
final class MediaBlocksRichContentPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    /**
     * @return array<Extension>
     */
    public function getTipTapPhpExtensions(): array
    {
        // Provide PHP TipTap extensions so the server-side renderer & sanitizer
        // understand the same nodes as the editor.
        return [
            new Div,
            new Figure,
            new Figcaption,
            new Iframe,
        ];
    }

    /**
     * @return array<string>
     */
    public function getTipTapJsExtensions(): array
    {
        // These files are compiled and registered via FilamentAsset in AppServiceProvider.
        return [
            FilamentAsset::getScriptSrc('rich-content-plugins/figure'),
            FilamentAsset::getScriptSrc('rich-content-plugins/figcaption'),
            FilamentAsset::getScriptSrc('rich-content-plugins/div'),
            FilamentAsset::getScriptSrc('rich-content-plugins/iframe'),
            // Helper that rewrites external Hetzner image URLs to a local proxy in admin context
            FilamentAsset::getScriptSrc('rich-content-plugins/image-proxy'),
        ];
    }

    /**
     * @return array<RichEditorTool>
     */
    public function getEditorTools(): array
    {
        // No toolbar buttons needed for simply parsing existing content.
        return [];
    }

    /**
     * @return array<Action>
     */
    public function getEditorActions(): array
    {
        return [];
    }
}
