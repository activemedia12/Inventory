<?php
session_start();
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

require_once '../../config/db.php';
require_once '../../config/security.php';

// Fetch user info (personal or company)
$userQuery = "SELECT 
                u.id,
                pc.first_name, pc.last_name,
                cc.company_name
              FROM users u
              LEFT JOIN personal_customers pc ON u.id = pc.user_id
              LEFT JOIN company_customers cc ON u.id = cc.user_id
              WHERE u.id = ?
              LIMIT 1";

$userStmt = $inventory->prepare($userQuery);
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user_data = $userResult->fetch_assoc();

// Set display name for session if not set
if (!empty($user_data['first_name'])) {
    $_SESSION['username'] = $user_data['first_name'];
} elseif (!empty($user_data['company_name'])) {
    $_SESSION['username'] = $user_data['company_name'];
} else {
    $_SESSION['username'] = 'User';
}


// Flash message from add_to_cart.php. It is rendered in the page body (see below)
// so nothing is printed before the doctype and the page stays in standards mode.
$toast = null;
if (isset($_GET['success'])) {
    $success_messages = [
        'added'   => 'Product added to cart successfully!',
        'updated' => 'Cart quantity updated successfully!',
    ];
    if (isset($success_messages[$_GET['success']])) {
        $toast = ['type' => 'success', 'message' => $success_messages[$_GET['success']], 'ms' => 3000];
    }
}

if (isset($_GET['error'])) {
    $error_messages = [
        'invalid_product' => 'Invalid product!',
        'cart_error'      => 'Error creating cart!',
        'update_error'    => 'Error updating cart!',
        'add_error'       => 'Error adding to cart!',
        'csrf'            => 'Your session expired. Please try again.',
        'upload_error'    => 'One of your files could not be uploaded (unsupported type or too large).',
    ];
    if (isset($error_messages[$_GET['error']])) {
        $toast = ['type' => 'error', 'message' => $error_messages[$_GET['error']], 'ms' => 5000];
    }
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id === 0) {
    header("Location: ../../website/main.php");
    exit;
}

// Check if product has customization options
$customization_query = "SELECT * FROM product_customization WHERE product_id = ?";
$customization_stmt = $inventory->prepare($customization_query);
$customization_stmt->bind_param("i", $product_id);
$customization_stmt->execute();
$customization_result = $customization_stmt->get_result();
$customization = $customization_result->fetch_assoc();

// Fetch available options for this product
$paper_options = [];
$finish_options = [];
$binding_options = [];
$layout_options = [];

if ($customization) {
    // Paper options
    if ($customization['has_paper_option']) {
        $paper_query = "SELECT po.* FROM paper_options po 
                       JOIN product_paper_options ppo ON po.id = ppo.paper_option_id 
                       WHERE ppo.product_id = ?";
        $paper_stmt = $inventory->prepare($paper_query);
        $paper_stmt->bind_param("i", $product_id);
        $paper_stmt->execute();
        $paper_result = $paper_stmt->get_result();
        while ($paper = $paper_result->fetch_assoc()) {
            $paper_options[] = $paper;
        }
    }

    // Repeat similar queries for finish, binding, and layout options
    // Finish options
    if ($customization['has_finish_option']) {
        $finish_query = "SELECT fo.* FROM finish_options fo 
                        JOIN product_finish_options pfo ON fo.id = pfo.finish_option_id 
                        WHERE pfo.product_id = ?";
        $finish_stmt = $inventory->prepare($finish_query);
        $finish_stmt->bind_param("i", $product_id);
        $finish_stmt->execute();
        $finish_result = $finish_stmt->get_result();
        while ($finish = $finish_result->fetch_assoc()) {
            $finish_options[] = $finish;
        }
    }

    // Binding options
    if ($customization['has_binding_option']) {
        $binding_query = "SELECT bo.* FROM binding_options bo 
                         JOIN product_binding_options pbo ON bo.id = pbo.binding_option_id 
                         WHERE pbo.product_id = ?";
        $binding_stmt = $inventory->prepare($binding_query);
        $binding_stmt->bind_param("i", $product_id);
        $binding_stmt->execute();
        $binding_result = $binding_stmt->get_result();
        while ($binding = $binding_result->fetch_assoc()) {
            $binding_options[] = $binding;
        }
    }

    // Layout options
    if ($customization['has_layout_option']) {
        $layout_query = "SELECT lo.* FROM layout_options lo 
                        JOIN product_layout_options plo ON lo.id = plo.layout_option_id 
                        WHERE plo.product_id = ?";
        $layout_stmt = $inventory->prepare($layout_query);
        $layout_stmt->bind_param("i", $product_id);
        $layout_stmt->execute();
        $layout_result = $layout_stmt->get_result();
        while ($layout = $layout_result->fetch_assoc()) {
            $layout_options[] = $layout;
        }
    }
}


// Fetch product details
$query = "SELECT id, product_name, category, price FROM products_offered WHERE id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: ../../website/main.php");
    exit;
}

// Fetch dynamic size and color options for Other Services (IDs 18-21)
$size_options = [];
$color_options = [];
$label = '';
if ($customization && in_array($product_id, [18, 19, 20, 21])) {
    // Size options
    switch ($product_id) {
        case 18: // T-Shirts
            $size_query = "SELECT ts.* FROM tshirt_sizes ts 
                          JOIN product_tshirt_sizes pts ON ts.id = pts.tshirt_size_id 
                          WHERE pts.product_id = ?";
            break;
        case 19: // Tote Bag
            $size_query = "SELECT tos.* FROM totesize_options tos 
                          JOIN product_totesizes ptos ON tos.id = ptos.totesize_id 
                          WHERE ptos.product_id = ?";
            break;
        case 20: // Paper Bag
            $size_query = "SELECT pbs.* FROM paperbag_size_options pbs 
                          JOIN product_paperbag_sizes ppbs ON pbs.id = ppbs.paperbag_size_id 
                          WHERE ppbs.product_id = ?";
            break;
        case 21: // Mug
            $size_query = "SELECT ms.* FROM mug_size_options ms 
                          JOIN product_mug_sizes pms ON ms.id = pms.mug_size_id 
                          WHERE pms.product_id = ?";
            break;
        default:
            $size_query = "";
    }
    if (!empty($size_query)) {
        $size_stmt = $inventory->prepare($size_query);
        $size_stmt->bind_param("i", $product_id);
        $size_stmt->execute();
        $size_result = $size_stmt->get_result();
        while ($size = $size_result->fetch_assoc()) {
            $size_options[] = $size;
        }
    }

    // Color options
    switch ($product_id) {
        case 18: // T-Shirts
            $color_query = "SELECT tc.* FROM tshirt_colors tc 
                           JOIN product_tshirt_colors ptc ON tc.id = ptc.tshirt_color_id 
                           WHERE ptc.product_id = ?";
            $label = "Color:";
            break;
        case 19: // Tote Bag
            $color_query = "SELECT toc.* FROM totecolor_options toc 
                           JOIN product_totecolors ptoc ON toc.id = ptoc.totecolor_id 
                           WHERE ptoc.product_id = ?";
            $label = "Color:";
            break;
        case 21: // Mug
            $color_query = "SELECT mc.* FROM mug_color_options mc 
                           JOIN product_mug_colors pmc ON mc.id = pmc.mug_color_id 
                           WHERE pmc.product_id = ?";
            $label = "Color:";
            break;
        default:
            $color_query = "";
    }
    if (!empty($color_query)) {
        $color_stmt = $inventory->prepare($color_query);
        $color_stmt->bind_param("i", $product_id);
        $color_stmt->execute();
        $color_result = $color_stmt->get_result();
        while ($color = $color_result->fetch_assoc()) {
            $color_options[] = $color;
        }
    }
}

// Check if product is in Other Services category (should show image customization)
$show_image_customization = ($product['category'] === 'Other Services');

// Category -> ink colour + home-page catalog tab (mirrors the catalog on the home page)
$ink_map = [
    'Offset Printing'  => ['ink' => 'black',   'anchor' => 'offset'],
    'Digital Printing' => ['ink' => 'cyan',    'anchor' => 'digital'],
    'RISO Printing'    => ['ink' => 'magenta', 'anchor' => 'riso'],
    'Riso Printing'    => ['ink' => 'magenta', 'anchor' => 'riso'],
    'Other Services'   => ['ink' => 'yellow',  'anchor' => 'other'],
];
$cat_meta = $ink_map[$product['category']] ?? ['ink' => 'black', 'anchor' => 'services'];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    // Where to go back to if something is wrong with the request (this same page)
    $back_url = strtok($_SERVER['REQUEST_URI'], '#');
    $back_url = preg_replace('/([?&])(success|error)=[^&]*&?/', '$1', $back_url);
    $back_url = rtrim($back_url, '?&');
    $back_url .= (strpos($back_url, '?') === false ? '?' : '&');

    if (!csrf_valid()) {
        header("Location: " . $back_url . "error=csrf");
        exit;
    }

    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $design_image = isset($_POST['design_image']) ? $_POST['design_image'] : '';
    $front_design_image = isset($_POST['front_design_image']) ? $_POST['front_design_image'] : '';
    $back_design_image = isset($_POST['back_design_image']) ? $_POST['back_design_image'] : '';
    $upload_type = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'single';
    $layout_option = isset($_POST['layout_option']) ? $_POST['layout_option'] : '';
    $layout_details = isset($_POST['layout_details']) ? $_POST['layout_details'] : '';

    // New: Get size/color options
    $size_option = isset($_POST['size_option']) ? $_POST['size_option'] : '';
    $custom_size = isset($_POST['custom_size']) ? $_POST['custom_size'] : '';
    $color_option = isset($_POST['color_option']) ? $_POST['color_option'] : '';
    $custom_color = isset($_POST['custom_color']) ? $_POST['custom_color'] : '';

    // Handle user layout file uploads (validated: allowed types only, random safe names)
    $user_layout_files = [];
    $layout_upload_failed = false;
    if (isset($_FILES['user_layout_upload']) && is_array($_FILES['user_layout_upload']['name'])) {
        $layout_dir = '../../assets/uploads/user_layouts/' . (int) $_SESSION['user_id'] . '/';
        foreach (array_keys($_FILES['user_layout_upload']['name']) as $key) {
            if ($_FILES['user_layout_upload']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $one_file = [
                'name'     => $_FILES['user_layout_upload']['name'][$key],
                'type'     => $_FILES['user_layout_upload']['type'][$key],
                'tmp_name' => $_FILES['user_layout_upload']['tmp_name'][$key],
                'error'    => $_FILES['user_layout_upload']['error'][$key],
                'size'     => $_FILES['user_layout_upload']['size'][$key],
            ];
            $saved = save_uploaded_file($one_file, $layout_dir, upload_allowed_design(), 'layout_', UPLOAD_MAX_DESIGN_BYTES, true);
            if ($saved['ok']) {
                $user_layout_files[] = $layout_dir . $saved['filename'];
            } else {
                $layout_upload_failed = true;
            }
        }
    }
    if ($layout_upload_failed) {
        header("Location: " . $back_url . "error=upload_error");
        exit;
    }
    $user_layout_files_json = !empty($user_layout_files) ? json_encode($user_layout_files) : '';

    // Hand over to add_to_cart.php in this same request (POST + CSRF token, nothing in the URL)
    $_POST['product_id']        = $product_id;
    $_POST['quantity']          = $quantity;
    $_POST['user_layout_files'] = $user_layout_files_json;
    require __DIR__ . '/add_to_cart.php';
    exit;
}

