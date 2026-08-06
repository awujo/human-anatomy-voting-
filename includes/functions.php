<?php
// Shared helper functions. Requires config/config.php to already be loaded
// (session started) before use.

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . $path);
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

function flash_set($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function render_flashes() {
    foreach (flash_get() as $f) {
        $type = $f['type'] === 'error' ? 'danger' : h($f['type']);
        echo '<div class="alert alert-' . $type . '">' . h($f['message']) . '</div>';
    }
}

// Generates a random N-digit numeric PIN as a string (default 6 digits).
function generate_pin($digits = 6) {
    $min = (int) str_pad('1', $digits, '0');
    $max = (int) str_pad('', $digits, '9');
    return (string) random_int($min, $max);
}

// Handles a candidate photo upload from $_FILES[$field].
// Returns the stored relative path (e.g. "uploads/candidates/xxxx.jpg") or
// null if no file was submitted. Throws RuntimeException on invalid file.
function handle_photo_upload($field, $uploadDir, $publicPrefix) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Photo upload failed (error code ' . $file['error'] . ').');
    }

    if ($file['size'] > MAX_PHOTO_BYTES) {
        throw new RuntimeException('Photo is too large. Max size is ' . (MAX_PHOTO_BYTES / 1024 / 1024) . 'MB.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG or WEBP images are allowed.');
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = rtrim($uploadDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded photo.');
    }

    return rtrim($publicPrefix, '/') . '/' . $filename;
}

function log_admin_activity(PDO $pdo, $adminId, $action, $details = null) {
    $stmt = $pdo->prepare(
        'INSERT INTO admin_activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$adminId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
