<?php

return [
    'account_deletion_grace_days' => (int) env('ACCOUNT_DELETION_GRACE_DAYS', 30),
    'admin_mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', false),
    'transactional_email_enabled' => (bool) env('TRANSACTIONAL_EMAIL_ENABLED', false),
];
