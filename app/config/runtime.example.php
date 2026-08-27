<?php

declare(strict_types=1);

// Copy to runtime.php on the host, replace every placeholder, and keep it outside the public document root.
return [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'https://app.example.com',
    'APP_KEY' => '',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'architex',
    'DB_USER' => 'architex_app',
    'DB_PASSWORD' => '',
    'SMTP_HOST' => '',
    'SMTP_PORT' => '587',
    'SMTP_ENCRYPTION' => 'starttls',
    'SMTP_USERNAME' => '',
    'SMTP_PASSWORD' => '',
    'SMTP_FROM_EMAIL' => '',
    'SMTP_FROM_NAME' => 'Architex',
    'BACKUP_ARCHIVE_PASSWORD' => '',
    'FILE_STORAGE_PATH' => '',
    'MAINTENANCE_ENABLED' => '0',
    'MAINTENANCE_TOKEN' => '',
    'MAINTENANCE_ALLOW_MIGRATIONS' => '0',
    'LAST_VERIFIED_BACKUP_ID' => '',
];
