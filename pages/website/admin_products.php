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

// Pagination: the catalog is small today but this listing has no LIMIT at
// all, so every product ever added gets rendered into the DOM on every
// load. Page it server-side now, before that becomes a real cost.
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$total_products_result = $inventory->query("SELECT COUNT(*) as total FROM products_offered");
$total_products = (int) $total_products_result->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_products / $per_page));
$page = min($page, $total_pages); // clamp so a stale/typed-in page= doesn't return an empty page
$offset = ($page - 1) * $per_page;

// Get products with customization settings, one page at a time
$query = "SELECT p.*, 
                 pc.has_paper_option, pc.has_size_option, pc.has_finish_option,
                 pc.has_layout_option, pc.has_binding_option, pc.has_gsm_option
          FROM products_offered p
          LEFT JOIN product_customization pc ON p.id = pc.product_id
          ORDER BY p.category, p.product_name
          LIMIT ? OFFSET ?";
$products_stmt = $inventory->prepare($query);
$products_stmt->bind_param('ii', $per_page, $offset);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products = [];
while ($row = $products_result->fetch_assoc()) {
    // Check image existence for each product
    $product_id = $row['id'];
    $product_image_path = "../../assets/images/services/service-" . $product_id . ".jpg";
    $product_back_image_path = "../../assets/images/services/service-" . $product_id . "-1.jpg";
    $base_image_path = "../../assets/images/base/base-" . $product_id . ".jpg";
    $base_back_image_path = "../../assets/images/base/base-" . $product_id . "-1.jpg";

    $row['product_image_exists'] = file_exists($product_image_path);
    $row['product_back_image_exists'] = file_exists($product_back_image_path);
    $row['base_image_exists'] = file_exists($base_image_path);
    $row['base_back_image_exists'] = file_exists($base_back_image_path);

    // Every existing image, for the thumbnails in the table
    $row['image_urls'] = getProductImageUrls($product_id);
    $row['base_url'] = imageUrlWithVersion($base_image_path);
    $row['base_back_url'] = imageUrlWithVersion($base_back_image_path);

    $products[] = $row;
}

// Get unique categories for dropdown
$categories_query = "SELECT DISTINCT category FROM products_offered ORDER BY category";
$categories_result = $inventory->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
?>

<?php
require_once 'admin_layout.php';

$actions = '<button type="button" class="btn btn-secondary" id="exportProductsBtn"><i class="fas fa-file-export" aria-hidden="true"></i> Export CSV</button>'
    . '<button type="button" class="btn btn-primary" id="addProductBtn"><i class="fas fa-plus" aria-hidden="true"></i> Add product</button>';
admin_layout_start([
    'page' => 'products',
    'title' => 'Products',
    'subtitle' => $total_products . ' ' . ($total_products === 1 ? 'product' : 'products') . ' in the catalog',
    'actions' => $actions,
]);
?>

<div class="toolbar">
    <div class="search-field">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="text" id="productSearch" placeholder="Filter by product name" aria-label="Filter products by name" autocomplete="off">
    </div>
    <label class="sr-only" for="categoryFilter">Category</label>
    <select id="categoryFilter">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
        <?php endforeach; ?>
    </select>
    <span class="toolbar-spacer"></span>
    <span class="cell-sub" aria-live="polite">Showing <strong id="productCount"><?php echo count($products); ?></strong> on this page</span>
</div>

