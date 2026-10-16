<?php

namespace DanielPetrica\LaravelActivityPub\Enums;

enum ActivityType: string
{
    case Accept = 'Accept';
    case Announce = 'Announce';
    case Block = 'Block';
    case Create = 'Create';
    case Delete = 'Delete';
    case Flag = 'Flag';
    case Follow = 'Follow';
    case Like = 'Like';
    case Move = 'Move';
    case Reject = 'Reject';
    case Undo = 'Undo';
    case Update = 'Update';
    case View = 'View';
}
