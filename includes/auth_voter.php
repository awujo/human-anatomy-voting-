<?php
// Include after config/db.php + includes/functions.php. Redirects to voter
// login if there is no authenticated voter in the session.

if (empty($_SESSION['voter_id'])) {
    redirect(BASE_URL . '/vote/login.php');
}
