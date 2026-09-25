<?php
session_start();
require_once '../../config/security.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if (!csrf_valid()) {
    http_response_code(403);
    echo json_encode(['error' => 'Your session expired. Please refresh the page and try again.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// product_id ends up inside a file path, so it must be a plain positive integer.
$product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if ($product_id === false || $product_id === null || $product_id < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product']);
    exit;
}

// Create user-specific upload directory
$uploadDir = "../../assets/uploads/save_design/{$user_id}/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$response = [];

/**
 * Save a canvas "data:image/png;base64,..." string as a PNG file.
 * Only real PNG images under 10 MB are accepted. Returns the file name or null.
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
    $filename = $prefix . time() . '_' . bin2hex(random_bytes(8)) . '.png';
    return file_put_contents($dir . $filename, $binary) !== false ? $filename : null;
}

// Get design flags
$hasFrontDesign = isset($_POST['has_front_design']) && $_POST['has_front_design'] === '1';
$hasBackDesign  = isset($_POST['has_back_design']) && $_POST['has_back_design'] === '1';

// Handle front mockup image - ALWAYS CREATE
$frontFilename = null;
if ($hasFrontDesign && !empty($_POST['front_image']) && is_string($_POST['front_image'])) {
    $frontFilename = save_png_data_uri($_POST['front_image'], $uploadDir, 'front_mockup_');
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
    $response['front_mockup'] = "save_design/{$user_id}/{$frontFilename}";
}

// Handle back mockup image
$backFilename = null;
if ($hasBackDesign && !empty($_POST['back_image']) && is_string($_POST['back_image'])) {
    $backFilename = save_png_data_uri($_POST['back_image'], $uploadDir, 'back_mockup_');
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
    $response['back_mockup'] = "save_design/{$user_id}/{$backFilename}";
}

// Handle uploaded front / back design files (strictly validated)
foreach (['front' => 'front_design_file', 'back' => 'back_design_file'] as $side => $field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $upload = save_uploaded_file(
        $_FILES[$field],
        $uploadDir,
        upload_allowed_design(),
        $side . '_design_',
        UPLOAD_MAX_DESIGN_BYTES,
        true
    );
    if ($upload['ok']) {
        $response[$side . '_uploaded_file'] = "save_design/{$user_id}/{$upload['filename']}";
    } else {
        $response[$side . '_upload_error'] = $upload['error'];
    }
}

echo json_encode($response);