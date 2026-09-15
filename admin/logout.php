<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/auth.php';

rp_admin_logout();
rp_flash('success', __('admin.logged_out'));
rp_redirect('admin/login.php');
