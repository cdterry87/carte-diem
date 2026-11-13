<?php

/*
 * Facts the public policies state about the operator. Anything left unset renders
 * as a visible bracketed placeholder, so a missing fact cannot pass for a real one.
 */
return [
    'entity' => env('LEGAL_ENTITY_NAME') ?: null,
    'contact_email' => env('LEGAL_CONTACT_EMAIL') ?: null,
    'effective_date' => env('LEGAL_EFFECTIVE_DATE') ?: null,
    'backup_retention' => env('LEGAL_BACKUP_RETENTION') ?: null,
    'log_retention' => env('LEGAL_LOG_RETENTION') ?: null,
];
