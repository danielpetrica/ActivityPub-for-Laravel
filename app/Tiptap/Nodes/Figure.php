<?php

namespace App\Tiptap\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

final class Figure extends Node
{
    public static $name = 'figure';

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
                'tag' => 'figure',
            ],
        ];
    }

    public function addAttributes()
    {
        return [
            'class' => [],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        return ['figure', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
