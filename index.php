<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn()) {
    redirect('modules/promotion/dashboard.php');
} else {
    redirect('modules/auth/login.php');
}
