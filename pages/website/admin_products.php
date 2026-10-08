<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../permissions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

// Function to handle product image uploads (supports single and multiple files)
function handleProductImageUpload($product_id, $file_input_name, $directory, $prefix, $suffix = '', $index = null)
{
    if (isset($_FILES[$file_input_name])) {
        // Handle both single file and multiple files
        if ($index !== null && is_array($_FILES[$file_input_name]['name'])) {
            // Multiple files - specific index
            $file = [
                'name' => $_FILES[$file_input_name]['name'][$index],
                'type' => $_FILES[$file_input_name]['type'][$index],
                'tmp_name' => $_FILES[$file_input_name]['tmp_name'][$index],
                'error' => $_FILES[$file_input_name]['error'][$index],
                'size' => $_FILES[$file_input_name]['size'][$index]
            ];
        } else {
            // Single file
            $file = $_FILES[$file_input_name];
        }

        if ($file['error'] === 0) {
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $_SESSION['error'] = "File size too large. Maximum size is 5MB.";
                return false;
            }

            // Validate the ACTUAL file content rather than trusting the
            // browser-supplied $_FILES[...]['type'] header, which is just
            // whatever Content-Type the client chose to send and is
            // trivially spoofable (e.g. a renamed .php file claiming to be
            // "image/jpeg"). getimagesize() reads the real image headers,
            // so it also rejects non-image files outright.
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                return false;
            }

            $real_mime = $image_info['mime'];
            $allowed_types = [
                'image/jpeg' => 'imagecreatefromjpeg',
                'image/png' => 'imagecreatefrompng',
                'image/gif' => 'imagecreatefromgif',
            ];
            if (!isset($allowed_types[$real_mime])) {
                $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                return false;
            }

            // Create directory if it doesn't exist
            $upload_dir = "../../assets/images/{$directory}/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate filename
            $filename = $prefix . '-' . $product_id . $suffix . '.jpg';
            $file_path = $upload_dir . $filename;

            // Actually convert the image to a real JPEG (rather than just
            // renaming whatever bytes were uploaded to ".jpg"). This makes
            // the file's real format match its extension, and re-encoding
            // through GD strips out anything appended to the file that
            // isn't valid image data.
            $create_fn = $allowed_types[$real_mime];
            $source_image = @$create_fn($file['tmp_name']);
            if ($source_image === false) {
                $_SESSION['error'] = "Uploaded file could not be processed as an image.";
                return false;
            }

            // Flatten transparency (PNG/GIF) onto a white background before
            // saving as JPEG, since JPEG has no alpha channel.
            $width = imagesx($source_image);
            $height = imagesy($source_image);
            $jpeg_image = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($jpeg_image, 255, 255, 255);
            imagefill($jpeg_image, 0, 0, $white);
            imagealphablending($jpeg_image, true);
            imagecopy($jpeg_image, $source_image, 0, 0, 0, 0, $width, $height);
            imagedestroy($source_image);

            $saved = imagejpeg($jpeg_image, $file_path, 90);
            imagedestroy($jpeg_image);

            if ($saved) {
                // Clear file cache
                clearstatcache(true, $file_path);
                return true;
            }

            $_SESSION['error'] = "Failed to save uploaded image.";
        }
    }
    return false;
}

// Maximum product images per product (slots 0-4 => service-{id}.jpg, service-{id}-1.jpg ...)
const MAX_PRODUCT_IMAGES = 5;

function productImagePath($product_id, $index)
{
    $suffix = $index > 0 ? '-' . $index : '';
    return "../../assets/images/services/service-{$product_id}{$suffix}.jpg";
}

// URL (relative, same as the path) with a cache-buster so replaced files show immediately
function imageUrlWithVersion($path)
{
    return file_exists($path) ? $path . '?v=' . filemtime($path) : null;
}

// Returns [slotIndex => url] for every product image that exists
function getProductImageUrls($product_id)
{
    $urls = [];
    for ($i = 0; $i < MAX_PRODUCT_IMAGES; $i++) {
        $url = imageUrlWithVersion(productImagePath($product_id, $i));
        if ($url) {
            $urls[$i] = $url;
        }
    }
    return $urls;
}

// Saves newly chosen product images into the FREE slots only, so existing
// images are kept (previously file #1 always overwrote slot 0).
function saveUploadedProductImages($product_id)
{
    if (!isset($_FILES['product_images']) || empty($_FILES['product_images']['name'][0])) {
        return 0;
    }

    $free = [];
    for ($i = 0; $i < MAX_PRODUCT_IMAGES; $i++) {
        if (!file_exists(productImagePath($product_id, $i))) {
            $free[] = $i;
        }
    }

    $uploaded = 0;
    $skipped = 0;
    $count = count($_FILES['product_images']['name']);
    for ($f = 0; $f < $count; $f++) {
        if ($_FILES['product_images']['error'][$f] !== UPLOAD_ERR_OK) {
            continue;
        }
        if (empty($free)) {
            $skipped++;
            continue;
        }
        $slot = $free[0];
        $suffix = $slot > 0 ? '-' . $slot : '';
        if (handleProductImageUpload($product_id, 'product_images', 'services', 'service', $suffix, $f)) {
            array_shift($free);
            $uploaded++;
        }
    }

    if ($skipped > 0) {
        $_SESSION['error'] = "A product can have at most " . MAX_PRODUCT_IMAGES . " images. {$skipped} image(s) were not added - delete an existing image first.";
    }
    return $uploaded;
}

