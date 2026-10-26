<?php
require_once __DIR__ . '/config/session.php';

if (isLoggedIn()) {
    header('Location: dashboard/index.php');
} else {
    header('Location: auth/login.php');
}
exit;
