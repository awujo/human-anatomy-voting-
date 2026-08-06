<?php
// Include after config/db.php + includes/functions.php. Redirects to admin
// login if there is no authenticated admin in the session.

if (empty($_SESSION['admin_id'])) {
    redirect(BASE_URL . '/admin/login.php');
}
