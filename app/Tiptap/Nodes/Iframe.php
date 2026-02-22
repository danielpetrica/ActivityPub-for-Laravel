<?php

namespace App\Tiptap\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

final class Iframe extends Node
{
    public static $name = 'iframe';

    public function addOptions()
    {
        return [
            'HTMLAttributes' => [],
        ];
    }

    public function parseHTML()
    {
        return [
            [
                'tag' => 'iframe',
            ],
        ];
    }

    public function addAttributes()
    {
        return [
            'src' => [],
            'width' => [],
            'height' => [],
            'frameborder' => [],
            'allow' => [],
            'allowfullscreen' => [],
            'referrerpolicy' => [],
            'loading' => [],
            'title' => [],
            'class' => [],
            'style' => [],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        // Void element; no children (0 is fine, library will ignore children if void)
        return ['iframe', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
