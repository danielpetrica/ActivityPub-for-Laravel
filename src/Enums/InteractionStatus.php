<?php

namespace DanielPetrica\LaravelActivityPub\Enums;

enum InteractionStatus: string
{
    case Active = 'Active';
    case Undone = 'Undone';
}