// Function to delete product images
function deleteProductImages($product_id, $image_type)
{
    $success = true;

    switch ($image_type) {
        case 'product_images':
            // Delete up to 5 product images (indices 0-4)
            $files = [];
            for ($i = 0; $i < 5; $i++) {
                $suffix = $i > 0 ? '-' . $i : '';
                $files[] = "../../assets/images/services/service-{$product_id}{$suffix}.jpg";
            }
            break;
        case 'base_templates':
            $files = [
                "../../assets/images/base/base-{$product_id}.jpg",
                "../../assets/images/base/base-{$product_id}-1.jpg"
            ];
            break;
        case 'all_images':
            // Delete all product images (up to 5) AND base templates
            $files = [];
            // Product images (indices 0-4)
            for ($i = 0; $i < 5; $i++) {
                $suffix = $i > 0 ? '-' . $i : '';
                $files[] = "../../assets/images/services/service-{$product_id}{$suffix}.jpg";
            }
            // Base templates
            $files[] = "../../assets/images/base/base-{$product_id}.jpg";
            $files[] = "../../assets/images/base/base-{$product_id}-1.jpg";
            break;
        default:
            return false;
    }

    foreach ($files as $file) {
        if (file_exists($file)) {
            if (!unlink($file)) {
                $success = false;
                error_log("Failed to delete file: $file");
            }
        }
    }

    return $success;
}

