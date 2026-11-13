<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Draft = 'draft';
    case Verified = 'verified';
    case NeedsReview = 'needs_review';
}
