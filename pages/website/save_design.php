<?php
session_start();
require_once '../../config/security.php';
require_once '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!csrf_valid()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Basic rate limit: this endpoint writes files to disk, so throttle rapid
// repeat submissions from the same session (double-clicks, retry loops, abuse).
const SAVE_DESIGN_MIN_INTERVAL_SECONDS = 2;
$now = microtime(true);
if (
    isset($_SESSION['last_save_design_time']) &&
    ($now - $_SESSION['last_save_design_time']) < SAVE_DESIGN_MIN_INTERVAL_SECONDS
) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Please wait a moment before saving again.']);
    exit;
}
$_SESSION['last_save_design_time'] = $now;

// product_id ends up inside a file path, so it must be a plain positive integer.
$product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if ($product_id === false || $product_id === null || $product_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

// Confirm the product actually exists before we do any file work for it.
$product_check_stmt = $inventory->prepare('SELECT id FROM products_offered WHERE id = ? LIMIT 1');
$product_check_stmt->bind_param('i', $product_id);
$product_check_stmt->execute();
if (!$product_check_stmt->get_result()->fetch_assoc()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product']);
    exit;
}

// Create user- and product-specific upload directory. Namespacing by product_id
// (rather than dumping every product's mockups into one user folder) keeps
// designs for different products from colliding and makes cleanup below safe.
$uploadDir = "../../assets/uploads/save_design/{$user_id}/{$product_id}/";
if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not prepare storage for this design.']);
    exit;
}

$response = ['success' => true];
$errors = [];

// Reasonable ceiling on decoded pixel dimensions so a small-byte-size but
// enormous-pixel-count PNG (decompression-bomb style) can't be smuggled through
// just because it passes the byte-size check.
const MAX_DESIGN_DIMENSION_PX = 8000;

/**
 * Save a canvas "data:image/png;base64,..." string as a PNG file.
 * Only real PNG images under 10 MB and within MAX_DESIGN_DIMENSION_PX are
 * accepted. Returns the file name or null.
 */
function save_png_data_uri(string $data, string $dir, string $prefix): ?string
{
    $header = 'data:image/png;base64,';
    if (strpos($data, $header) !== 0) {
        return null;
    }
    $binary = base64_decode(str_replace(' ', '+', substr($data, strlen($header))), true);
    if ($binary === false || $binary === '' || strlen($binary) > 10 * 1024 * 1024) {
        return null;
    }
    $info = @getimagesizefromstring($binary);
    if ($info === false || $info[2] !== IMAGETYPE_PNG) {
        return null;
    }
    if ($info[0] > MAX_DESIGN_DIMENSION_PX || $info[1] > MAX_DESIGN_DIMENSION_PX) {
        return null;
    }
    $filename = $prefix . time() . '_' . bin2hex(random_bytes(8)) . '.png';
    return file_put_contents($dir . $filename, $binary) !== false ? $filename : null;
}

/**
 * Remove previously saved files for a given side/prefix in this user+product
 * folder before writing the new one, so re-saving a design doesn't leave old
 * mockups and uploads behind forever.
 */
function clear_old_files(string $dir, string $globPattern): void
{
    foreach ((glob($dir . $globPattern) ?: []) as $oldFile) {
        @unlink($oldFile);
    }
}

// Get design flags
$hasFrontDesign = isset($_POST['has_front_design']) && $_POST['has_front_design'] === '1';
$hasBackDesign  = isset($_POST['has_back_design']) && $_POST['has_back_design'] === '1';

// Handle front mockup image - ALWAYS CREATE
clear_old_files($uploadDir, 'front_mockup_*.png');
$frontFilename = null;
if ($hasFrontDesign && !empty($_POST['front_image']) && is_string($_POST['front_image'])) {
    $frontFilename = save_png_data_uri($_POST['front_image'], $uploadDir, 'front_mockup_');
    if ($frontFilename === null) {
        $errors[] = 'Front design image could not be processed; used the plain template instead.';
    }
}
if ($frontFilename === null) {
    // Create a plain front mockup (just the template if available)
    $baseImagePath = "../../assets/images/base/base-" . $product_id . ".jpg";
    if (file_exists($baseImagePath)) {
        $plainName = 'front_mockup_' . time() . '_' . bin2hex(random_bytes(8)) . '.png';
        if (copy($baseImagePath, $uploadDir . $plainName)) {
            $frontFilename = $plainName;
        }
    }
}
if ($frontFilename !== null) {
    $response['front_mockup'] = "save_design/{$user_id}/{$product_id}/{$frontFilename}";
}

