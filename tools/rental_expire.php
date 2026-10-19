<?php
/**
 * Cancel unpaid rental deals whose payment deadline has passed.
 * Cron: * /5 * * * * php /path/to/tools/rental_expire.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$count = rp_rental_expire_overdue(rp_db());
echo "Expired deals: {$count}\n";
