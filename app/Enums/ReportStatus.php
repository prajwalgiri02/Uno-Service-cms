<?php

namespace App\Enums;

enum ReportStatus: string
{
    case OPEN = 'open';
    case RESOLVED = 'resolved';
}
