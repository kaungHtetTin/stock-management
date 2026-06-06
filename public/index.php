<?php
require dirname(__DIR__) . '/bootstrap/init.php';

if (!is_logged_in()) {
    redirect('login.php');
}

redirect('pages/dashboard.php');