// Handle product actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // Permission gate (buttons stay visible; the page's own toast shows the notice)
    $needs = [
        'add_product' => 'web_products', 'update_product' => 'web_products',
        'update_customization' => 'web_products',
        'delete_product_images' => 'web_products', 'delete_single_image' => 'web_products',
        'delete_product' => 'web_delete',
    ];
    if (isset($needs[$action]) && !can($needs[$action])) {
        $_SESSION['error'] = permission_denied_message($needs[$action]);
        header("Location: admin_products.php");
        exit;
    }

    switch ($action) {
        case 'add_product':
            $product_name = trim($_POST['product_name']);
            $category = $_POST['category'];
            $price = $_POST['price'];

            // Check if product already exists
            $check_query = "SELECT id FROM products_offered WHERE product_name = ? AND category = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("ss", $product_name, $category);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Product '$product_name' already exists in this category!";
            } else {
                $query = "INSERT INTO products_offered (product_name, category, price) VALUES (?, ?, ?)";
                $stmt = $inventory->prepare($query);
                $stmt->bind_param("ssd", $product_name, $category, $price);

                if ($stmt->execute()) {
                    $product_id = $inventory->insert_id;
                    $_SESSION['message'] = "Product '$product_name' added successfully!";

                    // Handle image uploads (fills free slots, keeps existing images)
                    saveUploadedProductImages($product_id);

                    // Handle base template uploads for Other Services
                    if ($category === 'Other Services') {
                        handleProductImageUpload($product_id, 'base_image', 'base', 'base');
                        handleProductImageUpload($product_id, 'base_back_image', 'base', 'base', '-1');
                    }

                    // Add default customization settings
                    $custom_query = "INSERT INTO product_customization (product_id, has_paper_option, has_size_option, has_finish_option, has_layout_option, has_binding_option, has_gsm_option) VALUES (?, 0, 0, 0, 0, 0, 0)";
                    $custom_stmt = $inventory->prepare($custom_query);
                    $custom_stmt->bind_param("i", $product_id);
                    $custom_stmt->execute();

                    // For Other Services, enable size option by default
                    if ($category === 'Other Services') {
                        $update_custom_query = "UPDATE product_customization SET has_size_option = 1 WHERE product_id = ?";
                        $update_custom_stmt = $inventory->prepare($update_custom_query);
                        $update_custom_stmt->bind_param("i", $product_id);
                        $update_custom_stmt->execute();
                    }
                } else {
                    $_SESSION['error'] = "Failed to add product!";
                }
            }
            break;

        case 'update_product':
            $product_id = $_POST['product_id'];
            $product_name = trim($_POST['product_name']);
            $category = $_POST['category'];
            $price = $_POST['price'];

            // Check if product already exists (excluding current product)
            $check_query = "SELECT id FROM products_offered WHERE product_name = ? AND category = ? AND id != ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("ssi", $product_name, $category, $product_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Product '$product_name' already exists in this category!";
            } else {
                $query = "UPDATE products_offered SET product_name = ?, category = ?, price = ? WHERE id = ?";
                $stmt = $inventory->prepare($query);
                $stmt->bind_param("ssdi", $product_name, $category, $price, $product_id);

                if ($stmt->execute()) {
                    $_SESSION['message'] = "Product updated successfully!";

                    // Handle image uploads (fills free slots, keeps existing images)
                    saveUploadedProductImages($product_id);

                    // Handle base template uploads for Other Services
                    if ($category === 'Other Services') {
                        if (isset($_FILES['base_image']) && $_FILES['base_image']['error'] === 0) {
                            handleProductImageUpload($product_id, 'base_image', 'base', 'base');
                        }
                        if (isset($_FILES['base_back_image']) && $_FILES['base_back_image']['error'] === 0) {
                            handleProductImageUpload($product_id, 'base_back_image', 'base', 'base', '-1');
                        }
                    }
                } else {
                    $_SESSION['error'] = "Failed to update product!";
                }
            }
            break;

        case 'delete_product':
            $product_id = (int) $_POST['product_id'];

            // Check if product has orders
            $check_query = "SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("i", $product_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();

            if ($result['order_count'] > 0) {
                $_SESSION['error'] = "Cannot delete product - it has existing orders!";
            } else {
                // Everything below either all succeeds or all rolls back,
                // so a mid-way failure can't leave the product half-deleted
                // with dangling references elsewhere.
                $inventory->begin_transaction();

                try {
                    // Products that were never ordered can still be sitting
                    // in someone's cart or have open pricing requests
                    // against them - clear those out first so they don't
                    // end up pointing at a product_id that no longer exists.
                    $delete_cart_items_query = "DELETE FROM cart_items WHERE product_id = ?";
                    $delete_cart_items_stmt = $inventory->prepare($delete_cart_items_query);
                    $delete_cart_items_stmt->bind_param("i", $product_id);
                    $delete_cart_items_stmt->execute();

                    $delete_pricing_query = "DELETE FROM pricing_requests WHERE product_id = ?";
                    $delete_pricing_stmt = $inventory->prepare($delete_pricing_query);
                    $delete_pricing_stmt->bind_param("i", $product_id);
                    $delete_pricing_stmt->execute();

                    // Delete from all option tables first
                    $option_tables = [
                        'product_paper_options',
                        'product_finish_options',
                        'product_binding_options',
                        'product_layout_options',
                        'product_tshirt_sizes',
                        'product_tshirt_colors',
                        'product_totesizes',
                        'product_totecolors',
                        'product_paperbag_sizes',
                        'product_mug_sizes',
                        'product_mug_colors'
                    ];

                    foreach ($option_tables as $table) {
                        $delete_query = "DELETE FROM $table WHERE product_id = ?";
                        $delete_stmt = $inventory->prepare($delete_query);
                        $delete_stmt->bind_param("i", $product_id);
                        $delete_stmt->execute();
                    }

                    // Delete from product_customization
                    $delete_custom_query = "DELETE FROM product_customization WHERE product_id = ?";
                    $delete_custom_stmt = $inventory->prepare($delete_custom_query);
                    $delete_custom_stmt->bind_param("i", $product_id);
                    $delete_custom_stmt->execute();

                    // Then delete the product
                    $delete_query = "DELETE FROM products_offered WHERE id = ?";
                    $delete_stmt = $inventory->prepare($delete_query);
                    $delete_stmt->bind_param("i", $product_id);
                    $delete_stmt->execute();

                    if ($delete_stmt->affected_rows === 0) {
                        throw new Exception("Product not found or already deleted");
                    }

                    $inventory->commit();

                    // Only remove the image files once the DB transaction
                    // has actually committed - if it had rolled back, the
                    // product row would still exist but its images would
                    // already be gone.
                    deleteProductImages($product_id, 'all_images');

                    $_SESSION['message'] = "Product deleted successfully!";
                } catch (Throwable $e) {
                    // Throwable (not Exception) so PHP Errors, e.g. bind_param() on a
                    // failed prepare(), also roll back instead of leaving the page dead.
                    $inventory->rollback();
                    // The log line names the table/constraint that blocked the delete.
                    error_log("Failed to delete product {$product_id}: " . $e->getMessage());
                    if ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1451) {
                        $_SESSION['error'] = "Cannot delete product - it is still linked to other records. Check the PHP error log for the table name.";
                    } else {
                        $_SESSION['error'] = "Failed to delete product! (see PHP error log)";
                    }
                }
            }
            break;

        case 'delete_product_images':
            $product_id = (int) $_POST['product_id'];
            $image_type = $_POST['image_type'];

            if (deleteProductImages($product_id, $image_type)) {
                $_SESSION['message'] = "Images deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete images!";
            }
            break;

        case 'delete_single_image':
            $product_id = (int) $_POST['product_id'];
            $image_index = $_POST['image_index'];

            $success = false;

            if ($image_index === 'front') {
                // Delete front base template
                $file = "../../assets/images/base/base-{$product_id}.jpg";
                if (file_exists($file)) {
                    $success = unlink($file);
                }
            } elseif ($image_index === 'back') {
                // Delete back base template  
                $file = "../../assets/images/base/base-{$product_id}-1.jpg";
                if (file_exists($file)) {
                    $success = unlink($file);
                }
            } else {
                // Delete product image (0-4 index)
                $idx = (int) $image_index;
                if ($idx < 0 || $idx >= MAX_PRODUCT_IMAGES) {
                    $idx = 0;
                }
                $file = productImagePath($product_id, $idx);
                if (file_exists($file)) {
                    $success = unlink($file);
                }
            }

            if ($success) {
                $_SESSION['message'] = "Image deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete image!";
            }
            break;

        case 'update_customization':
            $product_id = $_POST['product_id'];
            $has_paper = isset($_POST['has_paper_option']) ? 1 : 0;
            $has_size = isset($_POST['has_size_option']) ? 1 : 0;
            $has_finish = isset($_POST['has_finish_option']) ? 1 : 0;
            $has_layout = isset($_POST['has_layout_option']) ? 1 : 0;
            $has_binding = isset($_POST['has_binding_option']) ? 1 : 0;
            $has_gsm = isset($_POST['has_gsm_option']) ? 1 : 0;

            $query = "UPDATE product_customization SET 
                    has_paper_option = ?, has_size_option = ?, has_finish_option = ?, 
                    has_layout_option = ?, has_binding_option = ?, has_gsm_option = ? 
                    WHERE product_id = ?";
            $stmt = $inventory->prepare($query);
            $stmt->bind_param("iiiiiii", $has_paper, $has_size, $has_finish, $has_layout, $has_binding, $has_gsm, $product_id);

            if ($stmt->execute()) {
                // Update available options based on customization settings
                updateProductOptions($inventory, $product_id, $has_paper, $has_finish, $has_binding, $has_layout);
                $_SESSION['message'] = "Customization settings updated!";
            } else {
                $_SESSION['error'] = "Failed to update customization settings!";
            }
            break;
    }

    header("Location: admin_products.php");
    exit;
}

