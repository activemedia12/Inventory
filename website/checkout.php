<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Get selected items from URL
$selected_items = isset($_GET['selected_items']) ? explode(',', $_GET['selected_items']) : [];

if (empty($selected_items)) {
    header("Location: view_cart.php");
    exit;
}

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username, 
                 pc.first_name, pc.middle_name, pc.last_name, pc.age, pc.gender, 
                 pc.birthdate, pc.contact_number AS personal_contact, pc.address_line1, pc.city AS personal_city, 
                 pc.province AS personal_province, pc.zip_code AS personal_zip,
                 cc.company_name, cc.contact_person, cc.contact_number AS company_contact,
                 cc.province AS company_province, cc.city AS company_city, cc.barangay, 
                 cc.subd_or_street, cc.building_or_block, cc.lot_or_room_no, cc.zip_code AS company_zip,
                 CASE 
                     WHEN pc.user_id IS NOT NULL THEN 'personal'
                     WHEN cc.user_id IS NOT NULL THEN 'company'
                     ELSE 'unknown'
                 END AS customer_type
          FROM users u
          LEFT JOIN personal_customers pc ON u.id = pc.user_id 
          LEFT JOIN company_customers cc ON u.id = cc.user_id 
          WHERE u.id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get cart items for selected items with all customization options
$placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
$query = "SELECT p.id, p.product_name, p.price as unit_price, p.category as product_group, 
                 ci.quantity, ci.item_id, ci.design_image,
                 ci.quoted_price, ci.price_updated_by_admin,
                 ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                 ci.finish_option, ci.paper_option, ci.binding_option,
                 ci.layout_option, ci.layout_details, ci.gsm_option, ci.user_layout_files,
                 -- Get option names from related tables
                 po.option_name as paper_option_name,
                 fo.option_name as finish_option_name,
                 bo.option_name as binding_option_name,
                 lo.option_name as layout_option_name,
                 -- Get other services option names using CASE statements
                 CASE 
                     WHEN p.category = 'Other Services' AND p.product_name = 'T-Shirts' THEN ts.size_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Tote Bag' THEN tos.size_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Paper Bag' THEN pbs.size_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Mug' THEN ms.size_name
                     ELSE NULL
                 END as size_option_name,
                 CASE 
                     WHEN p.category = 'Other Services' AND p.product_name = 'T-Shirts' THEN tc.color_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Tote Bag' THEN toc.color_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Mug' THEN mc.color_name
                     ELSE NULL
                 END as color_option_name,
                 pbs.dimensions as paperbag_dimensions
          FROM cart_items ci
          JOIN products_offered p ON ci.product_id = p.id
          JOIN carts c ON ci.cart_id = c.cart_id
          LEFT JOIN paper_options po ON ci.paper_option = po.id
          LEFT JOIN finish_options fo ON ci.finish_option = fo.id
          LEFT JOIN binding_options bo ON ci.binding_option = bo.id
          LEFT JOIN layout_options lo ON ci.layout_option = lo.id
          -- Left join all other services tables with specific conditions
          LEFT JOIN tshirt_sizes ts ON (p.category = 'Other Services' AND p.product_name = 'T-Shirts' AND ci.size_option = ts.id)
          LEFT JOIN tshirt_colors tc ON (p.category = 'Other Services' AND p.product_name = 'T-Shirts' AND ci.color_option = tc.id)
          LEFT JOIN totesize_options tos ON (p.category = 'Other Services' AND p.product_name = 'Tote Bag' AND ci.size_option = tos.id)
          LEFT JOIN totecolor_options toc ON (p.category = 'Other Services' AND p.product_name = 'Tote Bag' AND ci.color_option = toc.id)
          LEFT JOIN paperbag_size_options pbs ON (p.category = 'Other Services' AND p.product_name = 'Paper Bag' AND ci.size_option = pbs.id)
          LEFT JOIN mug_size_options ms ON (p.category = 'Other Services' AND p.product_name = 'Mug' AND ci.size_option = ms.id)
          LEFT JOIN mug_color_options mc ON (p.category = 'Other Services' AND p.product_name = 'Mug' AND ci.color_option = mc.id)
          WHERE c.user_id = ? AND ci.item_id IN ($placeholders)";
