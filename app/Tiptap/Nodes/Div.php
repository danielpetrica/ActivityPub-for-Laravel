<?php

namespace App\Tiptap\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

final class Div extends Node
{
    public static $name = 'div';

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
                'tag' => 'div',
            ],
        ];
    }

    public function addAttributes()
    {
        return [
            'class' => [],
            'id' => [],
            'style' => [],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        return ['div', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