// Function to update product options based on customization
function updateProductOptions($tshirtprint, $product_id, $has_paper, $has_finish, $has_binding, $has_layout)
{
    // Clear existing options
    $option_tables = [
        'product_paper_options',
        'product_finish_options',
        'product_binding_options',
        'product_layout_options'
    ];

    foreach ($option_tables as $table) {
        $clear_query = "DELETE FROM $table WHERE product_id = ?";
        $clear_stmt = $tshirtprint->prepare($clear_query);
        $clear_stmt->bind_param("i", $product_id);
        $clear_stmt->execute();
    }

    // Add all available options if customization is enabled
    if ($has_paper) {
        $paper_query = "INSERT INTO product_paper_options (product_id, paper_option_id) 
                       SELECT ?, id FROM paper_options";
        $paper_stmt = $tshirtprint->prepare($paper_query);
        $paper_stmt->bind_param("i", $product_id);
        $paper_stmt->execute();
    }

    if ($has_finish) {
        $finish_query = "INSERT INTO product_finish_options (product_id, finish_option_id) 
                        SELECT ?, id FROM finish_options";
        $finish_stmt = $tshirtprint->prepare($finish_query);
        $finish_stmt->bind_param("i", $product_id);
        $finish_stmt->execute();
    }

    if ($has_binding) {
        $binding_query = "INSERT INTO product_binding_options (product_id, binding_option_id) 
                         SELECT ?, id FROM binding_options";
        $binding_stmt = $tshirtprint->prepare($binding_query);
        $binding_stmt->bind_param("i", $product_id);
        $binding_stmt->execute();
    }

    if ($has_layout) {
        $layout_query = "INSERT INTO product_layout_options (product_id, layout_option_id) 
                        SELECT ?, id FROM layout_options";
        $layout_stmt = $tshirtprint->prepare($layout_query);
        $layout_stmt->bind_param("i", $product_id);
        $layout_stmt->execute();
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    switch ($_GET['ajax']) {
        case 'get_product':
            $product_id = (int) $_GET['product_id'];
            $query = "SELECT * FROM products_offered WHERE id = ?";
            $stmt = $inventory->prepare($query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();

                // Check for up to 5 product images
                for ($i = 0; $i < 5; $i++) {
                    $suffix = $i > 0 ? '-' . $i : '';
                    $product_image_path = "../../assets/images/services/service-" . $product_id . $suffix . ".jpg";
                    $product['product_image_exists_' . $i] = file_exists($product_image_path);
                }

                // Check base templates
                $base_image_path = "../../assets/images/base/base-" . $product_id . ".jpg";
                $base_back_image_path = "../../assets/images/base/base-" . $product_id . "-1.jpg";

                $product['base_image_exists'] = file_exists($base_image_path);
                $product['base_back_image_exists'] = file_exists($base_back_image_path);

                // URLs (with cache-buster) so the edit modal can show the actual images
                for ($i = 0; $i < MAX_PRODUCT_IMAGES; $i++) {
                    $product['product_image_url_' . $i] = imageUrlWithVersion(productImagePath($product_id, $i));
                }
                $product['base_image_url'] = imageUrlWithVersion($base_image_path);
                $product['base_back_image_url'] = imageUrlWithVersion($base_back_image_path);

                echo json_encode($product);
            } else {
                echo json_encode(['error' => 'Product not found']);
            }
            exit;

        case 'get_customization':
            $product_id = $_GET['product_id'];
            $query = "SELECT * FROM product_customization WHERE product_id = ?";
            $stmt = $inventory->prepare($query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $customization = $result->fetch_assoc();
                echo json_encode($customization);
            } else {
                echo json_encode(['error' => 'Customization settings not found']);
            }
            exit;
    }
}

// ---------------------------------------------------------------------
// Normal page load: filters, pagination, listing query.
// (Mirrors the orders page: search + category filter, summary counts,
// server-side paging. The listing is still read-only here; every write
// goes through the POST actions above.)
// ---------------------------------------------------------------------

/**
 * Bind a dynamic list of params to a mysqli statement (bind_param needs refs).
 */
if (!function_exists('bind_dynamic')) {
    function bind_dynamic(mysqli_stmt $stmt, string $types, array $params): void
    {
        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

/**
 * Small presentation helpers (UI only - no business logic).
 */
function pr_category_tone(string $category): string
{
    // Stable colour per category name, drawn from the same tone palette the
    // orders page uses for statuses, so badges look identical on both pages.
    $tones = ['tone-paid', 'tone-processing', 'tone-ready_for_pickup', 'tone-completed', 'tone-pending'];
    return $tones[abs(crc32(strtolower($category))) % count($tones)];
}

function pr_has_any_image(int $product_id): bool
{
    for ($i = 0; $i < MAX_PRODUCT_IMAGES; $i++) {
        if (file_exists(productImagePath($product_id, $i))) {
            return true;
        }
    }
    return false;
}

$PRODUCT_OPTION_LABELS = [
    'has_paper_option' => 'Paper',
    'has_size_option' => 'Size',
    'has_finish_option' => 'Finish',
    'has_layout_option' => 'Layout',
    'has_binding_option' => 'Binding',
    'has_gsm_option' => 'GSM',
];

// Categories (dropdown, tabs and filter validation)
$categories_result = $inventory->query("SELECT DISTINCT category FROM products_offered ORDER BY category");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}

$search = trim($_GET['search'] ?? '');
$category_filter = (string) ($_GET['category'] ?? '');
if (!in_array($category_filter, $categories, true)) {
    $category_filter = '';
}

$per_page = 30;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = [];
$params = [];
$types = '';

if ($category_filter !== '') {
    $where[] = "p.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = "(p.product_name LIKE ? OR p.id LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Pagination: page the catalog server-side so every product ever added
// isn't rendered into the DOM on each load.
$count_stmt = $inventory->prepare("SELECT COUNT(*) AS total FROM products_offered p $where_sql");
if ($types !== '') bind_dynamic($count_stmt, $types, $params);
$count_stmt->execute();
$total_products = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_products / $per_page));
$page = min($page, $total_pages); // clamp so a stale/typed-in page= doesn't return an empty page
$offset = ($page - 1) * $per_page;

// Get products with customization settings, one page at a time
$query = "SELECT p.*,
                 pc.has_paper_option, pc.has_size_option, pc.has_finish_option,
                 pc.has_layout_option, pc.has_binding_option, pc.has_gsm_option
          FROM products_offered p
          LEFT JOIN product_customization pc ON p.id = pc.product_id
          $where_sql
          ORDER BY p.category, p.product_name
          LIMIT ? OFFSET ?";
$list_params = $params;
$list_types = $types . 'ii';
$list_params[] = $per_page;
$list_params[] = $offset;
$products_stmt = $inventory->prepare($query);
bind_dynamic($products_stmt, $list_types, $list_params);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $product_id = $row['id'];
    $base_image_path = "../../assets/images/base/base-" . $product_id . ".jpg";
    $base_back_image_path = "../../assets/images/base/base-" . $product_id . "-1.jpg";

    // Every existing image, for the thumbnails in the table
    $row['image_urls'] = getProductImageUrls($product_id);
    $row['base_url'] = imageUrlWithVersion($base_image_path);
    $row['base_back_url'] = imageUrlWithVersion($base_back_image_path);

    $products[] = $row;
}

// Category tab counts (unfiltered by search, so counts stay stable while typing).
$tab_counts = ['all' => 0];
$count_by_category = $inventory->query("SELECT category, COUNT(*) AS c FROM products_offered GROUP BY category");
while ($row = $count_by_category->fetch_assoc()) {
    $tab_counts['all'] += (int) $row['c'];
    $tab_counts[$row['category']] = (int) $row['c'];
}

// Display-only summary values
$customizable_total = (int) $inventory->query(
    "SELECT COUNT(*) AS c FROM product_customization
     WHERE has_paper_option = 1 OR has_size_option = 1 OR has_finish_option = 1
        OR has_layout_option = 1 OR has_binding_option = 1 OR has_gsm_option = 1"
)->fetch_assoc()['c'];

$missing_images_total = 0;
$all_ids = $inventory->query("SELECT id FROM products_offered");
while ($row = $all_ids->fetch_assoc()) {
    if (!pr_has_any_image((int) $row['id'])) {
        $missing_images_total++;
    }
}

function build_query_url(array $overrides = []): string
{
    $current = ['category' => $_GET['category'] ?? '', 'search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? ''];
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return 'admin_products.php' . ($merged ? ('?' . http_build_query($merged)) : '');
}

$range_from = $total_products > 0 ? $offset + 1 : 0;
$range_to = $offset + count($products);
$has_filters = ($category_filter !== '' || $search !== '');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-products" data-page="products">
    <div class="admin-container">
        <main class="main-content">
            <div class="wa-page">

                <!-- 1. Page header -->
                <header class="wa-page-head">
                    <div>
                        <h1 class="wa-page-title">Product Management</h1>
                        <p class="wa-page-desc">Manage the products customers can order, their prices, customization options and images.</p>
                    </div>
                    <div class="wa-page-actions">
                        <button type="button" class="btn btn-secondary" onclick="exportProducts()">
                            <i class="fas fa-file-export" aria-hidden="true"></i> Export
                        </button>
                        <button type="button" class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus" aria-hidden="true"></i> Add new product
                        </button>
                    </div>
                </header>

                <!-- 2. Summary -->
                <section class="wa-stats" aria-label="Product summary">
                    <a class="wa-stat tone-total <?php echo !$has_filters ? 'is-active' : ''; ?>" href="admin_products.php">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-box"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Total products</span>
                            <span class="wa-stat-value"><?php echo $tab_counts['all']; ?></span>
                            <span class="wa-stat-sub">Across all categories</span>
                        </span>
                    </a>
                    <div class="wa-stat tone-processing">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-layer-group"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Categories</span>
                            <span class="wa-stat-value"><?php echo count($categories); ?></span>
                            <span class="wa-stat-sub">Product groupings</span>
                        </span>
                    </div>
                    <div class="wa-stat tone-completed">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-sliders"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Customizable</span>
                            <span class="wa-stat-value"><?php echo $customizable_total; ?></span>
                            <span class="wa-stat-sub">With at least one option</span>
                        </span>
                    </div>
                    <div class="wa-stat <?php echo $missing_images_total > 0 ? 'tone-pending' : 'tone-completed'; ?>">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas <?php echo $missing_images_total > 0 ? 'fa-image' : 'fa-images'; ?>"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Missing images</span>
                            <span class="wa-stat-value"><?php echo $missing_images_total; ?></span>
                            <span class="wa-stat-sub"><?php echo $missing_images_total > 0 ? 'Products with no image' : 'Every product has an image'; ?></span>
                        </span>
                    </div>
                </section>

                <!-- 3 + 4. Search, filter and category navigation (one control) -->
                <section class="wa-filterbar" aria-label="Search and filter products">
                    <form class="wa-filterbar-form" method="get" action="admin_products.php" role="search">
                        <div class="wa-search">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" name="search" id="productSearch" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by product name or ID" aria-label="Search products by name or ID" autocomplete="off">
                            <?php if ($search !== ''): ?>
                                <a class="wa-search-clear" href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Clear search" title="Clear search">
                                    <i class="fas fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="wa-select">
                            <label class="sr-only" for="productCategoryFilter">Filter by category</label>
                            <select name="category" id="productCategoryFilter">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                        <?php if ($has_filters): ?>
                            <a href="admin_products.php" class="btn btn-ghost"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <nav class="wa-tabs" aria-label="Filter products by category">
                        <?php
                        $tab_items = [['', 'All']];
                        foreach ($categories as $cat_name) {
                            $tab_items[] = [$cat_name, $cat_name];
                        }
                        foreach ($tab_items as [$val, $label]):
                            $val = (string) $val;
                            $count_key = $val === '' ? 'all' : $val;
                            $is_active = $category_filter === $val;
                        ?>
                            <a class="wa-tab <?php echo $is_active ? 'is-active' : ''; ?>"
                                href="<?php echo build_query_url(['category' => $val, 'page' => '']); ?>"
                                <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <?php if ($val !== ''): ?><span class="wa-dot <?php echo pr_category_tone($val); ?>" aria-hidden="true"></span><?php endif; ?>
                                <?php echo htmlspecialchars($label); ?>
                                <span class="wa-tab-count"><?php echo $tab_counts[$count_key] ?? 0; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </section>

                <!-- 5 + 6. Products data and pagination -->
                <section class="wa-datacard" aria-labelledby="productsHeading">
                    <div class="wa-datacard-head">
                        <div>
                            <h2 class="wa-datacard-title" id="productsHeading"><?php echo $category_filter !== '' ? htmlspecialchars($category_filter) . ' products' : 'All products'; ?></h2>
                            <p class="wa-datacard-sub">
                                <?php if ($total_products > 0): ?>
                                    Showing <?php echo $range_from; ?>&ndash;<?php echo $range_to; ?> of <?php echo $total_products; ?> &middot; grouped by category
                                <?php else: ?>
                                    Nothing to show
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($has_filters): ?>
                            <div class="wa-filter-chips" aria-label="Active filters">
                                <?php if ($category_filter !== ''): ?>
                                    <span class="wa-filter-chip">
                                        <span>Category: <?php echo htmlspecialchars($category_filter); ?></span>
                                        <a href="<?php echo build_query_url(['category' => '', 'page' => '']); ?>" aria-label="Remove category filter" title="Remove category filter"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($search !== ''): ?>
                                    <span class="wa-filter-chip">
                                        <span>Search: &ldquo;<?php echo htmlspecialchars($search); ?>&rdquo;</span>
                                        <a href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Remove search filter" title="Remove search filter"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wa-table-wrap">
                        <table class="prd-table">
                            <caption class="sr-only">Products offered</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="is-num">Price</th>
                                    <th scope="col">Customization</th>
                                    <th scope="col">Images</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody id="productsTable">
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-row">
                                            <div class="wa-empty">
                                                <span class="wa-empty-icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                                                <div class="wa-empty-title">No products found</div>
                                                <p class="wa-empty-text">
                                                    <?php echo $has_filters ? 'Nothing matches the current search or category. Try a different term or clear the filters.' : 'Products will appear here once you add them.'; ?>
                                                </p>
                                                <?php if ($has_filters): ?>
                                                    <a href="admin_products.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left" aria-hidden="true"></i> Clear filters</a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()"><i class="fas fa-plus" aria-hidden="true"></i> Add new product</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($products as $product):
                                    $pid = (int) $product['id'];
                                    $thumb_limit = 4;
                                    $thumbs = array_slice($product['image_urls'], 0, $thumb_limit, true);
                                    $thumbs_extra = max(0, count($product['image_urls']) - $thumb_limit);
                                ?>
                                    <tr class="prd-row" id="product-row-<?php echo $pid; ?>">
                                        <td class="prd-col-product">
                                            <button type="button" class="prd-name" onclick="openEditModal(<?php echo $pid; ?>)"
                                                title="Edit <?php echo htmlspecialchars($product['product_name']); ?>"><?php echo htmlspecialchars($product['product_name']); ?></button>
                                            <div class="prd-meta">ID <?php echo $pid; ?></div>
                                        </td>
                                        <td class="prd-col-category">
                                            <span class="wa-badge <?php echo pr_category_tone((string) $product['category']); ?>"><?php echo htmlspecialchars($product['category']); ?></span>
                                        </td>
                                        <td class="prd-col-price is-num">
                                            <span class="prd-price">&#8369;<?php echo number_format($product['price'], 2); ?></span>
                                        </td>
                                        <td class="prd-col-options">
                                            <?php
                                            $enabled_options = [];
                                            foreach ($PRODUCT_OPTION_LABELS as $field => $label) {
                                                if (!empty($product[$field])) {
                                                    $enabled_options[] = $label;
                                                }
                                            }
                                            ?>
                                            <?php if ($enabled_options): ?>
                                                <div class="prd-chips">
                                                    <?php foreach ($enabled_options as $label): ?>
                                                        <span class="wa-chip"><?php echo htmlspecialchars($label); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="prd-state"><i class="fas fa-circle-minus" aria-hidden="true"></i> No options</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="prd-col-images">
                                            <div class="prd-thumbs">
                                                <?php foreach ($thumbs as $idx => $url): ?>
                                                    <a class="prd-thumb" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" title="Product image <?php echo $idx + 1; ?>">
                                                        <img src="<?php echo htmlspecialchars($url); ?>" alt="Product image <?php echo $idx + 1; ?>" loading="lazy"
                                                            onerror="this.closest('.prd-thumb').classList.add('is-broken')">
                                                        <i class="fas fa-image" aria-hidden="true"></i>
                                                    </a>
                                                <?php endforeach; ?>
                                                <?php if ($thumbs_extra > 0): ?>
                                                    <span class="prd-thumb-more" title="<?php echo $thumbs_extra; ?> more image(s)">+<?php echo $thumbs_extra; ?></span>
                                                <?php endif; ?>
                                                <?php if (empty($product['image_urls'])): ?>
                                                    <span class="prd-state is-missing" title="No product images"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> No image</span>
                                                <?php endif; ?>

                                                <?php if ($product['category'] === 'Other Services'): ?>
                                                    <?php foreach ([['Base front', $product['base_url']], ['Base back', $product['base_back_url']]] as [$label, $url]): ?>
                                                        <?php if ($url): ?>
                                                            <a class="prd-thumb prd-thumb--base" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" title="<?php echo $label; ?>">
                                                                <img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo $label; ?>" loading="lazy"
                                                                    onerror="this.closest('.prd-thumb').classList.add('is-broken')">
                                                                <i class="fas fa-vector-square" aria-hidden="true"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="prd-state is-warn" title="<?php echo $label; ?> template missing"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> <?php echo $label; ?></span>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="prd-col-actions">
                                            <div class="prd-actions">
                                                <button type="button" class="btn btn-sm btn-outline" onclick="openEditModal(<?php echo $pid; ?>)">
                                                    <i class="fas fa-pen" aria-hidden="true"></i> Edit
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline" onclick="openCustomizationModal(<?php echo $pid; ?>)">
                                                    <i class="fas fa-sliders" aria-hidden="true"></i> Options
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-icon prd-delete"
                                                    onclick="confirmDelete(<?php echo $pid; ?>, <?php echo esc_attr_js($product['product_name']); ?>)"
                                                    aria-label="Delete <?php echo htmlspecialchars($product['product_name']); ?>" title="Delete product">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="wa-datacard-foot">
                        <span class="wa-datacard-foot-text">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products</span>
                        <?php if ($total_pages > 1): ?>
                            <nav class="wa-pager" aria-label="Pagination">
                                <a class="wa-pager-link <?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page - 1]); ?>"
                                    <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>><i class="fas fa-chevron-left" aria-hidden="true"></i> Prev</a>
                                <?php
                                // A small window of page links around the current page, plus
                                // first/last, so this stays compact even with many pages.
                                $window = 2;
                                for ($p = 1; $p <= $total_pages; $p++) {
                                    $show = $p === 1 || $p === $total_pages || abs($p - $page) <= $window;
                                    if (!$show) {
                                        if ($p === 2 || $p === $total_pages - 1) {
                                            echo '<span class="wa-pager-gap" aria-hidden="true">&hellip;</span>';
                                        }
                                        continue;
                                    }
                                    $active = $p === $page;
                                    echo '<a class="wa-pager-link' . ($active ? ' is-active' : '') . '" href="' . build_query_url(['page' => $p]) . '"'
                                        . ($active ? ' aria-current="page"' : '') . ' aria-label="Page ' . $p . '">' . $p . '</a>';
                                }
                                ?>
                                <a class="wa-pager-link <?php echo $page >= $total_pages ? 'is-disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page + 1]); ?>"
                                    <?php echo $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            </nav>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Add/Edit product dialog -->
    <div id="productModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <div>
                    <h2 id="modalTitle">Add New Product</h2>
                    <p class="ord-dialog-sub" id="modalSub">Set the name, category and price, then add images.</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('productModal')" aria-label="Close">&times;</button>
            </div>
            <form id="productForm" method="post" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" id="formAction" value="add_product">
                <input type="hidden" name="product_id" id="productId">

                <div class="modal-body">
                    <div class="prd-stack">

                        <!-- Details -->
                        <section class="od-section">
                            <header class="od-section-head">
                                <h3 class="od-section-title"><i class="fas fa-box" aria-hidden="true"></i> Product details</h3>
                            </header>
                            <div class="od-section-body">
                                <div class="prd-field">
                                    <label class="prd-label" for="product_name">Product name</label>
                                    <input type="text" id="product_name" name="product_name" class="prd-input" placeholder="e.g. Business Cards" required>
                                </div>
                                <div class="prd-grid">
                                    <div class="prd-field">
                                        <label class="prd-label" for="category">Category</label>
                                        <select id="category" name="category" class="prd-input prd-select" required onchange="handleCategoryChange()">
                                            <option value="">Select category</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="prd-field">
                                        <label class="prd-label" for="price">Price</label>
                                        <div class="prd-input-wrap">
                                            <span class="prd-affix" aria-hidden="true">&#8369;</span>
                                            <input type="number" id="price" name="price" class="prd-input" step="0.01" min="0" placeholder="0.00" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Product images -->
                        <section class="od-section">
                            <header class="od-section-head">
                                <h3 class="od-section-title"><i class="fas fa-images" aria-hidden="true"></i> Product images</h3>
                                <span class="od-count">Up to 5</span>
                            </header>
                            <div class="od-section-body">
                                <div class="prd-upload">
                                    <label class="file-input-label">
                                        <i class="fas fa-upload" aria-hidden="true"></i> Choose product images
                                        <input type="file" class="file-input" name="product_images[]" accept="image/*" multiple onchange="showFileNames(this, 'productImagesFile')">
                                    </label>
                                    <span class="prd-file-name" id="productImagesFile">No files chosen</span>
                                </div>
                                <p class="ord-field-hint">Saved as service-{id}.jpg, service-{id}-1.jpg, service-{id}-2.jpg, and so on. New images fill the free slots.</p>

                                <div class="image-preview-container" id="productImagesPreview" style="display: none;"></div>
                            </div>

                            <!-- Current images (edit mode) -->
                            <div class="od-block" id="currentImagesSection" style="display: none;">
                                <div id="currentImagesList"></div>
                            </div>
                        </section>

                        <!-- Base templates (Other Services only) -->
                        <section class="od-section" id="baseTemplatesSection" style="display: none;">
                            <header class="od-section-head">
                                <h3 class="od-section-title"><i class="fas fa-vector-square" aria-hidden="true"></i> Base templates</h3>
                                <span class="od-count">Other Services</span>
                            </header>
                            <div class="od-section-body">
                                <div class="prd-grid">
                                    <div class="prd-field">
                                        <span class="prd-label">Front base template</span>
                                        <div class="prd-upload">
                                            <label class="file-input-label">
                                                <i class="fas fa-upload" aria-hidden="true"></i> Choose front base
                                                <input type="file" class="file-input" name="base_image" accept="image/*" onchange="showFileName(this, 'frontBaseFile')">
                                            </label>
                                            <span class="prd-file-name" id="frontBaseFile">No file chosen</span>
                                        </div>
                                        <p class="ord-field-hint">Saved as base-{id}.jpg</p>
                                        <div class="image-preview-container" id="frontBasePreview" style="display: none;">
                                            <div class="image-preview"><img id="frontBasePreviewImg" src="" alt="Front base preview"></div>
                                        </div>
                                    </div>

                                    <div class="prd-field">
                                        <span class="prd-label">Back base template <em>(optional)</em></span>
                                        <div class="prd-upload">
                                            <label class="file-input-label">
                                                <i class="fas fa-upload" aria-hidden="true"></i> Choose back base
                                                <input type="file" class="file-input" name="base_back_image" accept="image/*" onchange="showFileName(this, 'backBaseFile')">
                                            </label>
                                            <span class="prd-file-name" id="backBaseFile">No file chosen</span>
                                        </div>
                                        <p class="ord-field-hint">Saved as base-{id}-1.jpg</p>
                                        <div class="image-preview-container" id="backBasePreview" style="display: none;">
                                            <div class="image-preview"><img id="backBasePreviewImg" src="" alt="Back base preview"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current base templates (edit mode) -->
                            <div class="od-block" id="currentBaseSection" style="display: none;"></div>
                        </section>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveProductBtn">
                        <span id="saveBtnText">Save product</span>
                        <span id="saveBtnLoading" class="spinner" style="display: none;"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customization options dialog -->
    <div id="customizationModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="customizationModalTitle">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <div>
                    <h2 id="customizationModalTitle">Customization options</h2>
                    <p class="ord-dialog-sub">Choose what customers can customize on this product.</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('customizationModal')" aria-label="Close">&times;</button>
            </div>
            <form id="customizationForm" method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_customization">
                <input type="hidden" name="product_id" id="customizationProductId">

                <div class="modal-body">
                    <fieldset class="ord-options prd-options">
                        <legend class="sr-only">Enabled customization options</legend>
                        <?php
                        $option_fields = [
                            'has_paper_option' => 'Paper options',
                            'has_size_option' => 'Size options',
                            'has_finish_option' => 'Finish options',
                            'has_layout_option' => 'Layout options',
                            'has_binding_option' => 'Binding options',
                            'has_gsm_option' => 'GSM options',
                        ];
                        foreach ($option_fields as $field => $label): ?>
                            <label class="ord-option tone-total">
                                <input type="checkbox" class="sr-only" id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="1">
                                <span class="wa-dot" aria-hidden="true"></span>
                                <span class="ord-option-label"><?php echo $label; ?></span>
                                <i class="fas fa-check ord-option-check" aria-hidden="true"></i>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('customizationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save options</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite"></div>

    <?php
    $wa_data = [
        'products' => array_map(function ($product) {
            return [
                (string) $product['id'],
                (string) $product['product_name'],
                (string) $product['category'],
                (string) $product['price'],
            ];
        }, $products),
    ];
    ?>
    <script>
        window.WA_CONFIG = {
            csrfToken: <?php echo esc_js(csrf_token()); ?>,
            flash: {
                message: <?php echo isset($_SESSION['message']) ? esc_js($_SESSION['message']) : 'null'; ?>,
                error: <?php echo isset($_SESSION['error']) ? esc_js($_SESSION['error']) : 'null'; ?>
            },
            data: <?php echo json_encode($wa_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        };
        <?php unset($_SESSION['message'], $_SESSION['error']); ?>
    </script>
    <script src="../../assets/js/website_admin.js"></script>

</body>

</html>