<?php if (empty($products)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-box-open" aria-hidden="true"></i>
            <h3>No products yet</h3>
            <p>Add your first product or service to start taking orders.</p>
        </div>
    </div>
<?php else: ?>
<div class="product-grid">
    <?php foreach ($products as $product):
        $imgs = $product['image_urls'];
        $first = $imgs ? reset($imgs) : null;
        $opts = [];
        foreach (['paper' => 'Paper', 'size' => 'Size', 'finish' => 'Finish', 'layout' => 'Layout', 'binding' => 'Binding', 'gsm' => 'GSM'] as $key => $label) {
            if (!empty($product['has_' . $key . '_option'])) $opts[] = $label;
        }
    ?>
        <article class="product-card" data-name="<?php echo htmlspecialchars(mb_strtolower($product['product_name'])); ?>" data-category="<?php echo htmlspecialchars($product['category']); ?>">
            <div class="product-media">
                <?php if ($first): ?>
                    <img src="<?php echo htmlspecialchars($first); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" loading="lazy">
                    <span class="img-count"><i class="fas fa-images" aria-hidden="true"></i> <?php echo count($imgs); ?></span>
                <?php else: ?>
                    <div class="product-noimg"><i class="fas fa-image" aria-hidden="true"></i><span>No image yet</span></div>
                <?php endif; ?>
            </div>
            <div class="product-body">
                <div class="product-top">
                    <div>
                        <h2 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h2>
                        <span class="product-id">ID <?php echo (int) $product['id']; ?></span>
                    </div>
                    <span class="product-price">&#8369;<?php echo number_format($product['price'], 2); ?></span>
                </div>
                <div><span class="category-badge"><?php echo htmlspecialchars($product['category']); ?></span></div>
                <div class="chip-row customization-badges">
                    <?php foreach ($opts as $o): ?><span class="customization-badge active"><?php echo $o; ?></span><?php endforeach; ?>
                    <?php if (!$opts): ?><span class="cell-sub">No customization options</span><?php endif; ?>
                </div>
                <div class="chip-row">
                    <?php if (!$first): ?>
                        <span class="customization-badge is-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> No product image</span>
                    <?php endif; ?>
                    <?php if ($product['category'] === 'Other Services'): ?>
                        <?php foreach ([['Base front', $product['base_url']], ['Base back', $product['base_back_url']]] as [$label, $url]): ?>
                            <?php if (!$url): ?><span class="customization-badge is-warn"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> <?php echo $label; ?> missing</span><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php
                $thumbs = array_slice($imgs, 1, null, true);
                if ($thumbs || ($product['category'] === 'Other Services' && ($product['base_url'] || $product['base_back_url']))): ?>
                    <div class="product-thumbs">
                        <?php foreach ($thumbs as $idx => $url): ?>
                            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" title="Product image <?php echo $idx + 1; ?>">
                                <img class="product-thumb" src="<?php echo htmlspecialchars($url); ?>" alt="Product image <?php echo $idx + 1; ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                        <?php if ($product['category'] === 'Other Services'): foreach ([['Base front', $product['base_url']], ['Base back', $product['base_back_url']]] as [$label, $url]): ?>
                            <?php if ($url): ?>
                                <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener" title="<?php echo $label; ?> template">
                                    <img class="product-thumb product-thumb-base" src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo $label; ?> template" loading="lazy">
                                </a>
                            <?php endif; ?>
                        <?php endforeach; endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="product-foot">
                <button type="button" class="btn btn-secondary btn-sm" data-product-action="edit" data-id="<?php echo (int) $product['id']; ?>"><i class="fas fa-pen" aria-hidden="true"></i> Edit</button>
                <button type="button" class="btn btn-secondary btn-sm" data-product-action="options" data-id="<?php echo (int) $product['id']; ?>"><i class="fas fa-sliders" aria-hidden="true"></i> Options</button>
                <span class="spacer"></span>
                <button type="button" class="btn btn-ghost-danger btn-sm btn-icon" data-product-action="delete" data-id="<?php echo (int) $product['id']; ?>"
                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>" aria-label="Delete <?php echo htmlspecialchars($product['product_name']); ?>" title="Delete">
                    <i class="fas fa-trash" aria-hidden="true"></i>
                </button>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card" id="productsEmpty" hidden>
    <div class="empty-state">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <h3>No products match</h3>
        <p>Filters apply to the products on this page. Clear them or check the other pages.</p>
        <button type="button" class="btn btn-secondary btn-sm" id="clearProductFilters">Clear filters</button>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <span class="pagination-summary">
            Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_products); ?>
            of <?php echo $total_products; ?> products
        </span>
        <div class="pagination-controls">
            <a href="?page=<?php echo max(1, $page - 1); ?>"
               class="btn <?php echo $page <= 1 ? 'btn-disabled' : ''; ?>"
               <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                <i class="fas fa-chevron-left" aria-hidden="true"></i> Prev
            </a>
            <?php
            // A small window of page links around the current page, plus first/last.
            $window = 2;
            for ($p = 1; $p <= $total_pages; $p++) {
                $show = $p === 1 || $p === $total_pages || abs($p - $page) <= $window;
                if (!$show) {
                    if ($p === 2 || $p === $total_pages - 1) {
                        echo '<span class="pagination-ellipsis">&hellip;</span>';
                    }
                    continue;
                }
                $active = $p === $page ? 'active' : '';
                echo '<a href="?page=' . $p . '" class="btn ' . $active . '"' . ($p === $page ? ' aria-current="page"' : '') . '>' . $p . '</a>';
            }
            ?>
            <a href="?page=<?php echo min($total_pages, $page + 1); ?>"
               class="btn <?php echo $page >= $total_pages ? 'btn-disabled' : ''; ?>"
               <?php echo $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                Next <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php admin_layout_end(); ?>

