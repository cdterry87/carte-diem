<?php

namespace App\Enums;

/**
 * One-time tips shown at the top of the dashboard. Once someone dismisses one, it never returns.
 */
enum DashboardNotice: string
{
    case Household = 'household';
}
