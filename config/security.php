<?php
/**
 * config/security.php
 *
 * Shared helpers used by the storefront and the admin pages:
 *   - CSRF tokens          (csrf_token, csrf_field, csrf_valid, csrf_require)
 *   - Output escaping      (esc_html, esc_js, esc_attr_js)
 *   - Safe file uploads    (save_uploaded_file + allow-lists)
 *   - Private file storage (private_storage_path) for payment proofs
 *   - Small validators     (valid_ymd, safe_referrer)
 *   - ORDER_TAX_RATE       (single source of truth for checkout + process_order)
 *
 * Put this file next to db.php (the config/ folder) and require it AFTER db.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------ */
/* Settings you may want to change                                     */
/* ------------------------------------------------------------------ */

// Tax added on top of the cart subtotal. checkout.php and process_order.php
// both use this so the number the customer sees is the number we charge.
if (!defined('ORDER_TAX_RATE')) {
    define('ORDER_TAX_RATE', 0.03);
}

// Where private files (payment proofs) are stored. NOT publicly downloadable.
// For best protection point this OUTSIDE your web root, e.g.
//   define('PRIVATE_STORAGE_DIR', 'C:/xampp/private_storage');
// You can define it in db.php before this file is loaded.
if (!defined('PRIVATE_STORAGE_DIR')) {
    define('PRIVATE_STORAGE_DIR', dirname(__DIR__) . '/../storage/private');
}

if (!defined('UPLOAD_MAX_PAYMENT_BYTES')) {
    define('UPLOAD_MAX_PAYMENT_BYTES', 5 * 1024 * 1024);   // 5 MB (matches checkout page text)
}
if (!defined('UPLOAD_MAX_DESIGN_BYTES')) {
    define('UPLOAD_MAX_DESIGN_BYTES', 25 * 1024 * 1024);   // 25 MB for print artwork
}

/* ------------------------------------------------------------------ */
/* Output escaping                                                     */
/* ------------------------------------------------------------------ */

/** Escape text for HTML body / attribute values. */
function esc_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Turn a value into a safe JavaScript string literal (quotes included) for use inside <script>. */
function esc_js($value): string
{
    return json_encode(
        (string) $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

/** Same as esc_js() but safe to place inside an HTML attribute such as onclick="...". */
function esc_attr_js($value): string
{
    return esc_html(esc_js($value));
}

/* ------------------------------------------------------------------ */
/* CSRF protection                                                     */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden <input> to drop inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . esc_html(csrf_token()) . '">';
}

/** True when the request carries the correct token (POST field or X-CSRF-Token header). */
function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Stop the request with a 403 if the CSRF token is missing or wrong. */
function csrf_require(bool $json = false): void
{
    if (csrf_valid()) {
        return;
    }
    http_response_code(403);
    $message = 'Your session expired or the request was not valid. Please go back, refresh the page and try again.';
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Request blocked</title></head>'
            . '<body style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 16px">'
            . '<h2>Request blocked</h2><p>' . esc_html($message) . '</p></body></html>';
    }
    exit;
}

/* ------------------------------------------------------------------ */
/* Small validators                                                    */
/* ------------------------------------------------------------------ */

/** Returns the string if it is a real YYYY-MM-DD date, otherwise null. */
function valid_ymd($value): ?string
{
    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
}

/** HTTP_REFERER, but only if it points at this same site. Otherwise $default. */
function safe_referrer(string $default): string
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref === '' || preg_match('/[\r\n]/', $ref)) {
        return $default;
    }
    $parts = parse_url($ref);
    if ($parts === false) {
        return $default;
    }
    if (isset($parts['host'])) {
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (strcasecmp($host, $_SERVER['HTTP_HOST'] ?? '') !== 0) {
            return $default;
        }
    }
    return $ref;
}

/* ------------------------------------------------------------------ */
/* Safe uploads                                                        */
/* ------------------------------------------------------------------ */

/**
 * Allow-lists are  extension => [allowed real MIME types]  or  extension => null
 * (null = extension-only, for formats PHP cannot reliably detect such as .psd/.ai/.docx).
 */
function upload_allowed_payment(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
    ];
}

