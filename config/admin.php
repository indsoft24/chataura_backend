<?php

return [
    'email' => env('ADMIN_EMAIL', 'admin@chataura.com'),
    'password' => env('ADMIN_PASSWORD', 'changeme'),
    'password_hash' => env('ADMIN_PASSWORD_HASH'), // Optional: bcrypt hash; if set, used instead of plain password
    'system_user_id' => (int) env('SYSTEM_USER_ID', 1), // User ID that holds the platform/admin wallet for call commission
];
