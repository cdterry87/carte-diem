<?php

namespace App\Enums;

enum ListItemStatus: string
{
    case Needed = 'needed';
    case Satisfied = 'satisfied';
}
