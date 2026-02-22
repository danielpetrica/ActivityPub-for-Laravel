<?php

namespace App\Tiptap\Nodes;

use Tiptap\Core\Node;
use Tiptap\Utils\HTML;

final class Figcaption extends Node
{
    public static $name = 'figcaption';

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
                'tag' => 'figcaption',
            ],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = [])
    {
        return ['figcaption', HTML::mergeAttributes($this->options['HTMLAttributes'], $HTMLAttributes), 0];
    }
}