$stmt = $inventory->prepare($query);
$types = str_repeat('i', count($selected_items) + 1);
$stmt->bind_param($types, $user_id, ...$selected_items);
$stmt->execute();
$result = $stmt->get_result();

$checkout_items = [];
$subtotal = 0;
while ($row = $result->fetch_assoc()) {
    // Use admin price if available, otherwise use unit price
    $actual_price = $row['price_updated_by_admin'] && $row['quoted_price'] > 0 
        ? $row['quoted_price'] 
        : $row['unit_price'];
    
    $item_total = $actual_price * $row['quantity'];
    $subtotal += $item_total;
    
    // Add the actual price to the row for display
    $row['actual_price'] = $actual_price;
    $row['has_admin_price'] = $row['price_updated_by_admin'] && $row['quoted_price'] > 0;
    
    $checkout_items[] = $row;
}

$shipping = 0;
$tax = $subtotal * 0.03;
$total = $subtotal + $tax;

$cart_count = 0;
$query = "SELECT SUM(ci.quantity) as total_items 
          FROM cart_items ci 
          JOIN carts c ON ci.cart_id = c.cart_id 
          WHERE c.user_id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_cart = $stmt->get_result();
$row = $result_cart->fetch_assoc();

$cart_count = $row['total_items'] ? $row['total_items'] : 0;

// ---------------------------------------------------------------
// Presentation helpers (display only — the logic above is unchanged)
// ---------------------------------------------------------------
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';

function co_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Same product-group -> brand-ink mapping the cart and catalog use
function co_ink_for_group($group)
{
    $g = strtolower((string) $group);
    if (strpos($g, 'offset') !== false)  return 'black';
    if (strpos($g, 'digital') !== false) return 'cyan';
    if (strpos($g, 'riso') !== false)    return 'magenta';
    return 'yellow';
}

// <dd> with a soft "Not provided" fallback
$dd = function ($value, $wide = false) {
    $value = trim((string) $value);
    return $value !== ''
        ? '<dd>' . co_h($value) . '</dd>'
        : '<dd class="is-empty">Not provided</dd>';
};

$customer_type = $user_data['customer_type'] ?? 'unknown';

// One readable address line (no stray commas when parts are missing)
if ($customer_type === 'personal') {
    $address_parts = [
        $user_data['address_line1'] ?? '',
        $user_data['personal_city'] ?? '',
        trim(($user_data['personal_province'] ?? '') . ' ' . ($user_data['personal_zip'] ?? '')),
    ];
} else {
    $address_parts = [
        trim(($user_data['subd_or_street'] ?? '') . ' ' . ($user_data['building_or_block'] ?? '') . ' ' . ($user_data['lot_or_room_no'] ?? '')),
        $user_data['barangay'] ?? '',
        $user_data['company_city'] ?? '',
        trim(($user_data['company_province'] ?? '') . ' ' . ($user_data['company_zip'] ?? '')),
    ];
}
$address_text = implode(', ', array_filter(array_map('trim', $address_parts), 'strlen'));

