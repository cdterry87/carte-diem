<?php

namespace App\Enums;

enum ListShareRole: string
{
    case Editor = 'editor';
    case Viewer = 'viewer';
}