// Handle back mockup image
clear_old_files($uploadDir, 'back_mockup_*.png');
$backFilename = null;
if ($hasBackDesign && !empty($_POST['back_image']) && is_string($_POST['back_image'])) {
    $backFilename = save_png_data_uri($_POST['back_image'], $uploadDir, 'back_mockup_');
    if ($backFilename === null) {
        $errors[] = 'Back design image could not be processed; used the plain template instead.';
    }
}
if ($backFilename === null) {
    // Create a plain back mockup ONLY IF BACK TEMPLATE EXISTS
    $backImagePath = "../../assets/images/base/base-" . $product_id . "-1.jpg";
    if (file_exists($backImagePath)) {
        $plainName = 'back_mockup_' . time() . '_' . bin2hex(random_bytes(8)) . '.png';
        if (copy($backImagePath, $uploadDir . $plainName)) {
            $backFilename = $plainName;
        }
    }
}
if ($backFilename !== null) {
    $response['back_mockup'] = "save_design/{$user_id}/{$product_id}/{$backFilename}";
}

/**
 * PHP collapses an <input name="front_design_file[]" multiple> upload into
 * $_FILES['front_design_file'] = ['name' => [...], 'tmp_name' => [...], ...]
 * (one array per property, not one array per file). This splits it back
 * into a normal list of single-file arrays, each shaped the way
 * save_uploaded_file() expects. A plain (non-array) single-file field is
 * also accepted, so an older client that still posts one file still works.
 */
function normalize_files_field($field): array
{
    if (!is_array($field) || !isset($field['name'])) {
        return [];
    }
    if (!is_array($field['name'])) {
        // Single file, not an array upload.
        return $field['error'] === UPLOAD_ERR_NO_FILE ? [] : [$field];
    }
    $files = [];
    foreach ($field['name'] as $i => $name) {
        if (($field['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name'     => $name,
            'type'     => $field['type'][$i]     ?? '',
            'tmp_name' => $field['tmp_name'][$i]  ?? '',
            'error'    => $field['error'][$i]     ?? UPLOAD_ERR_NO_FILE,
            'size'     => $field['size'][$i]      ?? 0,
        ];
    }
    return $files;
}

// Reasonable ceiling on how many original files we'll store per side.
const MAX_DESIGN_ORIGINALS_PER_SIDE = 10;

// Handle uploaded front / back design files (strictly validated, jpeg/png only)
foreach (['front' => 'front_design_file', 'back' => 'back_design_file'] as $side => $field) {
    $files = normalize_files_field($_FILES[$field] ?? null);
    if (empty($files)) {
        continue;
    }

    clear_old_files($uploadDir, $side . '_design_*');

    if (count($files) > MAX_DESIGN_ORIGINALS_PER_SIDE) {
        $files = array_slice($files, 0, MAX_DESIGN_ORIGINALS_PER_SIDE);
        $errors[] = ucfirst($side) . ": only the first " . MAX_DESIGN_ORIGINALS_PER_SIDE . " original files were saved.";
    }

    $savedPaths = [];
    foreach ($files as $file) {
        $upload = save_uploaded_file(
            $file,
            $uploadDir,
            upload_allowed_design_image(),
            $side . '_design_',
            UPLOAD_MAX_DESIGN_BYTES,
            true
        );
        if ($upload['ok']) {
            $savedPaths[] = "save_design/{$user_id}/{$product_id}/{$upload['filename']}";
        } else {
            $errors[] = ($file['name'] ?? 'File') . ': ' . $upload['error'];
        }
    }

    if (!empty($savedPaths)) {
        $response[$side . '_uploaded_files'] = $savedPaths;
    }
}

if (!empty($errors)) {
    $response['success'] = false;
    $response['errors'] = $errors;
}

echo json_encode($response);