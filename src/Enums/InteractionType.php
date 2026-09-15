<?php

namespace DanielPetrica\LaravelActivityPub\Enums;

enum InteractionType: string
{
    case Like = 'Like';
    case Announce = 'Announce';
    case Bookmark = 'Bookmark';
    case Quote = 'Quote';
    case Comment = 'Comment';
}