$has_items = !empty($checkout_items);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body class="co-page">
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

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
                <a href="view_cart.php" class="cart-icon active" aria-current="page">
                    <span class="side-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count > 99 ? '99+' : $cart_count; ?></span>
                    </span>
                    <span class="side-nav-label">Cart</span>
                </a>
            </li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="../pages/website/profile.php" class="user-profile">
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
                <a href="../accounts/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="side-nav-label">Log Out</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Checkout Hero -->
    <section class="co-hero hide">
        <div class="container">
            <div class="co-hero__texture halftone"></div>
            <div class="co-hero-inner">
                <span class="section-eyebrow"><span class="reg-mark"></span> Checkout</span>
                <h1 class="co-hero-title">Review &amp; <span class="registered" data-text="pay.">pay.</span></h1>
                <p class="co-hero-sub">Review your order and complete your purchase. Pay with GCash, then upload your proof of payment.</p>
            </div>
        </div>
    </section>

    <!-- Checkout Main -->
    <section class="co-main hide">
        <div class="container">

            <ol class="co-steps" aria-label="Order progress">
                <li class="co-step is-done">
                    <span class="co-step__num"><i class="fas fa-check"></i></span>
                    <span><strong>Select your orders</strong><small>Tick the items you want to quote</small></span>
                </li>
                <li class="co-step is-done">
                    <span class="co-step__num"><i class="fas fa-check"></i></span>
                    <span><strong>We confirm the price</strong><small>Our team reviews each job</small></span>
                </li>
                <li class="co-step is-current" aria-current="step">
                    <span class="co-step__num">3</span>
                    <span><strong>Check out</strong><small>Pay with GCash and upload proof</small></span>
                </li>
            </ol>

            <div class="co-layout">

                <!-- Left column: order details -->
                <div class="co-stack">

                    <!-- Customer information -->
                    <section class="co-card" data-ink="cyan">
                        <div class="co-card__head">
                            <div class="co-card__title">
                                <span class="co-card__icon"><i class="fas fa-user"></i></span>
                                <div>
                                    <h2>Customer information</h2>
                                    <p>Where we'll reach you about this order.</p>
                                </div>
                            </div>
                            <a href="../pages/website/edit_profile.php" class="co-link co-noprint"><i class="fas fa-pen"></i> Edit details</a>
                        </div>

                        <dl class="co-info">
                            <?php if ($customer_type === 'personal'): ?>
                                <div>
                                    <dt><i class="fas fa-user"></i> Name</dt>
                                    <?php echo $dd($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                                </div>
                                <div>
                                    <dt><i class="fas fa-phone"></i> Contact</dt>
                                    <?php echo $dd($user_data['personal_contact']); ?>
                                </div>
                                <div class="is-wide">
                                    <dt><i class="fas fa-map-marker-alt"></i> Address</dt>
                                    <?php echo $dd($address_text); ?>
                                </div>
                            <?php elseif ($customer_type === 'company'): ?>
                                <div>
                                    <dt><i class="fas fa-building"></i> Company</dt>
                                    <?php echo $dd($user_data['company_name']); ?>
                                </div>
                                <div>
                                    <dt><i class="fas fa-user-tie"></i> Contact person</dt>
                                    <?php echo $dd($user_data['contact_person']); ?>
                                </div>
                                <div>
                                    <dt><i class="fas fa-phone"></i> Contact number</dt>
                                    <?php echo $dd($user_data['company_contact']); ?>
                                </div>
                                <div class="is-wide">
                                    <dt><i class="fas fa-map-marker-alt"></i> Address</dt>
                                    <?php echo $dd($address_text); ?>
                                </div>
                            <?php else: ?>
                                <div class="is-wide">
                                    <dt><i class="fas fa-info-circle"></i> Details</dt>
                                    <dd class="is-empty">No customer details found.</dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    </section>

                    <!-- Order summary -->
                    <section class="co-card" data-ink="magenta">
                        <div class="co-card__head">
                            <div class="co-card__title">
                                <span class="co-card__icon"><i class="fas fa-receipt"></i></span>
                                <div>
                                    <h2>Order summary</h2>
                                    <p><?php echo count($checkout_items); ?> <?php echo count($checkout_items) === 1 ? 'job' : 'jobs'; ?> in this order</p>
                                </div>
                            </div>
                            <a href="view_cart.php" class="co-link co-noprint"><i class="fas fa-arrow-left"></i> Back to cart</a>
                        </div>

                        <?php if (!$has_items): ?>
                            <div class="co-empty">
                                <i class="fas fa-box-open" aria-hidden="true"></i>
                                <p>We couldn't find those items in your cart.</p>
                                <a href="view_cart.php" class="btn btn-secondary">Back to cart</a>
                            </div>
                        <?php else: ?>
                            <div class="co-items">
                                <?php foreach ($checkout_items as $item):
                                    $ink = co_ink_for_group($item['product_group']);

                                    // Product type. `product_group` holds the category ("Other Services"),
                                    // so the product name is checked too, otherwise T-shirts, mugs and
                                    // bags were never recognised.
                                    $category   = strtolower($item['product_group'] . ' ' . $item['product_name']);
                                    $isTshirt   = strpos($category, 't-shirt') !== false || strpos($category, 'tshirt') !== false;
                                    $isTote     = strpos($category, 'tote') !== false;
                                    $isPaperBag = strpos($category, 'paper bag') !== false;
                                    $isMug      = strpos($category, 'mug') !== false;

                                    $image_path = "../assets/images/services/service-" . $item['id'] . ".jpg";
                                    $has_image  = file_exists($image_path);

                                    // ---- Customisation options -> chips ----
                                    $chips = [];

                                    if (!empty($item['size_option'])) {
                                        $size_label = $isTshirt ? 'T-Shirt Size' : ($isTote ? 'Tote Bag Size' : ($isPaperBag ? 'Paper Bag Size' : ($isMug ? 'Mug Size' : 'Size')));
                                        if (!empty($item['size_option_name'])) {
                                            $size_html = co_h($item['size_option_name']);
                                            if ($isPaperBag && !empty($item['paperbag_dimensions'])) {
                                                $size_html .= '<br><small>(' . co_h($item['paperbag_dimensions']) . ')</small>';
                                            }
                                        } else {
                                            $size_html = co_h($item['size_option']);
                                        }
                                        if (!empty($item['custom_size'])) {
                                            $size_html .= '<br><small>Custom: ' . co_h($item['custom_size']) . '</small>';
                                        }
                                        $chips[] = [$size_label, $size_html];
                                    }

                                    if (!empty($item['color_option'])) {
                                        $color_label = $isTshirt ? 'T-Shirt Color' : ($isTote ? 'Tote Bag Color' : ($isMug ? 'Mug Color' : 'Color'));
                                        $color_html  = !empty($item['color_option_name']) ? co_h($item['color_option_name']) : co_h($item['color_option']);
                                        if (!empty($item['custom_color'])) {
                                            $color_html .= '<br><small>Custom: ' . co_h($item['custom_color']) . '</small>';
                                        }
                                        $chips[] = [$color_label, $color_html];
                                    }

                                    // Printing options only apply to printing products
                                    if (!$isTshirt && !$isTote && !$isPaperBag && !$isMug) {
                                        if (!empty($item['finish_option_name'])) {
                                            $chips[] = ['Finish', co_h($item['finish_option_name'])];
                                        }
                                        if (!empty($item['paper_option_name'])) {
                                            $chips[] = ['Paper', co_h($item['paper_option_name'])];
                                        }
                                        if (!empty($item['binding_option_name'])) {
                                            $chips[] = ['Binding', co_h($item['binding_option_name'])];
                                        }
                                        if (!empty($item['layout_option_name'])) {
                                            $layout_html = co_h($item['layout_option_name']);
                                            if (!empty($item['layout_details'])) {
                                                $layout_html .= '<br><small>Details: ' . co_h($item['layout_details']) . '</small>';
                                            }
                                            $chips[] = ['Layout Type', $layout_html];
                                        }
                                        if (!empty($item['gsm_option'])) {
                                            $chips[] = ['GSM', co_h($item['gsm_option'])];
                                        }
                                    }

                                    // ---- Custom design previews ----
                                    $tiles = [];
                                    $uploadType = '';
                                    if (!empty($item['design_image'])) {
                                        $designData = $item['design_image'];
                                        $frontMockup = '';
                                        $backMockup = '';
                                        $uploadedFile = '';
                                        $frontUploadedFile = '';
                                        $backUploadedFile = '';
                                        $uploadType = '';

                                        // Check if it's JSON format
                                        $isJson = false;
                                        $designArray = json_decode($designData, true);

                                        if (json_last_error() === JSON_ERROR_NONE && is_array($designArray)) {
                                            $isJson = true;
                                            $uploadType = $designArray['upload_type'] ?? 'single';

                                            // Get ALL images - FIXED: Extract all file types
                                            $frontMockup = $designArray['front_mockup'] ?? '';
                                            $backMockup = $designArray['back_mockup'] ?? '';
                                            $uploadedFile = $designArray['uploaded_file'] ?? '';
                                            $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                            $backUploadedFile = $designArray['back_uploaded_file'] ?? '';

                                        } else {
                                            // Try to fix JSON if it's malformed
                                            if (preg_match('/\{.*\}/', $designData)) {
                                                $fixedJson = str_replace('\"', '"', $designData);
                                                $fixedJson = stripslashes($fixedJson);

                                                $designArray = json_decode($fixedJson, true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($designArray)) {
                                                    $isJson = true;
                                                    $uploadType = $designArray['upload_type'] ?? 'single';
                                                    $frontMockup = $designArray['front_mockup'] ?? '';
                                                    $backMockup = $designArray['back_mockup'] ?? '';
                                                    $uploadedFile = $designArray['uploaded_file'] ?? '';
                                                    $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                                    $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                                                }
                                            } else {
                                                // Legacy format - single image
                                                $uploadedFile = $designData;
                                                $uploadType = 'single';
                                            }
                                        }

                                        // Display design previews if we have valid images
                                        $hasDesigns = !empty($frontMockup) || !empty($backMockup) || !empty($uploadedFile) || !empty($frontUploadedFile) || !empty($backUploadedFile);

                                        if ($uploadType === 'single' && !empty($uploadedFile)) {
                                            $tiles[] = ['Original File', $uploadedFile, 'fa-file-image'];
                                        }
                                        if (!empty($frontUploadedFile)) {
                                            $tiles[] = ['Front Original', $frontUploadedFile, 'fa-file-image'];
                                        }
                                        if (!empty($backUploadedFile)) {
                                            $tiles[] = ['Back Original', $backUploadedFile, 'fa-file-image'];
                                        }
                                        if (!empty($frontMockup)) {
                                            $tiles[] = ['Front Mockup', $frontMockup, 'fa-image'];
                                        }
                                        if (!empty($backMockup)) {
                                            $tiles[] = ['Back Mockup', $backMockup, 'fa-image'];
                                        }
                                    }
                                ?>
                                    <article class="co-item" data-ink="<?php echo $ink; ?>">
                                        <div class="co-item__image">
                                            <?php if ($has_image): ?>
                                                <img src="<?php echo co_h($image_path); ?>" alt="<?php echo co_h($item['product_name']); ?>" loading="lazy">
                                            <?php else: ?>
                                                <i class="fas fa-image" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="co-item__body">
                                            <span class="co-item__group"><?php echo co_h($item['product_group']); ?></span>
                                            <h3 class="co-item__name"><?php echo co_h($item['product_name']); ?></h3>
                                            <p class="co-item__price">
                                                <?php if ($item['has_admin_price']): ?>
                                                    <s>₱<?php echo number_format($item['unit_price'], 2); ?></s>
                                                <?php endif; ?>
                                                <strong>₱<?php echo number_format($item['actual_price'], 2); ?></strong>
                                                <span>× <?php echo (int) $item['quantity']; ?></span>
                                                <?php if ($item['has_admin_price']): ?>
                                                    <span class="co-tag"><i class="fas fa-check"></i> Price confirmed by admin</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>

                                        <div class="co-item__total">₱<?php echo number_format($item['actual_price'] * $item['quantity'], 2); ?></div>

                                        <?php if (!empty($chips) || !empty($tiles)): ?>
                                            <div class="co-item__extras">
                                                <?php if (!empty($chips)): ?>
                                                    <div class="co-details">
                                                        <?php foreach ($chips as $chip): ?>
                                                            <div class="co-detail">
                                                                <span class="co-detail__label"><?php echo $chip[0]; ?></span>
                                                                <span class="co-detail__value"><?php echo $chip[1]; ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($tiles)): ?>
                                                    <div class="co-design">
                                                        <div class="co-design__title">
                                                            <span><i class="fas fa-palette"></i> Custom design</span>
                                                            <small>(<?php echo $uploadType === 'single' ? 'Same design for both sides' : 'Different designs for front/back'; ?>)</small>
                                                        </div>
                                                        <div class="co-design__tiles">
                                                            <?php foreach ($tiles as $tile):
                                                                $tile_path   = "../assets/uploads/" . $tile[1];
                                                                $tile_exists = file_exists($tile_path);
                                                            ?>
                                                                <figure class="co-tile">
                                                                    <?php if ($tile_exists): ?>
                                                                        <a href="<?php echo co_h($tile_path); ?>" target="_blank" rel="noopener">
                                                                            <img src="<?php echo co_h($tile_path); ?>" alt="<?php echo co_h($tile[0]); ?>" loading="lazy">
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <div class="co-tile__missing" title="Preview not available"><i class="fas <?php echo $tile[2]; ?>"></i></div>
                                                                    <?php endif; ?>
                                                                    <figcaption><?php echo co_h($tile[0]); ?></figcaption>
                                                                </figure>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>

                            <div class="co-totals">
                                <div class="co-totals__row">
                                    <span>Subtotal</span>
                                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="co-totals__row">
                                    <span>Tax (3%)</span>
                                    <span>₱<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <div class="co-totals__row co-totals__row--total">
                                    <span>Total amount</span>
                                    <span>₱<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>

                            <button type="button" class="btn btn-secondary co-print co-noprint" onclick="window.print()">
                                <i class="fas fa-print"></i> Print order summary
                            </button>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Right column: payment -->
                <aside class="co-pay">
                    <div class="co-pay__bar" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <div class="co-pay__body">
                        <span class="section-eyebrow"><span class="reg-mark"></span> Payment method</span>
                        <h2>Pay with InstaPay</h2>

                        <div class="co-amount">
                            <span>Amount to pay</span>
                            <strong>₱<?php echo number_format($total, 2); ?></strong>
                        </div>

                        <div class="co-payto">
                            <a href="../assets/images/gcash-qr.jpg" target="_blank" rel="noopener" class="co-qr" title="Open the QR code full size">
                                <img src="../assets/images/gcash-qr.jpg" alt="GCash QR Code">
                                <p style="font-size: 0.6rem; color: #666; text-align: center; margin-top: 0.5rem;">
                                    Transfer fees may apply.
                                </p>
                            </a>
                            <dl class="co-gcash">
                                <div>
                                    <dt>GCash number</dt>
                                    <dd>0998-791-6018</dd>
                                </div>
                                <div>
                                    <dt>Account name</dt>
                                    <dd>WI******A L.</dd>
                                </div>
                            </dl>
                        </div>

                        <form action="../pages/website/process_order.php" method="post" enctype="multipart/form-data" id="checkoutForm" novalidate>
                            <input type="hidden" name="selected_items" value="<?php echo co_h(implode(',', $selected_items)); ?>">
                            <input type="hidden" name="total_amount" value="<?php echo co_h($total); ?>">

                            <div class="co-upload" id="uploadBox">
                                <input type="file" name="payment_proof" id="payment_proof" accept="image/*,.pdf" class="co-upload__input" required>
                                <label for="payment_proof" class="co-upload__drop">
                                    <span class="co-upload__icon"><i class="fas fa-upload"></i></span>
                                    <span class="co-upload__text">
                                        <strong>Upload payment proof</strong>
                                        <small>Screenshot of your GCash payment confirmation (JPG, PNG or PDF, up to 5MB)</small>
                                    </span>
                                </label>
                                <div class="co-upload__file" id="uploadFile">
                                    <span class="co-upload__thumb" id="uploadThumb"><i class="fas fa-file-image"></i></span>
                                    <span class="co-upload__meta">
                                        <strong id="uploadName"></strong>
                                        <small id="uploadSize"></small>
                                    </span>
                                    <button type="button" class="co-upload__remove" id="uploadRemove" aria-label="Remove file">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <p class="co-upload__error" id="uploadError" role="alert" hidden></p>
                            </div>

                            <div class="co-howto">
                                <h3>Payment instructions</h3>
                                <ol>
                                    <li>Scan the QR code or send payment to our GCash number</li>
                                    <li>Take a screenshot of your payment confirmation</li>
                                    <li>Upload the screenshot as proof of payment</li>
                                    <li>Your order will be processed within 24 hours</li>
                                    <li>You will receive order updates via email/SMS</li>
                                </ol>
                            </div>

                        </form>
                    </div>

                    <div class="co-pay__foot">
                        <button type="submit" form="checkoutForm" class="btn btn-primary co-submit" id="confirm-order-btn"<?php echo $has_items ? '' : ' disabled'; ?>>
                            <i class="fas fa-check"></i> Confirm order
                        </button>
                    </div>
                </aside>

            </div>
        </div>
    </section>

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
                        <li><a href="main.php#offset">Offset Printing</a></li>
                        <li><a href="main.php#digital">Digital Printing</a></li>
                        <li><a href="main.php#riso">RISO Printing</a></li>
                        <li><a href="main.php#other">Other Services</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="about.php">Our Team</a></li>
                        <li><a href="about.php">Careers</a></li>
                        <li><a href="about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="contact.php">FAQ</a></li>
                        <li><a href="contact.php">Shipping Info</a></li>
                        <li><a href="contact.php">Returns</a></li>
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

    <script src="../assets/js/main.js"></script>
    <script>
        // Checkout: payment-proof upload (validation, preview, drag & drop) and submit state
        (function () {
            'use strict';

            var form      = document.getElementById('checkoutForm');
            if (!form) return;

            var input     = document.getElementById('payment_proof');
            var box       = document.getElementById('uploadBox');
            var thumb     = document.getElementById('uploadThumb');
            var nameEl    = document.getElementById('uploadName');
            var sizeEl    = document.getElementById('uploadSize');
            var removeBtn = document.getElementById('uploadRemove');
            var errorEl   = document.getElementById('uploadError');
            var submitBtn = document.getElementById('confirm-order-btn');
            var submitHtml = submitBtn ? submitBtn.innerHTML : '';
            var MAX_MB    = 5;
            var previewUrl = null;

            function showError(message) {
                errorEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + message;
                errorEl.hidden = false;
                box.classList.add('has-error');
            }

            function clearError() {
                errorEl.hidden = true;
                errorEl.textContent = '';
                box.classList.remove('has-error');
            }

            function resetFile() {
                input.value = '';
                box.classList.remove('has-file');
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = null;
                }
            }

            function formatSize(bytes) {
                return bytes >= 1048576
                    ? (bytes / 1048576).toFixed(1) + ' MB'
                    : Math.max(1, Math.round(bytes / 1024)) + ' KB';
            }

            function handleFile(file) {
                clearError();
                if (!file) {
                    resetFile();
                    return;
                }

                // Validate file type
                if (file.type.indexOf('image/') !== 0 && file.type !== 'application/pdf') {
                    resetFile();
                    showError('Please upload only image files (JPG, PNG) or PDF files.');
                    return;
                }

                // Validate file size (max 5MB)
                if (file.size / 1024 / 1024 > MAX_MB) {
                    resetFile();
                    showError('File size must be less than 5MB.');
                    return;
                }

                // Show what was chosen
                nameEl.textContent = file.name;
                sizeEl.textContent = formatSize(file.size);
                if (previewUrl) URL.revokeObjectURL(previewUrl);

                if (file.type.indexOf('image/') === 0) {
                    previewUrl = URL.createObjectURL(file);
                    thumb.innerHTML = '<img src="' + previewUrl + '" alt="">';
                } else {
                    previewUrl = null;
                    thumb.innerHTML = '<i class="fas fa-file-pdf"></i>';
                }
                box.classList.add('has-file');
            }

            input.addEventListener('change', function () {
                handleFile(input.files[0]);
            });

            removeBtn.addEventListener('click', function () {
                resetFile();
                clearError();
                input.focus();
            });

            // Drag & drop onto the drop area
            ['dragenter', 'dragover'].forEach(function (type) {
                box.addEventListener(type, function (e) {
                    e.preventDefault();
                    box.classList.add('is-drag');
                });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                box.addEventListener(type, function (e) {
                    e.preventDefault();
                    box.classList.remove('is-drag');
                });
            });
            box.addEventListener('drop', function (e) {
                if (!e.dataTransfer || !e.dataTransfer.files.length) return;
                try {
                    input.files = e.dataTransfer.files;
                } catch (err) {
                    return;
                }
                handleFile(input.files[0]);
            });

            // Form submission handling
            form.addEventListener('submit', function (e) {
                if (!input.files.length) {
                    e.preventDefault();
                    showError('Please upload your payment proof before confirming the order.');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    input.focus();
                    return;
                }

                // Show loading state; the form then submits normally
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }
            });

            // Coming back with the Back button shouldn't leave the button stuck on "Processing..."
            window.addEventListener('pageshow', function (e) {
                if (e.persisted && submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitHtml;
                }
            });
        })();
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
                const response = await fetch('../api/chat_api.php?action=conversations');
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
                const response = await fetch('../api/chat_api.php?action=conversation_limit');
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

                const response = await fetch('../api/chat_api.php', {
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
                const response = await fetch(`../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
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
                const response = await fetch('../api/chat_api.php', {
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

                const response = await fetch('../api/chat_api.php', {
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
                const response = await fetch('../api/chat_api.php?action=unread_count');
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