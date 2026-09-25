<?php
/**
 * Request Portal — configuration sample.
 *
 * UA: Скопіюйте цей файл у config.php та заповніть дані підключення до MySQL.
 *     Прод: php tools/config_crypt.php decrypt (config.enc + .config-pass).
 * EN: Copy this file to config.php and fill in your MySQL credentials.
 *     Production: php tools/config_crypt.php decrypt (config.enc + .config-pass).
 */

return [
    'db' => [
        'host'       => 'localhost',
        'port'       => 3306,
        'name'       => 'request_portal',
        'user'       => 'request_portal',
        'pass'       => '',
        'charset'    => 'utf8mb4',
        // Managed MySQL (HolderPOS): ssl true, ssl_verify false.
        'ssl'        => false,
        'ssl_verify' => false,
        'ssl_cipher' => 'DEFAULT',
        'ssl_ca'     => '',
    ],

    'app' => [
        // UA: Назва, що виводиться у заголовку. EN: Title shown in the header.
        'name'         => 'Портал заявок',
        // UA: Шлях до застосунку, напр. '/requests'. Порожнє = визначити автоматично.
        // EN: Path the app is served from, e.g. '/requests'. Empty = auto-detect.
        'base_url'     => '',
        'timezone'     => 'Europe/Kyiv', // UA: час на екрані. EN: display timezone. DB stores UTC.
        'default_lang' => 'uk',
        // UA: Увімкнути показ помилок лише під час налаштування!
        // EN: Enable error output only while setting things up!
        'debug'        => false,
        // UA: Посилання на основний сайт (необов'язково). EN: Link back to the main site (optional).
        'site_url'     => 'https://uni-sport.edu.ua/university',
    ],

    'uploads' => [
        'dir'           => __DIR__ . '/storage/uploads',
        'max_files'     => 10,
        'max_file_size' => 200 * 1024 * 1024, // 200 MB
        /*
         * UA: Дозволені розширення та MIME-типи, які їм відповідають.
         * EN: Allowed extensions mapped to the MIME types accepted for them.
         */
        'allowed'       => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp'  => ['image/bmp', 'image/x-ms-bmp'],
            'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
            'mp4'  => ['video/mp4', 'application/mp4', 'video/quicktime'],
            'mov'  => ['video/quicktime', 'video/mp4'],
            'avi'  => ['video/x-msvideo', 'video/avi'],
            'mkv'  => ['video/x-matroska'],
            'webm' => ['video/webm'],
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/plain', 'text/csv', 'application/csv'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
        ],
    ],

    'security' => [
        // UA: Скільки заявок з однієї IP-адреси дозволено за годину (0 = без обмежень).
        // EN: How many requests one IP may submit per hour (0 = unlimited).
        'rate_limit_per_hour' => 5,
        'session_name'        => 'rp_session',
    ],

    'telegram' => [
        'enabled'         => true,
        'bot_token'       => '',
        'chat_id'         => '',
        'thread_plan'     => 6,
        'thread_feedback' => 95,
        'thread_request'  => 96,
        // UA: Окремий бот і супергрупа для заявок IT-відділу.
        // EN: Separate bot and supergroup for IT-department tickets.
        'it_bot_token'    => '',
        'it_chat_id'      => '',
        'thread_it'       => 3,
    ],
];
