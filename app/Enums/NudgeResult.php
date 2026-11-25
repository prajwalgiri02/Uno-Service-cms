<?php

namespace App\Enums;

enum NudgeResult: string
{
    case SENT = 'sent';
    case OPENED = 'opened';
    case VISITED = 'visited';
    case IGNORED = 'ignored';
}