<!-- Add / edit product -->
<div id="productModal" class="modal" aria-labelledby="modalTitle">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2><i class="fas fa-box" aria-hidden="true"></i> <span id="modalTitle">Add product</span></h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close product form">&times;</button>
        </div>
        <form id="productForm" method="post" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" id="formAction" value="add_product">
            <input type="hidden" name="product_id" id="productId">

            <div class="modal-body">
                <section class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-circle-info" aria-hidden="true"></i> Basic information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_name">Product name</label>
                            <input type="text" id="product_name" name="product_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-tag" aria-hidden="true"></i> Pricing</h3>
                    <div class="form-group">
                        <label for="price">Base price</label>
                        <div class="price-input-wrap">
                            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        <span class="form-hint">Customization options can change the final price for the customer.</span>
                    </div>
                </section>

                <section class="form-section" id="productOptionsSection" hidden>
                    <h3 class="form-section-title"><i class="fas fa-sliders" aria-hidden="true"></i> Product options</h3>
                    <p class="form-hint" style="margin-bottom:8px;">Paper, size, finish, layout, binding and GSM choices are saved separately from this form.</p>
                    <button type="button" class="btn btn-secondary btn-sm" data-product-action="options"><i class="fas fa-sliders" aria-hidden="true"></i> Configure options</button>
                </section>

                <section class="form-section image-upload-section">
                    <h3 class="form-section-title"><i class="fas fa-images" aria-hidden="true"></i> Images <small>Up to 5 per product</small></h3>
                    <label class="file-input-label" for="productImagesInput">
                        <i class="fas fa-upload" aria-hidden="true"></i> Choose product images
                        <input type="file" id="productImagesInput" class="file-input" name="product_images[]" accept="image/*" multiple>
                    </label>
                    <span id="productImagesFile" class="file-name">No files chosen</span>
                    <span class="form-hint">JPG, PNG or GIF, up to 5 MB each. New images fill the first free slots; existing images are kept.</span>
                    <div class="image-preview-container" id="productImagesPreview" hidden></div>

                    <div class="current-images-section" id="currentImagesSection" hidden>
                        <div id="currentImagesList"></div>
                    </div>
                </section>

                <section class="form-section image-upload-section" id="baseTemplatesSection" hidden>
                    <h3 class="form-section-title"><i class="fas fa-vector-square" aria-hidden="true"></i> Base templates <small>Other Services only</small></h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="frontBaseInput">Front base template</label>
                            <label class="file-input-label" for="frontBaseInput">
                                <i class="fas fa-upload" aria-hidden="true"></i> Choose front
                                <input type="file" id="frontBaseInput" class="file-input" name="base_image" accept="image/*">
                            </label>
                            <span id="frontBaseFile" class="file-name">No file chosen</span>
                            <div class="image-preview-container" id="frontBasePreview" hidden>
                                <div class="image-preview"><img id="frontBasePreviewImg" src="" alt="Front base preview"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="backBaseInput">Back base template <small>(optional)</small></label>
                            <label class="file-input-label" for="backBaseInput">
                                <i class="fas fa-upload" aria-hidden="true"></i> Choose back
                                <input type="file" id="backBaseInput" class="file-input" name="base_back_image" accept="image/*">
                            </label>
                            <span id="backBaseFile" class="file-name">No file chosen</span>
                            <div class="image-preview-container" id="backBasePreview" hidden>
                                <div class="image-preview"><img id="backBasePreviewImg" src="" alt="Back base preview"></div>
                            </div>
                        </div>
                    </div>
                    <div class="current-images-section" id="currentBaseSection" hidden></div>
                </section>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveProductBtn">
                    <span id="saveBtnText">Save product</span>
                    <span id="saveBtnLoading" class="spinner" hidden role="status" aria-label="Saving"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Customization options -->
<div id="customizationModal" class="modal" aria-labelledby="customizationTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="customizationTitle"><i class="fas fa-sliders" aria-hidden="true"></i> Customization options</h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close options">&times;</button>
        </div>
        <form id="customizationForm" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_customization">
            <input type="hidden" name="product_id" id="customizationProductId">

            <div class="modal-body">
                <p class="form-hint" style="margin-bottom:10px;">Choose what customers can customize on this product.</p>
                <div class="checkbox-group">
                    <?php foreach (['paper' => 'Paper', 'size' => 'Size', 'finish' => 'Finish', 'layout' => 'Layout', 'binding' => 'Binding', 'gsm' => 'GSM'] as $key => $label): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_<?php echo $key; ?>_option" name="has_<?php echo $key; ?>_option" value="1">
                            <label for="has_<?php echo $key; ?>_option"><?php echo $label; ?> options</label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save options</button>
            </div>
        </form>
    </div>
</div>

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