function upload_allowed_design(): array
{
    return upload_allowed_payment() + [
        'psd'  => null,
        'ai'   => null,
        'eps'  => null,
        'cdr'  => null,
        'doc'  => null,
        'docx' => null,
        'ppt'  => null,
        'pptx' => null,
    ];
}

/**
 * Validate and store one uploaded file.
 *
 *  - checks the upload really came from PHP's upload handler
 *  - enforces a size limit
 *  - only accepts extensions in $allowed, and (for images/PDF) checks the REAL content type
 *  - never uses the customer's extension or name as-is: the stored name is
 *    prefix + random + (optionally a cleaned version of the original name) + safe extension
 *
 * @return array ['ok' => true, 'filename' => '...', 'path' => '...'] or ['ok' => false, 'error' => '...']
 */
function save_uploaded_file(array $file, string $dir, array $allowed, string $prefix, int $maxBytes, bool $keepOriginalName = false): array
{
    $fail = static function (string $msg): array {
        return ['ok' => false, 'error' => $msg];
    };

    if (!isset($file['error']) || is_array($file['error'])) {
        return $fail('Invalid upload.');
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return $fail('The file is too large.');
        case UPLOAD_ERR_NO_FILE:
            return $fail('No file was uploaded.');
        default:
            return $fail('The upload failed. Please try again.');
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp)) {
        return $fail('Invalid upload.');
    }
    if (filesize($tmp) > $maxBytes) {
        return $fail('The file is too large (maximum ' . round($maxBytes / 1048576) . ' MB).');
    }

    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext === '' || !array_key_exists($ext, $allowed)) {
        return $fail('This file type is not allowed. Allowed types: ' . implode(', ', array_keys($allowed)) . '.');
    }

    if ($allowed[$ext] !== null) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, $allowed[$ext], true)) {
            return $fail('The file content does not match its type.');
        }
        if (strpos($mime, 'image/') === 0 && @getimagesize($tmp) === false) {
            return $fail('The image file is not valid.');
        }
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return $fail('The file could not be stored.');
    }

    $name = $prefix . bin2hex(random_bytes(12));
    if ($keepOriginalName) {
        // Letters, numbers, _ and - only (no dots) so "shell.php.jpg" style tricks are impossible.
        $base = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($original, PATHINFO_FILENAME));
        $base = substr(trim((string) $base, '_'), 0, 60);
        if ($base !== '') {
            $name .= '_' . $base;
        }
    }
    $name .= '.' . $ext;

    $target = rtrim($dir, '/\\') . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        return $fail('The file could not be stored.');
    }
    @chmod($target, 0644);

    return ['ok' => true, 'filename' => $name, 'path' => $target];
}

/* ------------------------------------------------------------------ */
/* Private storage                                                     */
/* ------------------------------------------------------------------ */

/**
 * Returns (and creates) a folder inside PRIVATE_STORAGE_DIR.
 * The base folder gets an .htaccess that blocks direct web access, as a safety net
 * in case it ends up inside the web root.
 */
function private_storage_path(string $sub = ''): string
{
    $base = PRIVATE_STORAGE_DIR;
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }
    $guard = $base . '/.htaccess';
    if (is_dir($base) && !file_exists($guard)) {
        @file_put_contents(
            $guard,
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
        );
    }
    $path = $sub === '' ? $base : $base . '/' . trim($sub, '/\\');
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    return $path;
}

/**
 * Where a stored payment proof lives on disk (new private folder first, then the old
 * public folder for orders placed before the fix). Returns null if the file is missing.
 */
function payment_proof_path(int $user_id, string $file): ?string
{
    $file = basename($file);   // never trust a stored path
    if ($file === '' || $file === '.' || $file === '..') {
        return null;
    }
    $candidates = [
        PRIVATE_STORAGE_DIR . "/payments/user_{$user_id}/{$file}",
        dirname(__DIR__) . "/assets/uploads/payments/user_{$user_id}/{$file}",
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/**
 * URL of the script that serves payment proofs. The admin pages live in a different folder
 * from payment_proof.php, so if your folders are named differently, define PAYMENT_PROOF_URL
 * (for example in db.php) before this file loads.
 */
function payment_proof_url(int $order_id): string
{
    $base = defined('PAYMENT_PROOF_URL') ? PAYMENT_PROOF_URL : '../website/payment_proof.php';
    return $base . '?order_id=' . $order_id;
}