// Get cart count for navigation
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $count_query = "SELECT SUM(ci.quantity) as total_items 
                  FROM cart_items ci 
                  JOIN carts c ON ci.cart_id = c.cart_id 
                  WHERE c.user_id = ?";
    $count_stmt = $inventory->prepare($count_query);
    $count_stmt->bind_param("i", $_SESSION['user_id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $cart_count = $count_row['total_items'] ? $count_row['total_items'] : 0;
}

// Get base image paths instead of product images
$base_image_path = "../../assets/images/base/base-" . $product['id'] . ".jpg";
$base_image_url = file_exists($base_image_path) ? $base_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Base+Image";
$back_base_image_path = "../../assets/images/base/base-" . $product['id'] . "-1.jpg";
$back_base_image_url = file_exists($back_base_image_path) ? $back_base_image_path : "";

// Get product images for gallery display
$product_image_path = "../../assets/images/services/service-" . $product['id'] . ".jpg";
$product_image_url = file_exists($product_image_path) ? $product_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Product+Image";
$product_back_image_path = "../../assets/images/services/service-" . $product['id'] . "-1.jpg";
$product_back_image_url = file_exists($product_back_image_path) ? $product_back_image_path : "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/service_detail.css">
    <style>
        /* --- Design studio: multi-image thumbs, tool groups, rotate/opacity controls ---
           Added inline so these work regardless of what's in service_detail.css. */
        .design-thumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .design-thumb {
            position: relative;
            width: 64px;
            height: 64px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.12);
            background: #f4f4f5;
            flex: 0 0 auto;
            cursor: pointer;
        }
        .design-thumb.is-selected {
            border: 2px solid #4f46e5;
        }
        /* Each uploaded image gets its own draggable box on the canvas; the
           one currently selected for the position/size/rotate/opacity tools
           is outlined and drawn above the others. */
        .draggable-design.is-selected {
            outline: 2px dashed #4f46e5;
            outline-offset: 2px;
        }
        .design-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .design-thumb__remove {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 18px;
            height: 18px;
            line-height: 18px;
            border: none;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.65);
            color: #fff;
            font-size: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .design-thumb__remove:hover { background: #d9463c; }
        .add-more-tile {
            width: 64px;
            height: 64px;
            border-radius: 8px;
            border: 1px dashed rgba(0, 0, 0, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            flex: 0 0 auto;
            background: transparent;
        }
        .add-more-tile:hover { border-color: #999; color: #333; }

        .positioning-tools { display: flex; flex-direction: column; gap: 10px; }
        .tool-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .tool-group__buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .tool-group__label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            min-width: 62px;
        }
        .tool-btn.icon-only { padding: 6px 10px; }
        .range-control {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1 1 160px;
            min-width: 140px;
        }
        .range-control input[type="range"] { flex: 1; accent-color: #d9463c; }
        .range-readout {
            font-size: 12px;
            color: #555;
            min-width: 34px;
            text-align: right;
        }

        .rotate-handle {
            position: absolute;
            left: 50%;
            top: -22px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #d9463c;
            transform: translateX(-50%);
            cursor: alias;
        }
        .rotate-handle::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 100%;
            width: 1px;
            height: 14px;
            background: #d9463c;
            transform: translateX(-50%);
        }
    </style>
</head>
<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="../../website/main.php" class="active"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="../../website/ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="../../website/about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="../../website/contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="#" class="chat-icon" id="chatButton">
                    <span class="side-nav-icon">
                        <i class="fas fa-comments"></i>
                        <span class="chat-count" id="chatCount">0</span>
                    </span>
                    <span class="side-nav-label">Chat</span>
                </a>
            </li>
            <li>
                <a href="../../website/view_cart.php" class="cart-icon">
                    <span class="side-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </span>
                    <span class="side-nav-label">Cart</span>
                </a>
            </li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="profile.php" class="user-profile">
                    <i class="fas fa-user"></i>
                    <span class="side-nav-label user-name">
                        <?php
                        if (!empty($user_data['first_name'])) {
                            echo htmlspecialchars($user_data['first_name']);
                        } elseif (!empty($user_data['company_name'])) {
                            echo htmlspecialchars($user_data['company_name']);
                        } else {
                            echo 'User';
                        }
                        ?>
                    </span>
                </a>
            </li>
            <li>
                <a href="../../accounts/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="side-nav-label">Log Out</span>
                </a>
            </li>
        </ul>
    </nav>

    <?php if ($toast): ?>
        <div class="pd-toast is-<?php echo $toast['type']; ?>" id="pdToast" role="status" data-ms="<?php echo (int) $toast['ms']; ?>">
            <i class="fas <?php echo $toast['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <span><?php echo htmlspecialchars($toast['message']); ?></span>
        </div>
    <?php endif; ?>

    <?php
    // Collect up to 5 product images: service-ID.jpg, service-ID-1.jpg ...
$product_images = [];
for ($i = 0; $i < 5; $i++) {
    $suffix = $i > 0 ? '-' . $i : '';
    $image_path = "../../assets/images/services/service-" . $product['id'] . $suffix . ".jpg";

    if (file_exists($image_path)) {
        $product_images[] = [
            'path' => $image_path,
            'alt' => $product['product_name'] . ($i > 0 ? ' - View ' . ($i + 1) : ''),
            'index' => $i
        ];
    }
}

// If no images found, use placeholder
if (empty($product_images)) {
    $product_images[] = [
        'path' => "https://via.placeholder.com/500x500/2c5aa0/ffffff?text=Product+Image",
        'alt' => $product['product_name'],
        'index' => 0
    ];
}

$main_image = $product_images[0];
?>

    <!-- Product detail -->
    <main class="pd-page" data-ink="<?php echo $cat_meta['ink']; ?>">
        <div class="pd-shell">
            <nav class="pd-crumbs" aria-label="Breadcrumb">
                <a href="../../website/main.php">Home</a>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <a href="../../website/main.php#<?php echo $cat_meta['anchor']; ?>"><?php echo htmlspecialchars($product['category']); ?></a>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <span aria-current="page"><?php echo htmlspecialchars($product['product_name']); ?></span>
            </nav>

            <form method="post" id="cartForm" action="" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_to_cart" value="1">

                <div class="pd-top">
                    <section class="pd-gallery" aria-label="Product images">
                    <div class="pd-proof">
                        <div class="pd-proof__sheet">
                            <img src="<?php echo htmlspecialchars($main_image['path']); ?>" alt="<?php echo htmlspecialchars($main_image['alt']); ?>" class="main-image" id="mainImage">
                        </div>
                    </div>

                    <?php if (count($product_images) > 1): ?>
                        <div class="pd-thumbs">
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?php echo htmlspecialchars($image['path']); ?>"
                                    alt="Thumbnail <?php echo $index + 1; ?>"
                                    class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                    role="button" tabindex="0"
                                    onclick="changeImage(this, <?php echo $index; ?>)"
                                    onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }"
                                    data-image-index="<?php echo $index; ?>">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                    <div class="pd-config">
                    <header class="pd-head">
                        <span class="pd-cat"><span class="reg-mark"></span><?php echo htmlspecialchars($product['category']); ?></span>
                        <h1 class="pd-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                        <p class="pd-price"><span class="pd-price__amt">₱<?php echo number_format($product['price'], 2); ?></span></p>
                    </header>

                    <?php if ($customization && in_array($product_id, [18, 19, 20, 21])): ?>
                        <section class="pd-panel customization-section">
                            <h2 class="pd-panel__title required-field"><span class="pd-ico"><i class="fas fa-ruler-combined"></i></span>Size</h2>
                            <?php if (!empty($size_options)): ?>
                                <div class="option-group pd-field">
                                    <div class="button-options">
                                        <?php foreach ($size_options as $size):
                                            $display_name = isset($size['dimensions']) ? $size['size_name'] . ' (' . $size['dimensions'] . ')' : $size['size_name'];
                                        ?>
                                            <button type="button"
                                                    class="option-button <?php echo $size['is_custom'] ? 'custom-option' : ''; ?>"
                                                    data-value="<?php echo $size['id']; ?>"
                                                    data-custom="<?php echo $size['is_custom']; ?>"
                                                    onclick="selectOption(this, 'size')"><?php echo htmlspecialchars($display_name); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="size_option" id="sizeOption" value="">
                                    <div id="customSizeContainer" class="pd-custom" style="display: none;">
                                        <label class="pd-label" for="customSizeInput">Custom size</label>
                                        <input type="text" class="pd-input" id="customSizeInput" name="custom_size" placeholder="Please specify your custom size">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="pd-panel customization-section">
                            <h2 class="pd-panel__title required-field"><span class="pd-ico"><i class="fas fa-palette"></i></span>Color</h2>
                            <?php if (!empty($color_options)): ?>
                                <div class="option-group pd-field">
                                    <div class="button-options">
                                        <?php foreach ($color_options as $color): ?>
                                            <button type="button"
                                                    class="option-button <?php echo $color['is_custom'] ? 'custom-option' : ''; ?>"
                                                    data-value="<?php echo $color['id']; ?>"
                                                    data-custom="<?php echo $color['is_custom']; ?>"
                                                    onclick="selectOption(this, 'color')"><?php echo htmlspecialchars($color['color_name']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="color_option" id="colorOption" value="">
                                    <div id="customColorContainer" class="pd-custom" style="display: none;">
                                        <label class="pd-label" for="customColorInput">Custom color</label>
                                        <input type="text" class="pd-input" id="customColorInput" name="custom_color" placeholder="Please specify your custom color">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($product_id == 20): ?>
                                <div class="option-group pd-field">
                                    <div class="button-options">
                                        <button type="button" class="option-button selected" data-value="brown" onclick="selectOption(this, 'color')">Brown (standard)</button>
                                    </div>
                                    <input type="hidden" name="color_option" value="brown">
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php
                    if (
                        $customization &&
                        !in_array($product_id, [18, 19, 20, 21]) &&
                        in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])
                    ):
                    ?>
                        <section class="pd-panel customization-section">
                            <h2 class="pd-panel__title"><span class="pd-ico"><i class="fas fa-cog"></i></span>Printing options</h2>

                            <?php if ($customization['has_paper_option'] && !empty($paper_options)): ?>
                                <div class="option-group pd-field">
                                    <span class="pd-label required-field">Paper type</span>
                                    <div class="button-options">
                                        <?php foreach ($paper_options as $paper): ?>
                                            <button type="button" class="option-button" data-value="<?php echo $paper['id']; ?>" onclick="selectOption(this, 'paper')"><?php echo htmlspecialchars($paper['option_name']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="paper_option" id="paperOption" value="">
                                </div>
                            <?php endif; ?>

                            <?php
                            // Only show for printing categories, not for Other Services (IDs 18-21)
                            if (
                                $customization['has_size_option'] &&
                                !in_array($product_id, [18, 19, 20, 21]) &&
                                in_array($product['category'], ['Riso Printing', 'Offset Printing', 'Digital Printing'])
                            ): ?>
                                <div class="option-group pd-field">
                                    <label class="pd-label required-field" for="printSizeInput">Size (in inches)</label>
                                    <input type="text" class="pd-input" id="printSizeInput" name="size_option" placeholder="e.g., 8.5 x 11">
                                </div>
                            <?php endif; ?>

                            <?php if ($customization['has_finish_option'] && !empty($finish_options)): ?>
                                <div class="option-group pd-field">
                                    <span class="pd-label required-field">Finish</span>
                                    <div class="button-options">
                                        <?php foreach ($finish_options as $finish): ?>
                                            <button type="button" class="option-button" data-value="<?php echo $finish['id']; ?>" onclick="selectOption(this, 'finish')"><?php echo htmlspecialchars($finish['option_name']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="finish_option" id="finishOption" value="">
                                </div>
                            <?php endif; ?>

                            <?php if ($customization['has_layout_option'] && !empty($layout_options)): ?>
                                <div class="option-group pd-field">
                                    <span class="pd-label required-field">Layout</span>
                                    <div class="button-options">
                                        <?php foreach ($layout_options as $layout): ?>
                                            <button type="button" class="option-button" data-value="<?php echo $layout['id']; ?>" onclick="selectLayoutOption(this)"><?php echo htmlspecialchars($layout['option_name']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="layout_option" id="layoutOption" value="">

                                    <div id="layoutInputContainer" class="pd-custom" style="display: none;">
                                        <!-- Content will be populated by JavaScript based on selection -->
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($customization['has_binding_option'] && !empty($binding_options)): ?>
                                <div class="option-group pd-field">
                                    <span class="pd-label required-field">Binding</span>
                                    <div class="button-options">
                                        <?php foreach ($binding_options as $binding): ?>
                                            <button type="button" class="option-button" data-value="<?php echo $binding['id']; ?>" onclick="selectOption(this, 'binding')"><?php echo htmlspecialchars($binding['option_name']); ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="binding_option" id="bindingOption" value="">
                                </div>
                            <?php endif; ?>

                            <?php if ($customization['has_gsm_option']): ?>
                                <div class="option-group pd-field">
                                    <label class="pd-label required-field" for="gsmInput">Paper weight (GSM)</label>
                                    <input type="number" class="pd-input" id="gsmInput" name="gsm_option" placeholder="e.g., 120" min="0">
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                    </div>
                </div>

                <?php if ($show_image_customization): ?>
                <section class="pd-panel pd-studio customization-section" aria-labelledby="studioTitle">
                    <h2 class="pd-panel__title" id="studioTitle"><span class="pd-ico"><i class="fas fa-paint-brush"></i></span>Customize your product</h2>

                    <div class="pd-studio__grid">
                        <div class="pd-studio__col">
                            <div class="design-areas">
                                <!-- Front design -->
                                <div class="design-area front-design" id="frontDesignArea">
                                    <h3 class="design-area-title">
                                        <i class="fas fa-tshirt"></i> Front design
                                        <span class="design-status" id="frontDesignStatus">Not uploaded</span>
                                    </h3>

                                    <div class="design-upload-container">
                                        <label class="upload-zone" id="frontUploadZone">
                                            <input type="file" id="frontDesignUpload" name="front_design_upload" accept="image/jpeg,image/png,.jpg,.jpeg,.png" multiple hidden
                                                onchange="handleDesignUpload(this, 'front')">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span class="upload-text">Upload front design</span>
                                            <small class="upload-hint">JPG, PNG, GIF (max 5MB each, up to 6 images)</small>
                                        </label>

                                        <div class="design-preview" id="frontDesignPreview">
                                            <img src="" alt="Front design preview" id="frontPreviewImage" style="display:none;">
                                            <div class="design-thumbs" id="frontDesignThumbs"></div>
                                            <div class="design-actions">
                                                <button type="button" class="btn-remove-design" onclick="removeDesign('front')">
                                                    <i class="fas fa-trash"></i> Remove all
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Back design -->
                                <div class="design-area back-design" id="backDesignArea"
                                    style="<?php echo empty($back_base_image_url) ? 'display: none;' : ''; ?>">
                                    <h3 class="design-area-title">
                                        <i class="fas fa-tshirt"></i> Back design
                                        <span class="design-status" id="backDesignStatus">Not uploaded</span>
                                    </h3>

                                    <div class="design-upload-container">
                                        <label class="upload-zone" id="backUploadZone">
                                            <input type="file" id="backDesignUpload" name="back_design_upload" accept="image/jpeg,image/png,.jpg,.jpeg,.png" multiple hidden
                                                onchange="handleDesignUpload(this, 'back')">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span class="upload-text">Upload back design</span>
                                            <small class="upload-hint">JPG, PNG, GIF (max 5MB each, up to 6 images)</small>
                                        </label>

                                        <div class="design-preview" id="backDesignPreview">
                                            <img src="" alt="Back design preview" id="backPreviewImage" style="display:none;">
                                            <div class="design-thumbs" id="backDesignThumbs"></div>
                                            <div class="design-actions">
                                                <button type="button" class="btn-remove-design" onclick="removeDesign('back')">
                                                    <i class="fas fa-trash"></i> Remove all
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Auto-determined design type indicator -->
                            <div class="design-type-indicator" id="designTypeIndicator">
                                <i class="fas fa-info-circle"></i>
                                <span id="designTypeText">Upload designs to see customization type</span>
                            </div>
                        </div>

                        <div class="pd-studio__col positioning-section">
                        <h3 class="pd-sub"><i class="fas fa-arrows-alt"></i> Position your design</h3>

                        <div class="view-selector">
                            <button type="button" class="view-btn active" id="frontViewBtn" onclick="switchView('front')">
                                <i class="fas fa-tshirt"></i> Front view
                            </button>
                            <?php if (!empty($back_base_image_url)): ?>
                                <button type="button" class="view-btn" id="backViewBtn" onclick="switchView('back')">
                                    <i class="fas fa-tshirt"></i> Back view
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="positioning-tools">
                            <div class="tool-group">
                                <span class="tool-group__label">Position</span>
                                <div class="tool-group__buttons">
                                    <button type="button" class="tool-btn" onclick="enableDragging()" id="dragBtn">
                                        <i class="fas fa-arrows-alt"></i> Move design
                                    </button>
                                    <button type="button" class="tool-btn" onclick="toggleBoundary()" id="boundaryBtn">
                                        <i class="fas fa-border-all"></i> Show boundaries
                                    </button>
                                    <button type="button" class="tool-btn" onclick="resetDesignPosition()">
                                        <i class="fas fa-redo"></i> Reset
                                    </button>
                                </div>
                            </div>

                            <div class="tool-group">
                                <span class="tool-group__label">Size</span>
                                <div class="tool-group__buttons">
                                    <button type="button" class="tool-btn icon-only" onclick="resizeDesign(1.1)" title="Enlarge">
                                        <i class="fas fa-search-plus"></i> Enlarge
                                    </button>
                                    <button type="button" class="tool-btn icon-only" onclick="resizeDesign(0.9)" title="Shrink">
                                        <i class="fas fa-search-minus"></i> Shrink
                                    </button>
                                </div>
                            </div>

                            <div class="tool-group">
                                <span class="tool-group__label">Rotate</span>
                                <div class="tool-group__buttons">
                                    <button type="button" class="tool-btn icon-only" onclick="rotateDesignBy(-15)" title="Rotate left 15°">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button type="button" class="tool-btn icon-only" onclick="rotateDesignBy(15)" title="Rotate right 15°">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                </div>
                                <div class="range-control">
                                    <input type="range" id="rotateSlider" min="0" max="359" step="1" value="0"
                                        oninput="setDesignRotation(this.value)">
                                    <span class="range-readout" id="rotateReadout">0°</span>
                                </div>
                            </div>

                            <div class="tool-group">
                                <span class="tool-group__label">Opacity</span>
                                <div class="range-control">
                                    <input type="range" id="opacitySlider" min="10" max="100" step="1" value="100"
                                        oninput="setDesignOpacity(this.value)">
                                    <span class="range-readout" id="opacityReadout">100%</span>
                                </div>
                            </div>
                        </div>

                        <div class="pd-canvas">
                            <div class="positioning-container">
                                <div class="product-base-image">
                                    <img src="<?php echo $base_image_url; ?>" alt="Product base" id="baseImage">
                                    <div id="designOverlay" class="design-overlay"></div>
                                    <div id="designBoundary" class="design-boundary"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <div class="pd-studio__done">
                        <p class="pd-note">Happy with the placement? Press Done to preview the mockup and apply your design.</p>
                        <button type="button" class="btn btn-primary pd-btn-done" onclick="generateMockup()">
                            <i class="fas fa-check"></i> Done
                        </button>
                    </div>
                </section>
                <?php endif; ?>

                <div class="pd-orderbar">
                    <div class="pd-orderbar__what">
                        <span class="pd-orderbar__name"><?php echo htmlspecialchars($product['product_name']); ?></span>
                        <span class="pd-orderbar__price">₱<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn" onclick="decreaseQuantity()" aria-label="Decrease quantity"><i class="fas fa-minus"></i></button>
                        <input type="number" name="quantity" class="quantity-input" id="quantity" value="1" min="1" aria-label="Quantity">
                        <button type="button" class="quantity-btn" onclick="increaseQuantity()" aria-label="Increase quantity"><i class="fas fa-plus"></i></button>
                    </div>
                    <button type="submit" class="btn btn-primary" id="cartSubmitBtn">
                        <i class="fas fa-shopping-cart"></i> Add to cart
                    </button>
                </div>

                <input type="hidden" name="design_image" id="designImageInput" value="">
                <input type="hidden" name="front_design_image" id="frontDesignImageInput" value="">
                <input type="hidden" name="back_design_image" id="backDesignImageInput" value="">
                <input type="hidden" name="upload_type" id="uploadTypeInput" value="single">
            </form>
        </div>
    </main>

    <!-- Mockup Popup -->
    <div class="mockup-popup" id="mockupPopup" role="dialog" aria-modal="true" aria-labelledby="mockupTitle">
        <div class="mockup-container">
            <button type="button" class="close-popup" onclick="closeModal()" aria-label="Close preview"><i class="fas fa-times"></i></button>

            <div class="pd-modal__head">
                <h2 id="mockupTitle">Your <?php echo htmlspecialchars($product['product_name']); ?> mockup</h2>
                <p>Preview your custom design</p>
            </div>

            <div class="mockup-images">
                <div class="mockup-image" id="frontMockupContainer">
                    <img src="" alt="Front view" id="mockupFront">
                    <p>Front view</p>
                    <button type="button" class="download-btn" onclick="downloadMockup('mockupFront', 'front-design.png')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <div class="mockup-image" id="backMockupContainer">
                    <img src="" alt="Back view" id="mockupBack">
                    <p>Back view</p>
                    <button type="button" class="download-btn" onclick="downloadMockup('mockupBack', 'back-design.png')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>

            <div class="pd-modal__actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="useDesignBtn" onclick="useThisDesign()">
                    <i class="fas fa-check"></i> Use this design
                </button>
            </div>
        </div>
    </div>

    <!-- Chat Widget -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <button class="chat-back-btn" id="chatBackBtn">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="chat-title" id="chatTitle">Messages</h3>
            <button class="chat-close" id="chatCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-body">
            <!-- Conversations List -->
            <div class="chat-conversations" id="chatConversations">
                <button class="chat-new-btn" id="newChatBtn">
                    <i class="fas fa-plus"></i> New Conversation
                </button>
                <div id="conversationsList"></div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatMessages">
                <div class="messages-list" id="messagesList"></div>
                <div class="chat-input-area" id="chatInputArea">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1"></textarea>
                        <button class="chat-send-btn" id="chatSendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>AMDP</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/profile.php?id=100063881538670"><i class="fab fa-facebook-f"></i></a>
                        <a href=""><i class="fab fa-twitter"></i></a>
                        <a href=""><i class="fab fa-instagram"></i></a>
                        <a href=""><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="../../website/main.php#offset">Offset Printing</a></li>
                        <li><a href="../../website/main.php#digital">Digital Printing</a></li>
                        <li><a href="../../website/main.php#riso">RISO Printing</a></li>
                        <li><a href="../../website/main.php#other">Other Services</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="../../website/about.php">About Us</a></li>
                        <li><a href="../../website/about.php">Our Team</a></li>
                        <li><a href="../../website/about.php">Careers</a></li>
                        <li><a href="../../website/about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="../../website/contact.php">Contact Us</a></li>
                        <li><a href="../../website/contact.php">FAQ</a></li>
                        <li><a href="../../website/contact.php">Shipping Info</a></li>
                        <li><a href="../../website/contact.php">Returns</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i>Fausta Rd Lucero St Mabolo, Malolos, Philippines</li>
                        <li><i class="fas fa-phone"></i> (044) 796-4101</li>
                        <li><i class="fas fa-envelope"></i> activemediaprint@gmail.com</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2025 Active Media Designs & Printing. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="">Privacy Policy</a>
                    <a href="">Terms of Service</a>
                    <a href="">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Page helpers: auto-hide the flash message, keep the design canvas maths current, close the mockup dialog with Escape
        (function () {
            var toast = document.getElementById('pdToast');
            if (toast) {
                var ms = parseInt(toast.getAttribute('data-ms'), 10) || 4000;
                setTimeout(function () {
                    toast.classList.add('is-leaving');
                    setTimeout(function () { toast.remove(); }, 300);
                }, ms);
            }

            // The canvas is fluid: keep the printable-area maths in step with its size
            window.addEventListener('resize', function () {
                if (document.getElementById('baseImage') && document.querySelector('.positioning-container')) {
                    calculateImageBoundary();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                var popup = document.getElementById('mockupPopup');
                if (popup && popup.style.display === 'flex') closeModal();
            });
        })();
    </script>
    <script>
        // Global variables for the new design system
        let currentMockup = null;
        let backMockup = null;
        // True only while currentMockup/backMockup reflect a mockup the user has
        // explicitly approved via "Use This Design" AND nothing has changed since.
        // The actual files are NOT written to disk until Add to Cart is pressed -
        // see submitCartForm(). Any edit to the design (remove/re-upload) flips
        // this back to false so a stale, unapproved mockup can never be saved.
        let designApplied = false;
        let isDraggingEnabled = false;
        // The DOM element of whichever image is currently selected (see
        // selectedImage below) - kept as its own variable because the
        // drag/resize/rotate/opacity code below reads/writes it directly.
        let currentDesign = null;
        let currentView = 'front';
        let currentUploadType = 'none'; // 'front_only', 'back_only', 'both_sides'
        // The individual source images the user has uploaded per side. Each
        // entry is { file, dataUrl, img, position, el }: `position` is that
        // image's OWN {x,y,width,height,rotation,opacity} box, and `el` is the
        // draggable DOM element currently rendering it (only set while its
        // side is the active view). Every image is independently draggable,
        // resizable and rotatable - nothing is flattened together anymore.
        let frontImages = [];
        let backImages = [];
        // { side: 'front'|'back', index } of whichever image the position/
        // size/rotation/opacity tools currently act on.
        let selectedImage = null;
        const MAX_DESIGN_IMAGES_PER_SIDE = 6;
        let isDragging = false;
        let isResizing = false;
        let startX, startY;
        let initialDesignPosition;
        let showBoundary = false;
        let imageBoundary = {
            x: 0,
            y: 0,
            width: 0,
            height: 0
        };
        let frontImageUrl = "<?php echo $base_image_url; ?>";
        let backImageUrl = "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>";

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate the image boundary for the initial product image
            const baseImage = document.getElementById('baseImage');
            baseImage.onload = function() {
                calculateImageBoundary();
            };

            // Initialize upload type
            document.getElementById('uploadTypeInput').value = currentUploadType;

            // Auto-select first option for each button group
            setTimeout(() => {
                autoSelectFirstOptions();
            }, 100);

            // Close popup when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('mockupPopup')) {
                    closeModal();
                }
            });

            // Setup canvas quality
            if (window.HTMLCanvasElement) {
                const originalGetContext = HTMLCanvasElement.prototype.getContext;
                HTMLCanvasElement.prototype.getContext = function() {
                    const context = originalGetContext.apply(this, arguments);
                    if (context && context.imageSmoothingEnabled !== undefined) {
                        context.imageSmoothingEnabled = true;
                        context.imageSmoothingQuality = 'high';
                    }
                    return context;
                };
            }

            // Check for AI-generated design on page load
            checkForAIDesign();
        });

        // Check for AI-generated design on page load
        function checkForAIDesign() {
            const aiDesign = sessionStorage.getItem('aiGeneratedDesign');
            const aiProductId = sessionStorage.getItem('aiDesignProductId');
            const aiPlacement = sessionStorage.getItem('aiDesignPlacement');
            const currentProductId = <?php echo $product_id; ?>;
            const urlParams = new URLSearchParams(window.location.search);
            const aiDesignParam = urlParams.get('ai_design');
            
            console.log('Checking for AI design:', {
                hasDesign: !!aiDesign,
                aiProductId: aiProductId,
                currentProductId: currentProductId,
                aiDesignParam: aiDesignParam
            });
            
            if (aiDesign && aiProductId && aiProductId == currentProductId && aiDesignParam === '1') {
                console.log('Auto-populating AI design for product:', currentProductId);
                autoPopulateAIDesign(aiDesign);
                
                // Clear the session storage
                sessionStorage.removeItem('aiGeneratedDesign');
                sessionStorage.removeItem('aiDesignProductId');
                sessionStorage.removeItem('aiDesignPlacement');
                sessionStorage.removeItem('aiDesignTimestamp');
                
                // Remove the ai_design parameter from URL without reloading
                const newUrl = window.location.pathname + '?id=' + currentProductId;
                window.history.replaceState({}, '', newUrl);
            }
        }

        // Handle design upload for both front and back. Supports selecting
        // several images at once (input has "multiple"), and can be called
        // again later ("Add more") to append further images to the same side.
        function handleDesignUpload(input, side) {
            const files = Array.from(input.files || []);
            if (files.length === 0) return;

            const images = side === 'front' ? frontImages : backImages;
            const room = MAX_DESIGN_IMAGES_PER_SIDE - images.length;

            if (room <= 0) {
                alert(`You can upload up to ${MAX_DESIGN_IMAGES_PER_SIDE} images per side.`);
                input.value = '';
                return;
            }

            const accepted = [];
            const rejected = [];
            files.slice(0, room).forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    rejected.push(file.name + ' (too large, max 5MB)');
                } else if (!['image/jpeg', 'image/png'].includes(file.type)) {
                    rejected.push(file.name + ' (only JPEG or PNG images are allowed)');
                } else {
                    accepted.push(file);
                }
            });
            if (files.length > room) {
                rejected.push(`${files.length - room} file(s) skipped - limit is ${MAX_DESIGN_IMAGES_PER_SIDE} images per side`);
            }
            if (rejected.length > 0) {
                alert('Some files were not added:\n' + rejected.join('\n'));
            }
            input.value = ''; // allow re-selecting the same file later

            if (accepted.length === 0) return;

            // A newly chosen file makes any previously-approved mockup stale.
            designApplied = false;
            document.getElementById('designImageInput').value = '';
            document.getElementById('frontDesignImageInput').value = '';
            document.getElementById('backDesignImageInput').value = '';

            let remaining = accepted.length;
            accepted.forEach(file => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const aspect = (img.naturalWidth / img.naturalHeight) || 1;
                        images.push({
                            file: file,
                            dataUrl: event.target.result,
                            img: img,
                            position: defaultPositionFor(aspect, images.length)
                        });
                        remaining--;
                        if (remaining === 0) {
                            syncDesignImages(side);
                        }
                    };
                    img.src = event.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        // A fresh, centered box for a newly-added image, sized to that image's
        // own aspect ratio. Successive images are cascaded slightly so they
        // don't land exactly on top of each other and are immediately visible
        // (and grabbable) as separate, independently-draggable pieces.
        function defaultPositionFor(aspect, cascadeIndex) {
            const maxBox = (imageBoundary.width > 0 && imageBoundary.height > 0)
                ? Math.min(imageBoundary.width, imageBoundary.height) * 0.45
                : 150;
            let w = maxBox, h = maxBox / aspect;
            if (h > maxBox) { h = maxBox; w = maxBox * aspect; }
            w = Math.max(50, w);
            h = Math.max(50, h);

            const step = 22;
            const offset = ((cascadeIndex || 0) % 5) * step - step * 2;

            const baseX = imageBoundary.width > 0 ? imageBoundary.x + (imageBoundary.width - w) / 2 : 100;
            const baseY = imageBoundary.height > 0 ? imageBoundary.y + (imageBoundary.height - h) / 2 : 100;

            return {
                x: baseX + offset,
                y: baseY + offset,
                width: w,
                height: h,
                rotation: 0,
                opacity: 1
            };
        }

        // Re-derive an existing image's height from its own aspect ratio (in
        // case the stored position was left over from a different photo) and
        // clamp its box into the current printable area, without touching
        // x/y/width beyond what's needed to keep it on the canvas.
        function ensurePosition(entry) {
            const aspect = (entry.img.naturalWidth && entry.img.naturalHeight)
                ? entry.img.naturalWidth / entry.img.naturalHeight
                : 1;
            entry.position.height = entry.position.width / aspect;

            if (imageBoundary.width > 0 && imageBoundary.height > 0) {
                entry.position.width = Math.min(entry.position.width, imageBoundary.width);
                entry.position.height = Math.min(entry.position.height, imageBoundary.height);
                entry.position.x = Math.min(Math.max(entry.position.x, imageBoundary.x), imageBoundary.x + imageBoundary.width - entry.position.width);
                entry.position.y = Math.min(Math.max(entry.position.y, imageBoundary.y), imageBoundary.y + imageBoundary.height - entry.position.height);
            }
        }

        // Refresh a side's thumbnail strip / status text / upload zone after
        // its image list changes, and re-render the on-canvas overlay if that
        // side is the one currently being viewed.
        function syncDesignImages(side) {
            const images = side === 'front' ? frontImages : backImages;
            const statusId = side + 'DesignStatus';
            const designArea = side + 'DesignArea';
            const previewContainer = side + 'DesignPreview';

            renderDesignThumbs(side);

            if (images.length === 0) {
                document.getElementById(statusId).textContent = 'Not uploaded';
                document.getElementById(statusId).classList.remove('is-done');
                document.getElementById(designArea).classList.remove('has-design');
                document.getElementById(side + 'UploadZone').style.display = 'flex';
                document.getElementById(previewContainer).style.display = 'none';
                if (currentView === side) resetDesignOverlay();
                updateDesignType();
                return;
            }

            document.getElementById(statusId).textContent = images.length > 1
                ? `${images.length} images - each is independently movable`
                : 'Uploaded';
            document.getElementById(statusId).classList.add('is-done');
            document.getElementById(designArea).classList.add('has-design');
            document.getElementById(side + 'UploadZone').style.display = 'none';
            document.getElementById(previewContainer).style.display = 'block';

            if (currentView === side) {
                renderDesignOverlay(side);
            }
            updateDesignType();
            updatePreview();
        }

        // Build the draggable/resizable DOM element for one image entry.
        function buildDraggableElement(side, index, entry) {
            const designElement = document.createElement('div');
            designElement.className = 'draggable-design';
            designElement.dataset.side = side;
            designElement.dataset.index = index;
            designElement.dataset.aspect = (entry.img.naturalWidth / entry.img.naturalHeight) || 1;
            designElement.style.position = 'absolute';
            designElement.style.backgroundImage = `url(${entry.dataUrl})`;
            designElement.style.backgroundSize = 'contain';
            designElement.style.backgroundRepeat = 'no-repeat';
            designElement.style.backgroundPosition = 'center';
            designElement.style.width = `${entry.position.width}px`;
            designElement.style.height = `${entry.position.height}px`;
            designElement.style.left = `${entry.position.x}px`;
            designElement.style.top = `${entry.position.y}px`;
            designElement.style.transform = `rotate(${entry.position.rotation || 0}deg)`;
            designElement.style.opacity = entry.position.opacity ?? 1;
            designElement.style.cursor = isDraggingEnabled ? 'move' : 'default';
            designElement.style.pointerEvents = isDraggingEnabled ? 'auto' : 'none';

            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle';
            designElement.appendChild(resizeHandle);

            designElement.addEventListener('mousedown', function(e) {
                selectImage(side, index);
                startDrag(e);
            });
            resizeHandle.addEventListener('mousedown', function(e) {
                selectImage(side, index);
                startResize(e);
            });

            return designElement;
        }

        // Render every uploaded image for a side as its own draggable element
        // on the canvas. Called whenever that side's image list changes while
        // it's the active view, or when switching to that view.
        function renderDesignOverlay(side) {
            const overlay = document.getElementById('designOverlay');
            const images = side === 'front' ? frontImages : backImages;

            if (images.length === 0) {
                resetDesignOverlay();
                return;
            }

            calculateImageBoundary();
            images.forEach(entry => ensurePosition(entry));

            // Dragging is on by default whenever there's something to drag.
            isDraggingEnabled = true;
            document.getElementById('dragBtn').classList.add('is-on');
            document.getElementById('dragBtn').innerHTML = '<i class="fas fa-hand-paper"></i> Dragging on';

            overlay.innerHTML = '';
            images.forEach((entry, index) => {
                const el = buildDraggableElement(side, index, entry);
                entry.el = el;
                overlay.appendChild(el);
            });

            let indexToSelect = images.length - 1; // default: the most recently added
            if (selectedImage && selectedImage.side === side && selectedImage.index < images.length) {
                indexToSelect = selectedImage.index;
            }
            selectImage(side, indexToSelect);
        }

        // Mark one image as the active one for the position/size/rotation/
        // opacity tools, highlight it (and its thumbnail) and bring it to front.
        function selectImage(side, index) {
            const images = side === 'front' ? frontImages : backImages;
            if (index < 0 || index >= images.length) return;

            selectedImage = { side: side, index: index };
            currentDesign = images[index].el || null;

            images.forEach((entry, i) => {
                if (!entry.el) return;
                entry.el.classList.toggle('is-selected', i === index);
                entry.el.style.zIndex = (i === index) ? 10 : 1;
            });

            renderDesignThumbs(side);
            syncToolSlidersToView();
        }

        // The image entry the tools currently act on, or null if none.
        function getSelectedEntry() {
            if (!selectedImage) return null;
            const images = selectedImage.side === 'front' ? frontImages : backImages;
            return images[selectedImage.index] || null;
        }

        // Render the thumbnail strip (with remove buttons) plus an "add more"
        // tile for a side's uploaded images.
        function renderDesignThumbs(side) {
            const images = side === 'front' ? frontImages : backImages;
            const container = document.getElementById(side + 'DesignThumbs');
            container.innerHTML = '';

            images.forEach((entry, index) => {
                const thumb = document.createElement('div');
                thumb.className = 'design-thumb';
                if (selectedImage && selectedImage.side === side && selectedImage.index === index) {
                    thumb.classList.add('is-selected');
                }
                thumb.title = 'Click to select this image for positioning';

                const img = document.createElement('img');
                img.src = entry.dataUrl;
                img.alt = `${side} design ${index + 1}`;
                thumb.appendChild(img);

                thumb.addEventListener('click', () => {
                    if (currentView !== side) {
                        switchView(side);
                    }
                    selectImage(side, index);
                });

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'design-thumb__remove';
                removeBtn.title = 'Remove this image';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.onclick = (e) => {
                    e.stopPropagation();
                    removeDesignImage(side, index);
                };
                thumb.appendChild(removeBtn);

                container.appendChild(thumb);
            });

            if (images.length < MAX_DESIGN_IMAGES_PER_SIDE) {
                const addTile = document.createElement('button');
                addTile.type = 'button';
                addTile.className = 'add-more-tile';
                addTile.title = 'Add another image';
                addTile.innerHTML = '<i class="fas fa-plus"></i>';
                addTile.onclick = () => document.getElementById(side + 'DesignUpload').click();
                container.appendChild(addTile);
            }
        }

        // Remove a single image from a side's list.
        function removeDesignImage(side, index) {
            const images = side === 'front' ? frontImages : backImages;
            images.splice(index, 1);

            if (selectedImage && selectedImage.side === side) {
                selectedImage = null; // let syncDesignImages/renderDesignOverlay re-pick a valid one
            }

            designApplied = false;
            document.getElementById('designImageInput').value = '';
            document.getElementById('frontDesignImageInput').value = '';
            document.getElementById('backDesignImageInput').value = '';
            if (side === 'front') { currentMockup = null; } else { backMockup = null; }

            syncDesignImages(side);
        }

        // Remove every image for a side
        function removeDesign(side) {
            const images = side === 'front' ? frontImages : backImages;
            images.length = 0;

            if (selectedImage && selectedImage.side === side) {
                selectedImage = null;
            }

            if (side === 'front') {
                currentMockup = null;
            } else {
                backMockup = null;
            }

            // The previously-approved mockup no longer matches what's on screen,
            // so it can no longer be used at Add to Cart time. Nothing was ever
            // written to disk for it (that only happens on Add to Cart), so there
            // is nothing to clean up - the user just has to regenerate and
            // re-approve before they can add this item to their cart.
            designApplied = false;
            document.getElementById('designImageInput').value = '';
            document.getElementById('frontDesignImageInput').value = '';
            document.getElementById('backDesignImageInput').value = '';

            syncDesignImages(side); // resets UI, calls updateDesignType/updatePreview
        }

        // Automatically determine design type based on uploaded designs
        function updateDesignType() {
            const hasFront = frontImages.length > 0;
            const hasBack = backImages.length > 0;
            const hasBackTemplate = backImageUrl !== '';

            let designType = 'none';
            let designTypeText = '';

            if (hasFront && !hasBack) {
                designType = 'front_only';
                designTypeText = 'Front Only Design - The back will be plain';
            } else if (!hasFront && hasBack) {
                designType = 'back_only';
                designTypeText = 'Back Only Design - The front will be plain';
            } else if (hasFront && hasBack) {
                designType = 'both_sides';
                designTypeText = 'Both Sides Design - Front and back will have different designs';
            } else {
                designTypeText = 'Upload designs to see customization type';
            }

            // Add note about mockup preview
            if (hasFront || hasBack) {
                designTypeText += ' (Both sides will be shown in mockup preview)';
            }

            currentUploadType = designType;
            document.getElementById('designTypeText').textContent = designTypeText;
            document.getElementById('uploadTypeInput').value = designType;
        }

        // The small raw-image "design preview" box was removed in favor of the
        // on-product mockup shown after pressing "Done". This is kept as a
        // no-op so existing call sites don't need to change.
        function updatePreview() {}

        // Auto-select first option for each button group
        function autoSelectFirstOptions() {
            // Size options
            const sizeButtons = document.querySelectorAll('.option-button[data-value][onclick*="size"]');
            if (sizeButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="size"]')) {
                selectOption(sizeButtons[0], 'size');
            }

            // Color options
            const colorButtons = document.querySelectorAll('.option-button[data-value][onclick*="color"]');
            if (colorButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="color"]')) {
                selectOption(colorButtons[0], 'color');
            }

            // Paper options
            const paperButtons = document.querySelectorAll('.option-button[data-value][onclick*="paper"]');
            if (paperButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="paper"]')) {
                selectOption(paperButtons[0], 'paper');
            }

            // Finish options
            const finishButtons = document.querySelectorAll('.option-button[data-value][onclick*="finish"]');
            if (finishButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="finish"]')) {
                selectOption(finishButtons[0], 'finish');
            }

            // Layout options
            const layoutButtons = document.querySelectorAll('.option-button[data-value][onclick*="selectLayoutOption"]');
            if (layoutButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="selectLayoutOption"]')) {
                selectLayoutOption(layoutButtons[0]);
            }

            // Binding options
            const bindingButtons = document.querySelectorAll('.option-button[data-value][onclick*="binding"]');
            if (bindingButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="binding"]')) {
                selectOption(bindingButtons[0], 'binding');
            }
        }

        // Handle option selection for buttons
        function selectOption(button, type) {
            // Remove selected class from all buttons in the same group
            const buttonGroup = button.closest('.option-group');
            if (buttonGroup) {
                buttonGroup.querySelectorAll('.option-button').forEach(btn => {
                    btn.classList.remove('selected');
                });
            }
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Update the hidden input value
            const value = button.getAttribute('data-value');
            const hiddenInput = document.getElementById(type + 'Option');
            if (hiddenInput) {
                hiddenInput.value = value;
            }
            
            // Handle custom options
            const isCustom = button.getAttribute('data-custom') === '1';
            const customBox = document.getElementById(type === 'size' ? 'customSizeContainer' : (type === 'color' ? 'customColorContainer' : ''));
            if (customBox) {
                customBox.style.display = isCustom ? 'block' : 'none';
            }
        }

        // Handle layout option selection
        function selectLayoutOption(button) {
            // Remove selected class from all layout buttons
            const buttonGroup = button.closest('.option-group');
            if (buttonGroup) {
                buttonGroup.querySelectorAll('.option-button').forEach(btn => {
                    btn.classList.remove('selected');
                });
            }
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Update the hidden input value
            const value = button.getAttribute('data-value');
            document.getElementById('layoutOption').value = value;
            
            // Handle layout-specific inputs
            handleLayoutOptionChange();
        }

        // Switch between front and back views
        function switchView(view) {
            // Only allow switching to back view if back image exists
            if (view === 'back' && !backImageUrl) {
                alert('Back view is not available for this product');
                return;
            }

            currentView = view;

            // Update button states
            document.getElementById('frontViewBtn').classList.toggle('active', view === 'front');
            document.getElementById('backViewBtn').classList.toggle('active', view === 'back');

            // Change the base image
            const baseImage = document.getElementById('baseImage');
            if (view === 'front') {
                baseImage.src = frontImageUrl;
            } else {
                baseImage.src = backImageUrl;
            }

            // Re-render the per-image overlay for the current view
            renderDesignOverlay(view);

            updatePreview();
            setTimeout(calculateImageBoundary, 100);
        }

        // Keep the rotate/opacity sliders showing the selected image's values
        // (each image remembers its own rotation and opacity).
        function syncToolSlidersToView() {
            const entry = getSelectedEntry();
            const position = entry ? entry.position : { rotation: 0, opacity: 1 };
            const rotation = Math.round(position.rotation || 0);
            const opacityPct = Math.round((position.opacity ?? 1) * 100);
            document.getElementById('rotateSlider').value = rotation;
            document.getElementById('rotateReadout').textContent = `${rotation}°`;
            document.getElementById('opacitySlider').value = opacityPct;
            document.getElementById('opacityReadout').textContent = `${opacityPct}%`;
        }

        // Calculate the actual image boundary within the container with better precision
        function calculateImageBoundary() {
            const container = document.querySelector('.positioning-container');
            const img = document.getElementById('baseImage');
            
            if (!img.complete) {
                // If image isn't loaded yet, wait for it
                img.onload = calculateImageBoundary;
                return;
            }
            
            // Get the natural dimensions of the image
            const naturalWidth = img.naturalWidth;
            const naturalHeight = img.naturalHeight;
            
            // Get the displayed dimensions
            const displayedWidth = img.offsetWidth;
            const displayedHeight = img.offsetHeight;
            
            // Calculate the aspect ratios
            const containerAspect = container.offsetWidth / container.offsetHeight;
            const imageAspect = naturalWidth / naturalHeight;
            
            // Calculate the actual displayed image area (not including any whitespace)
            if (imageAspect > containerAspect) {
                // Image is wider than container - image fills width, centered vertically
                imageBoundary.width = container.offsetWidth;
                imageBoundary.height = container.offsetWidth / imageAspect;
                imageBoundary.x = 0;
                imageBoundary.y = (container.offsetHeight - imageBoundary.height) / 2;
            } else {
                // Image is taller than container - image fills height, centered horizontally
                imageBoundary.height = container.offsetHeight;
                imageBoundary.width = container.offsetHeight * imageAspect;
                imageBoundary.y = 0;
                imageBoundary.x = (container.offsetWidth - imageBoundary.width) / 2;
            }
            
            console.log('Image Boundary:', imageBoundary); // Debug info
            
            // Update boundary indicator if shown
            if (showBoundary) {
                updateBoundaryIndicator();
            }
        }

        // Update the boundary indicator
        function updateBoundaryIndicator() {
            const boundary = document.getElementById('designBoundary');
            boundary.style.display = 'block';
            boundary.style.left = `${imageBoundary.x}px`;
            boundary.style.top = `${imageBoundary.y}px`;
            boundary.style.width = `${imageBoundary.width}px`;
            boundary.style.height = `${imageBoundary.height}px`;
        }

        // Toggle boundary visibility
        function toggleBoundary() {
            showBoundary = !showBoundary;
            const btn = document.getElementById('boundaryBtn');

            if (showBoundary) {
                btn.classList.add('is-on');
                btn.innerHTML = '<i class="fas fa-border-all"></i> Hide boundaries';
                updateBoundaryIndicator();
            } else {
                btn.classList.remove('is-on');
                btn.innerHTML = '<i class="fas fa-border-all"></i> Show boundaries';
                document.getElementById('designBoundary').style.display = 'none';
            }
        }

        function changeImage(element, imageIndex) {
            // The visitor chose an image: stop the auto-advance
            clearInterval(slideInterval);

            // Update main image
            document.getElementById('mainImage').src = element.src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
            
            // Don't change the base image - it should stay as the template
        }

        let slideIndex = 0;
        const slideInterval = setInterval(() => {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (document.querySelector('.pd-gallery:hover')) return;
            const thumbs = document.querySelectorAll('.thumbnail');
            if (thumbs.length > 1) {
                slideIndex = (slideIndex + 1) % thumbs.length;
                const nextThumb = thumbs[slideIndex];
                document.getElementById('mainImage').src = nextThumb.src;
                document.querySelector('.thumbnail.active')?.classList.remove('active');
                nextThumb.classList.add('active');
            }
        }, 3000);

        // Quantity controls
        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            quantityInput.value = parseInt(quantityInput.value) + 1;
        }

        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            if (parseInt(quantityInput.value) > 1) {
                quantityInput.value = parseInt(quantityInput.value) - 1;
            }
        }

        // Enable/disable dragging for every image on the current side at once
        function enableDragging() {
            const images = currentView === 'front' ? frontImages : backImages;

            if (images.length === 0) {
                alert(`Please upload a ${currentView} design image first!`);
                return;
            }

            isDraggingEnabled = !isDraggingEnabled;
            const btn = document.getElementById('dragBtn');

            images.forEach(entry => {
                if (!entry.el) return;
                entry.el.style.cursor = isDraggingEnabled ? 'move' : 'default';
                entry.el.style.pointerEvents = isDraggingEnabled ? 'auto' : 'none';
            });

            if (isDraggingEnabled) {
                btn.classList.add('is-on');
                btn.innerHTML = '<i class="fas fa-hand-paper"></i> Dragging on';
            } else {
                btn.classList.remove('is-on');
                btn.innerHTML = '<i class="fas fa-arrows-alt"></i> Move design';
            }
        }

        // Helper function to reset design overlay (nothing left to show/drag
        // for the current side).
        function resetDesignOverlay() {
            const overlay = document.getElementById('designOverlay');
            overlay.innerHTML = '';
            currentDesign = null;
            selectedImage = null;
            isDraggingEnabled = false;
            document.getElementById('dragBtn').innerHTML = '<i class="fas fa-arrows-alt"></i> Move design';
            document.getElementById('dragBtn').classList.remove('is-on');
            syncToolSlidersToView();
        }

        // Start dragging
        function startDrag(e) {
            if (!isDraggingEnabled) return;
            if (e.target.classList.contains('resize-handle')) return;

            e.preventDefault();
            e.stopPropagation();
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            initialDesignPosition = {
                x: parseInt(currentDesign.style.left),
                y: parseInt(currentDesign.style.top)
            };

            document.addEventListener('mousemove', doDrag);
            document.addEventListener('mouseup', stopDrag);
        }

        // Perform dragging with boundary constraints
        function doDrag(e) {
            if (!isDragging) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            let newX = initialDesignPosition.x + dx;
            let newY = initialDesignPosition.y + dy;

            // Apply boundary constraints
            const designWidth = parseInt(currentDesign.style.width);
            const designHeight = parseInt(currentDesign.style.height);

            // Ensure design stays within image boundaries
            newX = Math.max(imageBoundary.x, newX);
            newY = Math.max(imageBoundary.y, newY);
            newX = Math.min(imageBoundary.x + imageBoundary.width - designWidth, newX);
            newY = Math.min(imageBoundary.y + imageBoundary.height - designHeight, newY);

            currentDesign.style.left = `${newX}px`;
            currentDesign.style.top = `${newY}px`;
        }

        // Stop dragging
        function stopDrag() {
            isDragging = false;

            // Save the position on the selected image itself, not the side
            const entry = getSelectedEntry();
            if (entry && currentDesign) {
                entry.position.x = parseInt(currentDesign.style.left);
                entry.position.y = parseInt(currentDesign.style.top);
            }

            document.removeEventListener('mousemove', doDrag);
            document.removeEventListener('mouseup', stopDrag);
        }

        // Start resizing
        function startResize(e) {
            e.stopPropagation();
            isResizing = true;
            startX = e.clientX;
            startY = e.clientY;
            initialDesignPosition = {
                width: parseInt(currentDesign.style.width),
                height: parseInt(currentDesign.style.height),
                x: parseInt(currentDesign.style.left),
                y: parseInt(currentDesign.style.top)
            };

            document.addEventListener('mousemove', doResize);
            document.addEventListener('mouseup', stopResize);
        }

        // Perform resizing with boundary constraints. Locked to the design
        // image's own aspect ratio (currentDesign.dataset.aspect) so the
        // photo is scaled uniformly instead of stretched - whichever axis
        // the pointer is moving more on drives the resize, and the other
        // dimension is derived from the aspect ratio.
        function doResize(e) {
            if (!isResizing) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            const aspect = parseFloat(currentDesign.dataset.aspect) ||
                (initialDesignPosition.width / initialDesignPosition.height) || 1;

            let newWidth = Math.abs(dx) >= Math.abs(dy)
                ? initialDesignPosition.width + dx
                : (initialDesignPosition.height + dy) * aspect;
            newWidth = Math.max(50, newWidth);
            let newHeight = newWidth / aspect;

            // Apply boundary constraints during resizing - scale both
            // dimensions together so the aspect ratio holds at the edges too.
            const maxWidth = imageBoundary.x + imageBoundary.width - initialDesignPosition.x;
            const maxHeight = imageBoundary.y + imageBoundary.height - initialDesignPosition.y;
            const scale = Math.min(1, maxWidth / newWidth, maxHeight / newHeight);
            if (scale < 1) {
                newWidth *= scale;
                newHeight *= scale;
            }

            currentDesign.style.width = `${newWidth}px`;
            currentDesign.style.height = `${newHeight}px`;
        }

        // Stop resizing
        function stopResize() {
            isResizing = false;

            const entry = getSelectedEntry();
            if (entry && currentDesign) {
                entry.position.width = parseInt(currentDesign.style.width);
                entry.position.height = parseInt(currentDesign.style.height);
            }

            document.removeEventListener('mousemove', doResize);
            document.removeEventListener('mouseup', stopResize);
        }

        // Resize the SELECTED image with buttons - always scales both
        // dimensions by the same factor, so this alone never stretches the
        // design; boundary clamping also scales both dimensions together for
        // the same reason. Only affects the currently selected image, not
        // every image on the side.
        function resizeDesign(factor) {
            const entry = getSelectedEntry();
            if (!currentDesign || !entry) {
                alert('Please select a design image first!');
                return;
            }

            const currentWidth = parseInt(currentDesign.style.width);
            const currentHeight = parseInt(currentDesign.style.height);
            const currentX = parseInt(currentDesign.style.left);
            const currentY = parseInt(currentDesign.style.top);

            let newWidth = Math.max(50, currentWidth * factor);
            let newHeight = Math.max(50, currentHeight * factor);

            // Apply boundary constraints
            const maxWidth = imageBoundary.x + imageBoundary.width - currentX;
            const maxHeight = imageBoundary.y + imageBoundary.height - currentY;
            const scale = Math.min(1, maxWidth / newWidth, maxHeight / newHeight);
            if (scale < 1) {
                newWidth *= scale;
                newHeight *= scale;
            }

            currentDesign.style.width = `${newWidth}px`;
            currentDesign.style.height = `${newHeight}px`;

            entry.position.width = newWidth;
            entry.position.height = newHeight;
        }

        // Rotate the SELECTED image by a relative amount (degrees), via the
        // Rotate left/right buttons.
        function rotateDesignBy(deltaDegrees) {
            const entry = getSelectedEntry();
            if (!currentDesign || !entry) {
                alert('Please select a design image first!');
                return;
            }
            let rotation = ((entry.position.rotation || 0) + deltaDegrees) % 360;
            if (rotation < 0) rotation += 360;
            entry.position.rotation = rotation;
            currentDesign.style.transform = `rotate(${rotation}deg)`;

            const rounded = Math.round(rotation);
            document.getElementById('rotateSlider').value = rounded;
            document.getElementById('rotateReadout').textContent = `${rounded}°`;
        }

        // Rotate the SELECTED image to an absolute angle, via the slider.
        function setDesignRotation(value) {
            const entry = getSelectedEntry();
            if (!currentDesign || !entry) return;
            const rotation = parseFloat(value) || 0;
            entry.position.rotation = rotation;
            currentDesign.style.transform = `rotate(${rotation}deg)`;
            document.getElementById('rotateReadout').textContent = `${Math.round(rotation)}°`;
        }

        // Set the SELECTED image's opacity (0.1-1), via the slider.
        function setDesignOpacity(value) {
            const entry = getSelectedEntry();
            if (!currentDesign || !entry) return;
            const opacity = Math.max(0.1, Math.min(1, parseFloat(value) / 100));
            entry.position.opacity = opacity;
            currentDesign.style.opacity = opacity;
            document.getElementById('opacityReadout').textContent = `${Math.round(opacity * 100)}%`;
        }

        // Reset the SELECTED image's position, size, rotation and opacity -
        // the default box is sized to the image's own aspect ratio (not a
        // fixed square) so it starts out true to the photo. Other images on
        // the same side are left exactly where they are.
        function resetDesignPosition() {
            const entry = getSelectedEntry();
            if (!currentDesign || !entry) {
                alert('Please select a design image first!');
                return;
            }

            const aspect = parseFloat(currentDesign.dataset.aspect) || 1;
            const newPosition = defaultPositionFor(aspect, 0);
            entry.position = newPosition;

            currentDesign.style.width = `${newPosition.width}px`;
            currentDesign.style.height = `${newPosition.height}px`;
            currentDesign.style.left = `${newPosition.x}px`;
            currentDesign.style.top = `${newPosition.y}px`;
            currentDesign.style.transform = 'rotate(0deg)';
            currentDesign.style.opacity = 1;

            syncToolSlidersToView();
        }

        // Generate mockup preview - ALWAYS show both sides if templates exist
        function generateMockup() {
            const hasFrontDesign = frontImages.length > 0;
            const hasBackDesign = backImages.length > 0;

            if (!hasFrontDesign && !hasBackDesign) {
                alert('Please upload at least one design image!');
                return;
            }

            // Use base images as templates
            const frontTemplate = "<?php echo $base_image_url; ?>";
            const backTemplate = "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>";

            // Show loading state
            document.querySelectorAll('.mockup-image').forEach(el => el.style.display = 'none');

            // Reset mockups
            currentMockup = '';
            backMockup = '';

            // ALWAYS generate front mockup if template exists
            if (frontTemplate) {
                if (hasFrontDesign) {
                    // Composite every uploaded front image, each at its own
                    // position/size/rotation/opacity
                    generateSideMockup(
                        frontImages,
                        frontTemplate,
                        'mockupFront',
                        'frontMockupContainer',
                        'Front View'
                    );
                } else {
                    // Show front template only (no design)
                    generateTemplateOnly(
                        frontTemplate,
                        'mockupFront',
                        'frontMockupContainer',
                        'Front View (No Design)'
                    );
                }
            } else {
                document.getElementById('frontMockupContainer').style.display = 'none';
            }

            // ALWAYS generate back mockup if template exists
            if (backTemplate) {
                if (hasBackDesign) {
                    generateSideMockup(
                        backImages,
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        'Back View'
                    );
                } else {
                    // Show back template only (no design)
                    generateTemplateOnly(
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        'Back View (No Design)'
                    );
                }
            } else {
                document.getElementById('backMockupContainer').style.display = 'none';
            }

            document.getElementById('mockupPopup').style.display = 'flex';
        }

        // Generate template-only view (when no design is uploaded for that side)
        function generateTemplateOnly(templatePath, outputId, containerId, labelText) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const productTemplate = new Image();

            productTemplate.src = templatePath;
            productTemplate.onload = function() {
                // Set canvas to high resolution
                canvas.width = productTemplate.width;
                canvas.height = productTemplate.height;
                
                // Use high-quality image rendering
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                // Draw product template only (no design overlay)
                ctx.drawImage(productTemplate, 0, 0, canvas.width, canvas.height);

                // Output the template image
                const finalImage = canvas.toDataURL('image/png', 1.0);
                document.getElementById(outputId).src = finalImage;
                document.getElementById(containerId).style.display = 'block';
                
                // Update the label to indicate no design
                const labelElement = document.querySelector(`#${containerId} p`);
                if (labelElement) {
                    labelElement.textContent = labelText;
                }
                
                // Store mockup (empty for this side)
                if (outputId === 'mockupFront') {
                    currentMockup = '';
                } else if (outputId === 'mockupBack') {
                    backMockup = '';
                }
            };
        }

        // Generate a side's mockup by embedding EVERY uploaded image for that
        // side onto the product template, each at its own independently-set
        // position/size/rotation/opacity - same math the on-screen overlay
        // uses, just scaled up to the template's real resolution.
        function generateSideMockup(images, templatePath, outputId, containerId, label) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const productTemplate = new Image();

            productTemplate.src = templatePath;
            productTemplate.onload = function() {
                // Set canvas to high resolution
                canvas.width = productTemplate.width;
                canvas.height = productTemplate.height;

                // Use high-quality image rendering
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                // Draw product template first (as background)
                ctx.drawImage(productTemplate, 0, 0, canvas.width, canvas.height);

                // Calculate scale factors based on actual image dimensions, not container
                const scaleX = productTemplate.width / imageBoundary.width;
                const scaleY = productTemplate.height / imageBoundary.height;

                images.forEach(entry => {
                    const position = entry.position;
                    const designImage = entry.img;

                    // Calculate this image's box position relative to the actual image boundary
                    const boxX = (position.x - imageBoundary.x) * scaleX;
                    const boxY = (position.y - imageBoundary.y) * scaleY;
                    const boxWidth = position.width * scaleX;
                    const boxHeight = position.height * scaleY;

                    // The on-screen preview shows each image with
                    // backgroundSize: 'contain' inside its own box (never
                    // stretched). Match that here: fit the image's own aspect
                    // ratio inside the box instead of stretching it to fill
                    // boxWidth x boxHeight exactly.
                    const designAspect = (designImage.naturalWidth && designImage.naturalHeight)
                        ? designImage.naturalWidth / designImage.naturalHeight
                        : boxWidth / boxHeight;
                    const boxAspect = boxWidth / boxHeight;

                    let drawWidth = boxWidth, drawHeight = boxHeight;
                    if (designAspect > boxAspect) {
                        drawWidth = boxWidth;
                        drawHeight = boxWidth / designAspect;
                    } else {
                        drawHeight = boxHeight;
                        drawWidth = boxHeight * designAspect;
                    }
                    const drawX = boxX + (boxWidth - drawWidth) / 2;
                    const drawY = boxY + (boxHeight - drawHeight) / 2;

                    // Draw this image on top of the product template with
                    // high quality, applying its own rotation and opacity
                    // (rotating/fading around its own box's center).
                    ctx.save();
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.globalAlpha = position.opacity ?? 1;
                    const centerX = boxX + boxWidth / 2;
                    const centerY = boxY + boxHeight / 2;
                    ctx.translate(centerX, centerY);
                    ctx.rotate(((position.rotation || 0) * Math.PI) / 180);
                    ctx.drawImage(designImage, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
                    ctx.restore();
                });

                // Output the combined image to the mockup preview
                const finalImage = canvas.toDataURL('image/png', 1.0); // Maximum quality
                document.getElementById(outputId).src = finalImage;
                document.getElementById(containerId).style.display = 'block';

                // Update the label
                const labelElement = document.querySelector(`#${containerId} p`);
                if (labelElement) {
                    labelElement.textContent = label;
                }

                // Store mockup
                if (outputId === 'mockupFront') {
                    currentMockup = finalImage;
                } else if (outputId === 'mockupBack') {
                    backMockup = finalImage;
                }
            };
        }

        // Download mockup
        function downloadMockup(imageId, fileName) {
            const link = document.createElement('a');
            link.href = document.getElementById(imageId).src;
            link.download = fileName;
            link.click();
        }

        // Use this design - approve the mockup for use, but do NOT touch the
        // server/disk yet. Files are only written when the user actually presses
        // "Add to cart" (see submitCartForm()). This is what previously caused
        // orphaned mockup files: clicking "Use This Design" used to save
        // immediately, so removing the photo afterwards left saved files behind
        // with nothing ever referencing them.
        function useThisDesign() {
            const hasFrontDesign = frontImages.length > 0;
            const hasBackDesign = backImages.length > 0;

            if (!hasFrontDesign && !hasBackDesign) {
                alert('Please generate mockups for your designs first!');
                return;
            }

            // Nothing is uploaded here - we just mark the currently generated
            // currentMockup/backMockup as approved. removeDesign() and a fresh
            // file upload both flip designApplied back to false, so a stale
            // mockup can never slip through to the server later.
            designApplied = true;

            alert('Design applied. It will be saved when you add this item to your cart.');
            closeModal();
        }

        // Helper function to save designs and uploaded files - ALWAYS save both mockups
        // Only called from submitCartForm(), i.e. once the user actually presses
        // "Add to cart" - this is the sole point where files get written to disk.
        async function saveBothDesigns(frontImageData, backImageData) {
            try {
                const formData = new FormData();

                // ALWAYS send both images, even if one is empty
                // For empty sides, we'll send a flag to create a plain mockup
                formData.append('csrf_token', <?php echo esc_js(csrf_token()); ?>);
                formData.append('front_image', frontImageData || '');
                formData.append('back_image', backImageData || '');
                formData.append('has_front_design', frontImages.length > 0 ? '1' : '0');
                formData.append('has_back_design', backImages.length > 0 ? '1' : '0');

                // Add every original uploaded file as a record, one per side.
                // These are kept purely as reference copies of what the
                // customer supplied - the combined design itself (what
                // actually appears on the product) always goes through
                // front_image/back_image above regardless of how many source
                // photos it was built from.
                frontImages.forEach(entry => {
                    if (entry.file) formData.append('front_design_file[]', entry.file);
                });
                backImages.forEach(entry => {
                    if (entry.file) formData.append('back_design_file[]', entry.file);
                });

                // Add design configuration
                formData.append('upload_type', currentUploadType);
                formData.append('user_id', '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>');
                formData.append('product_id', '<?php echo $product_id; ?>');

                // Add template paths so server can generate plain mockups
                formData.append('front_template', "<?php echo $base_image_url; ?>");
                formData.append('back_template', "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>");

                const response = await fetch('save_design.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (!response.ok && result.success === undefined) {
                    // Defensive fallback in case the server ever responds with a
                    // non-2xx status but no explicit success flag.
                    result.success = false;
                }
                return result;
            } catch (error) {
                console.error('Error saving designs:', error);
                return {
                    success: false,
                    error: 'Could not reach the server to save your design. Please check your connection and try again.'
                };
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('mockupPopup').style.display = 'none';
        }

        function handleLayoutOptionChange() {
            const layoutValue = document.getElementById('layoutOption').value;
            const layoutInputContainer = document.getElementById('layoutInputContainer');

            // Clear previous content
            layoutInputContainer.innerHTML = '';

            if (layoutValue == 1) { // Assuming 1 is the ID for "User Layout"
                layoutInputContainer.innerHTML = `
                    <label for="userLayoutUpload" class="pd-label required-field">Upload your design files</label>
                    <input type="file" id="userLayoutUpload" class="pd-file" name="user_layout_upload[]" multiple accept="image/jpeg,image/png,.jpg,.jpeg,.png,.pdf,application/pdf">
                    <small class="pd-hint">You can upload multiple files (JPEG, PNG, or PDF only)</small>
                    <div id="userLayoutPreview" class="pd-filelist"></div>
                `;

                // Add event listener for file upload
                setTimeout(() => {
                    const uploadInput = document.getElementById('userLayoutUpload');
                    if (uploadInput) {
                        let accumulatedLayoutFiles = [];

                        uploadInput.addEventListener('change', function(e) {
                            const newFiles = Array.from(e.target.files);
                            const previewContainer = document.getElementById('userLayoutPreview');
                            previewContainer.innerHTML = '';

                            const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                            const allowedExts = ['.jpg', '.jpeg', '.png', '.pdf'];
                            const rejectedNames = [];

                            newFiles.forEach(function(file) {
                                const nameLower = file.name.toLowerCase();
                                const extOk = allowedExts.some(ext => nameLower.endsWith(ext));
                                const typeOk = allowedTypes.includes(file.type);
                                const alreadyAdded = accumulatedLayoutFiles.some(f => f.name === file.name && f.size === file.size);
                                if (extOk && (typeOk || file.type === '') && !alreadyAdded) {
                                    accumulatedLayoutFiles.push(file);
                                } else if (!extOk || !(typeOk || file.type === '')) {
                                    rejectedNames.push(file.name);
                                }
                            });

                            if (rejectedNames.length > 0) {
                                alert('These files were not accepted (only JPEG, PNG, and PDF are allowed):\n' + rejectedNames.join('\n'));
                            }

                            // Rebuild the input's FileList so the form submits every accumulated file
                            const dt = new DataTransfer();
                            accumulatedLayoutFiles.forEach(f => dt.items.add(f));
                            uploadInput.files = dt.files;

                            if (accumulatedLayoutFiles.length > 0) {
                                const title = document.createElement('p');
                                title.className = 'pd-filelist__title';
                                title.textContent = 'Uploaded files';
                                previewContainer.appendChild(title);

                                accumulatedLayoutFiles.forEach(function(file, idx) {
                                    const row = document.createElement('div');
                                    row.className = 'pd-filelist__item';
                                    row.innerHTML = '<i class="fas fa-file"></i>';
                                    const label = document.createElement('span');
                                    label.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                                    row.appendChild(label);

                                    const removeBtn = document.createElement('button');
                                    removeBtn.type = 'button';
                                    removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                                    removeBtn.title = 'Remove this file';
                                    removeBtn.style.cssText = 'margin-left:8px;border:none;background:transparent;color:#d9463c;cursor:pointer;font-size:12px;';
                                    removeBtn.addEventListener('click', function() {
                                        accumulatedLayoutFiles.splice(idx, 1);
                                        const dt2 = new DataTransfer();
                                        accumulatedLayoutFiles.forEach(f => dt2.items.add(f));
                                        uploadInput.files = dt2.files;
                                        uploadInput.dispatchEvent(new Event('change'));
                                    });
                                    row.appendChild(removeBtn);

                                    previewContainer.appendChild(row);
                                });
                            }
                        });
                    }
                }, 100);

            } else if (layoutValue == 2) { // Assuming 2 is the ID for "Store Layout"
                layoutInputContainer.innerHTML = `
                    <label for="layoutDetails" class="pd-label required-field">Design specifications</label>
                    <textarea id="layoutDetails" class="pd-input" name="layout_details" rows="4" placeholder="Please describe your design preferences, colors, text, images, and any specific requirements..."></textarea>
                    <small class="pd-hint">Please be as detailed as possible to help us create your design</small>
                `;
            }

            layoutInputContainer.style.display = 'block';
        }

                // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation + design persistence before submitting to cart.
        // This is the ONLY place save_design.php is ever called, so design
        // files only ever reach disk once the user has actually committed to
        // adding the item to their cart.
        document.getElementById('cartForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitCartForm();
        });

        async function submitCartForm() {
            if (!validateForm()) {
                return false;
            }

            const cartForm = document.getElementById('cartForm');
            const submitBtn = document.getElementById('cartSubmitBtn');

            <?php if ($show_image_customization): ?>
            const hasFrontDesign = frontImages.length > 0;
            const hasBackDesign = backImages.length > 0;

            if (hasFrontDesign || hasBackDesign) {
                // designApplied is guaranteed true here (validateForm just checked
                // it), so currentMockup/backMockup are known to match what's
                // currently on screen.
                const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving design...';
                }

                try {
                    const designData = await saveBothDesigns(currentMockup || '', backMockup || '');

                    const frontFailed = hasFrontDesign && (!designData.front_mockup || designData.front_mockup === 'error');
                    const backFailed = hasBackDesign && (!designData.back_mockup || designData.back_mockup === 'error');

                    if (designData.success === false || frontFailed || backFailed) {
                        const messages = Array.isArray(designData.errors) && designData.errors.length
                            ? designData.errors.join('\n')
                            : (designData.error || 'Something went wrong while saving your design. Please try again.');
                        alert(messages);
                        return false; // Nothing was added to the cart.
                    }

                    const completeDesignData = {
                        front_mockup: designData.front_mockup || '',
                        back_mockup: designData.back_mockup || '',
                        front_uploaded_files: designData.front_uploaded_files || [],
                        back_uploaded_files: designData.back_uploaded_files || [],
                        upload_type: currentUploadType,
                        has_front_design: hasFrontDesign ? '1' : '0',
                        has_back_design: hasBackDesign ? '1' : '0',
                        front_design_position: frontImages.map(entry => entry.position),
                        back_design_position: backImages.map(entry => entry.position)
                    };
                    const designDataString = JSON.stringify(completeDesignData);

                    document.getElementById('designImageInput').value = designDataString;
                    document.getElementById('frontDesignImageInput').value = designDataString;
                    document.getElementById('backDesignImageInput').value = designDataString;
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }
            }
            <?php endif; ?>

            // Everything needed is now in the hidden fields - hand off to
            // add_to_cart.php. Using the native submit() bypasses this same
            // 'submit' listener, so it won't loop back into validation.
            cartForm.submit();
        }

        function validateForm() {
            const productId = <?php echo $product_id; ?>;
            let isValid = true;
            let errorMessage = '';

            // Check if product requires customization options
            <?php if ($customization): ?>

                // Validate size options for Other Services (IDs 18-21)
                <?php if (in_array($product_id, [18, 19, 20, 21])): ?>
                    const sizeOption = document.getElementById('sizeOption');
                    if (sizeOption && !sizeOption.value) {
                        isValid = false;
                        errorMessage += '• Please select a size option\n';
                    } else if (sizeOption && sizeOption.value) {
                        const selectedButton = document.querySelector('.option-button.selected[onclick*="size"]');
                        if (selectedButton) {
                            const isCustomSize = selectedButton.getAttribute('data-custom') === '1';
                            const customSizeInput = document.querySelector('input[name="custom_size"]');
                            
                            if (isCustomSize && (!customSizeInput || !customSizeInput.value.trim())) {
                                isValid = false;
                                errorMessage += '• Please specify your custom size\n';
                            }
                        }
                    }
                <?php endif; ?>

                // Validate color options for Other Services (IDs 18-21)
                <?php if (in_array($product_id, [18, 19, 21])): ?>
                    const colorOption = document.getElementById('colorOption');
                    if (colorOption && !colorOption.value) {
                        isValid = false;
                        errorMessage += '• Please select a color option\n';
                    } else if (colorOption && colorOption.value) {
                        const selectedButton = document.querySelector('.option-button.selected[onclick*="color"]');
                        if (selectedButton) {
                            const isCustomColor = selectedButton.getAttribute('data-custom') === '1';
                            const customColorInput = document.querySelector('input[name="custom_color"]');
                            
                            if (isCustomColor && (!customColorInput || !customColorInput.value.trim())) {
                                isValid = false;
                                errorMessage += '• Please specify your custom color\n';
                            }
                        }
                    }
                <?php endif; ?>

                // Validate printing options for printing categories
                <?php if ($customization && !in_array($product_id, [18, 19, 20, 21]) && in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])): ?>

                    // Validate paper option
                    <?php if ($customization['has_paper_option'] && !empty($paper_options)): ?>
                        const paperOption = document.getElementById('paperOption');
                        if (paperOption && !paperOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a paper type\n';
                        }
                    <?php endif; ?>

                    // Validate size input for printing
                    <?php if ($customization['has_size_option'] && !in_array($product_id, [18, 19, 20, 21]) && in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])): ?>
                        const sizeInput = document.querySelector('input[name="size_option"]');
                        if (sizeInput && !sizeInput.value.trim()) {
                            isValid = false;
                            errorMessage += '• Please specify the size (e.g., 8.5 x 11)\n';
                        }
                    <?php endif; ?>

                    // Validate finish option
                    <?php if ($customization['has_finish_option'] && !empty($finish_options)): ?>
                        const finishOption = document.getElementById('finishOption');
                        if (finishOption && !finishOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a finish option\n';
                        }
                    <?php endif; ?>

                    // Validate layout option
                    <?php if ($customization['has_layout_option'] && !empty($layout_options)): ?>
                        const layoutOption = document.getElementById('layoutOption');
                        if (layoutOption && !layoutOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a layout option\n';
                        }

                        // Validate layout details based on selection
                        if (layoutOption && layoutOption.value) {
                            const selectedButton = document.querySelector('.option-button.selected[onclick*="selectLayoutOption"]');
                            if (selectedButton) {
                                const selectedText = selectedButton.textContent;
                                
                                if (selectedText.includes('User Layout')) {
                                    const userLayoutUpload = document.getElementById('userLayoutUpload');
                                    if (!userLayoutUpload || !userLayoutUpload.files.length) {
                                        isValid = false;
                                        errorMessage += '• Please upload your design files for User Layout\n';
                                    }
                                } else if (selectedText.includes('Store Layout')) {
                                    const layoutDetails = document.querySelector('textarea[name="layout_details"]');
                                    if (!layoutDetails || !layoutDetails.value.trim()) {
                                        isValid = false;
                                        errorMessage += '• Please provide design specifications for Store Layout\n';
                                    }
                                }
                            }
                        }
                    <?php endif; ?>

                    // Validate binding option
                    <?php if ($customization['has_binding_option'] && !empty($binding_options)): ?>
                        const bindingOption = document.getElementById('bindingOption');
                        if (bindingOption && !bindingOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a binding option\n';
                        }
                    <?php endif; ?>

                    // Validate GSM option
                    <?php if ($customization['has_gsm_option']): ?>
                        const gsmOption = document.querySelector('input[name="gsm_option"]');
                        if (gsmOption && (!gsmOption.value || gsmOption.value <= 0)) {
                            isValid = false;
                            errorMessage += '• Please enter a valid paper weight (GSM)\n';
                        }
                    <?php endif; ?>

                <?php endif; // End printing categories validation 
                ?>

            <?php endif; // End customization validation 
            ?>

            // Validate image customization for Other Services
            <?php if ($show_image_customization): ?>
                const hasFrontDesign = frontImages.length > 0;
                const hasBackDesign = backImages.length > 0;

                if (!hasFrontDesign && !hasBackDesign) {
                    isValid = false;
                    errorMessage += '• Please upload at least one design image\n';
                }

                // Check if design was applied (and still matches what's on screen -
                // removing/re-uploading a design resets this until re-approved)
                if ((hasFrontDesign || hasBackDesign) && !designApplied) {
                    isValid = false;
                    errorMessage += '• Please press "Done" and "Use This Design" to apply your design\n';
                }
            <?php endif; ?>

            // Validate quantity
            const quantityInput = document.getElementById('quantity');
            if (!quantityInput || quantityInput.value < 1) {
                isValid = false;
                errorMessage += '• Please enter a valid quantity\n';
            }

            // Show error message if validation fails
            if (!isValid) {
                alert('Please complete the following required fields:\n\n' + errorMessage);

                // Scroll to the first error (optional)
                if (errorMessage.includes('size')) {
                    document.querySelector('.customization-section').scrollIntoView({
                        behavior: 'smooth'
                    });
                } else if (errorMessage.includes('design') || errorMessage.includes('mockup')) {
                    document.querySelector('.design-areas').scrollIntoView({
                        behavior: 'smooth'
                    });
                }

                return false;
            }

            return true;
        }

        function autoPopulateAIDesign(imageData) {
            // Get placement from session storage
            const placement = sessionStorage.getItem('aiDesignPlacement') || 'front';
            const currentProductId = <?php echo $product_id; ?>;
            
            console.log('AI Design Placement:', placement, 'Product ID:', currentProductId);

            // Convert base64 to blob and create file
            fetch(imageData)
                .then(res => res.blob())
                .then(blob => {
                    const file = new File([blob], `ai-design-${Date.now()}.png`, { type: 'image/png' });
                    
                    // Determine which design area to populate based on placement
                    if (placement === 'front' || placement === 'both') {
                        populateDesignArea('front', file, imageData);
                    }
                    
                    if (placement === 'back' || placement === 'both') {
                        // Only populate back if back design area exists and is visible
                        const backDesignArea = document.getElementById('backDesignArea');
                        if (backDesignArea && backDesignArea.style.display !== 'none') {
                            populateDesignArea('back', file, imageData);
                        } else if (placement === 'back') {
                            // If back was requested but not available, use front instead
                            populateDesignArea('front', file, imageData);
                        }
                    }
                    
                    // Show success message
                    setTimeout(() => {
                        let message = 'AI-generated design loaded successfully! ';
                        if (placement === 'front') message += 'Design applied to front.';
                        else if (placement === 'back') message += 'Design applied to back.';
                        else if (placement === 'both') message += 'Design applied to both sides.';
                        
                        alert(message);
                        
                        // Switch to appropriate view
                        if (placement === 'back') {
                            switchView('back');
                        } else {
                            switchView('front');
                        }
                    }, 500);
                })
                .catch(error => {
                    console.error('Error loading AI design:', error);
                    alert('Error loading AI design. Please upload manually.');
                });
        }

        // Helper function to populate a specific design area with an
        // AI-generated design. Feeds it through the same frontImages/backImages
        // array + syncDesignImages() pathway as a manual upload, so it shows
        // up in the thumbnail strip, gets its own independent position, and
        // stays separately draggable if the customer adds more images
        // afterwards instead of silently getting overwritten.
        function populateDesignArea(side, file, imageData) {
            designApplied = false;
            document.getElementById('designImageInput').value = '';
            document.getElementById('frontDesignImageInput').value = '';
            document.getElementById('backDesignImageInput').value = '';

            const images = side === 'front' ? frontImages : backImages;
            const img = new Image();
            img.onload = function() {
                const aspect = (img.naturalWidth / img.naturalHeight) || 1;
                images.push({
                    file: file,
                    dataUrl: imageData,
                    img: img,
                    position: defaultPositionFor(aspect, images.length)
                });
                syncDesignImages(side);
                document.getElementById(side + 'DesignStatus').textContent = 'AI generated';
            };
            img.src = imageData;
        }
    </script>
    <script>
        // Chat functionality
        let currentConversationId = null;
        let chatRefreshInterval = null;

        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            const chatButton = document.getElementById('chatButton');
            const chatCloseBtn = document.getElementById('chatCloseBtn');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const newChatBtn = document.getElementById('newChatBtn');
            const chatSendBtn = document.getElementById('chatSendBtn');
            const chatInput = document.getElementById('chatInput');

            if (chatButton) {
                chatButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleChat();
                });
            }

            if (chatCloseBtn) {
                chatCloseBtn.addEventListener('click', toggleChat);
            }

            if (chatBackBtn) {
                chatBackBtn.addEventListener('click', goBackToConversations);
            }

            if (newChatBtn) {
                newChatBtn.addEventListener('click', startNewConversation);
            }

            if (chatSendBtn) {
                chatSendBtn.addEventListener('click', sendMessage);
            }

            if (chatInput) {
                chatInput.addEventListener('input', function() {
                    autoResize(this);
                });

                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            // Load initial unread count
            updateUnreadCount();

            // Check for unread messages every minute
            setInterval(updateUnreadCount, 60000);
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                } else {
                    stopChatRefresh();
                }
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversations');
                const data = await response.json();

                if (data.success) {
                    renderConversations(data.data);
                    updateUnreadCount();

                    // Show conversation count in the UI
                    updateConversationCount(data.data.length);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
                showChatError('Failed to load conversations. Please try again.');
            }
        }

        // Add this function to check conversation limit
        async function checkConversationLimit() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversation_limit');
                const data = await response.json();

                if (data.success) {
                    return {
                        reached: data.reached || false,
                        count: data.count || 0,
                        limit: data.limit || 3
                    };
                }
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            } catch (error) {
                console.error('Error checking conversation limit:', error);
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            }
        }

        // Render conversations list with delete buttons
        function renderConversations(conversations) {
            const container = document.getElementById('conversationsList');
            if (!container) return;

            if (conversations.length === 0) {
                container.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
                return;
            }

            container.innerHTML = conversations.map(conv => `
            <div class="chat-conversation-item ${currentConversationId === conv.id ? 'active' : ''}" 
                 onclick="openConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation')}')">
                <div class="conversation-header">
                    <div class="conversation-name">${escapeHtml(conv.title || 'Conversation #' + conv.id)}</div>
                    <button class="delete-conversation-btn" onclick="event.stopPropagation(); deleteConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation #' + conv.id)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="conversation-last-message">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                <div class="conversation-footer">
                    <div class="conversation-time">${formatTime(conv.last_message_time)}</div>
                    ${conv.unread_count > 0 ? `<div class="conversation-unread">${conv.unread_count} new</div>` : ''}
                </div>
            </div>
        `).join('');
        }

        // Delete conversation
        async function deleteConversation(conversationId, conversationTitle) {
            if (!confirm(`Are you sure you want to delete "${conversationTitle}"? This action cannot be undone.`)) {
                return;
            }

            try {
                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_conversation',
                        conversation_id: conversationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // If we're currently viewing this conversation, go back to list
                    if (currentConversationId === conversationId) {
                        goBackToConversations();
                    }

                    // Remove the conversation item from UI
                    const conversationItem = document.querySelector(`.chat-conversation-item[onclick*="${conversationId}"]`);
                    if (conversationItem) {
                        conversationItem.remove();
                    }

                    // Reload conversations list
                    await loadConversations();

                    showChatSuccess('Conversation deleted successfully.');
                } else {
                    showChatError(data.message || 'Failed to delete conversation.');
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
                showChatError('Failed to delete conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Open conversation
        function openConversation(conversationId, title) {
            currentConversationId = conversationId;

            // Update UI
            document.getElementById('chatConversations').style.display = 'none';
            document.getElementById('chatMessages').classList.add('active');
            document.getElementById('chatInputArea').classList.add('active');
            document.getElementById('chatBackBtn').classList.add('visible');
            document.getElementById('chatTitle').textContent = title;

            // Load messages
            loadMessages(conversationId);

            // Mark as read
            markAsRead(conversationId);
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            document.getElementById('chatConversations').style.display = 'flex';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showChatError('Failed to load messages. Please try again.');
            }
        }

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
                <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}">
                    ${!isSent && !isSystem ? `
                        <div class="message-sender">
                            ${escapeHtml(msg.sender_display_name || msg.sender_username)}
                        </div>
                    ` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.message)}</div>
                        <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message || !currentConversationId) return;

            // Disable send button
            const sendBtn = document.getElementById('chatSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            try {
                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        conversation_id: currentConversationId,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    autoResize(input);
                    loadMessages(currentConversationId);
                    updateUnreadCount();
                } else {
                    showChatError(data.message || 'Failed to send message.');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showChatError('Failed to send message. Please try again.');
            } finally {
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        // Start new conversation with online admin
        async function startNewConversation() {
            try {
                // First check if user has reached conversation limit
                const limitCheck = await checkConversationLimit();
                if (limitCheck.reached) {
                    showChatError(`You have reached the maximum limit of 3 active conversations. You currently have ${limitCheck.count} active conversations. Please complete or close existing conversations before starting a new one.`);
                    return;
                }

                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start_conversation',
                        title: 'Support Request',
                        request_online_admin: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const adminInfo = data.admin_name ? ` (Connected with: ${data.admin_name})` : '';
                    openConversation(data.conversation_id, 'Support Request');

                    if (data.admin_name) {
                        showSystemMessage(`You've been connected with administrator ${data.admin_name}. How can we help you?`);
                    }
                } else {
                    // Handle "no admin available" gracefully
                    if (data.message && data.message.includes('No administrators')) {
                        showChatError('No administrators are currently available. Please try again later or contact support via email.');
                    } else if (data.message && data.message.includes('maximum limit')) {
                        showChatError(data.message);
                        // Refresh conversations list to show current count
                        loadConversations();
                    } else {
                        showChatError(data.message || 'Failed to start conversation.');
                    }
                }
            } catch (error) {
                console.error('Error starting conversation:', error);
                showChatError('Failed to start conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Add this function to update conversation count display
        function updateConversationCount(count) {
            // Update the "New Conversation" button text
            const newChatBtn = document.getElementById('newChatBtn');
            if (newChatBtn) {
                const limitReached = count >= 3;
                newChatBtn.innerHTML = `<i class="fas fa-plus"></i> New Conversation (${count}/3)`;
                newChatBtn.disabled = limitReached;
                newChatBtn.title = limitReached ? 'Maximum 3 conversations reached' : 'Start a new conversation';

                if (limitReached) {
                    newChatBtn.classList.add('limit-reached');
                } else {
                    newChatBtn.classList.remove('limit-reached');
                }
            }

            // Also update conversation limit warning in conversations list
            const conversationsList = document.getElementById('conversationsList');
            if (conversationsList && count >= 3) {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (!warningElement) {
                    const warningDiv = document.createElement('div');
                    warningDiv.id = 'conversationLimitWarning';
                    warningDiv.className = 'conversation-limit-warning';
                    warningDiv.innerHTML = `
                    <div class="limit-warning-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Maximum conversations reached</strong>
                            <small>You have ${count} active conversations (maximum: 3). Please close or complete existing conversations to start new ones.</small>
                        </div>
                    </div>
                `;
                    conversationsList.parentNode.insertBefore(warningDiv, conversationsList);
                }
            } else {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (warningElement) {
                    warningElement.remove();
                }
            }
        }

        // Helper function to show system message
        function showSystemMessage(message) {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            const systemMessage = document.createElement('div');
            systemMessage.className = 'message-item system';
            systemMessage.innerHTML = `
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(message)}</div>
                <div class="message-time">${formatMessageTime(new Date().toISOString())}</div>
            </div>
        `;
            messagesList.appendChild(systemMessage);
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        // Add loading indicator
        function showChatLoading(show) {
            let loader = document.getElementById('chatLoader');
            if (!loader && show) {
                loader = document.createElement('div');
                loader.id = 'chatLoader';
                loader.className = 'chat-loader';
                loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                document.getElementById('chatMessages').prepend(loader);
            } else if (loader && !show) {
                loader.remove();
            }
        }

        // Show success message
        function showChatSuccess(message) {
            // Create success notification
            const successDiv = document.createElement('div');
            successDiv.className = 'chat-success-notification';
            successDiv.innerHTML = `
            <div class="success-content">
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;

            // Add to chat widget
            const chatBody = document.querySelector('.chat-body');
            if (chatBody) {
                chatBody.prepend(successDiv);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            } else {
                alert(message); // Fallback
            }
        }

        // Mark messages as read
        async function markAsRead(conversationId) {
            // This happens automatically when loading messages via the API
            updateUnreadCount();
        }

        // Update unread count
        async function updateUnreadCount() {
            try {
                const response = await fetch('../../api/chat_api.php?action=unread_count');
                const data = await response.json();

                if (data.success) {
                    const count = data.count || 0;
                    const chatCount = document.getElementById('chatCount');
                    if (chatCount) {
                        chatCount.textContent = count;
                        chatCount.style.display = count > 0 ? 'flex' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating unread count:', error);
            }
        }

        // Start auto-refresh
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    loadMessages(currentConversationId);
                }
                updateUnreadCount();
            }, 5000); // Refresh every 5 seconds
        }

        // Stop auto-refresh
        function stopChatRefresh() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
                chatRefreshInterval = null;
            }
        }

        // Helper functions
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 86400000) { // Less than 1 day
                return date.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } else if (diff < 604800000) { // Less than 1 week
                return date.toLocaleDateString([], {
                    weekday: 'short'
                });
            } else {
                return date.toLocaleDateString([], {
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function formatMessageTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        function autoResize(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        // Show chat error
        function showChatError(message) {
            // You can implement a notification system here
            console.error('Chat Error:', message);
            alert(message); // Simple alert for now
        }

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
    </script>
</body>

</html>