<?php

namespace DanielPetrica\LaravelActivityPub\Enums;

enum ActivityStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Received = 'received';
    case Failed = 'failed';
}
