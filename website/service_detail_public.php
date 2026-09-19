<?php
session_start();
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
require_once '../config/db.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id === 0) {
    header("Location: sub-main.php");
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
    header("Location: sub-main.php");
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

// Get base image paths
$base_image_path = "../assets/images/base/base-" . $product['id'] . ".jpg";
$base_image_url = file_exists($base_image_path) ? $base_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Base+Image";
$back_base_image_path = "../assets/images/base/base-" . $product['id'] . "-1.jpg";
$back_base_image_url = file_exists($back_base_image_path) ? $back_base_image_path : "";

// Get product images for gallery display
$product_image_path = "../assets/images/services/service-" . $product['id'] . ".jpg";
$product_image_url = file_exists($product_image_path) ? $product_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Product+Image";
$product_back_image_path = "../assets/images/services/service-" . $product['id'] . "-1.jpg";
$product_back_image_url = file_exists($product_back_image_path) ? $product_back_image_path : "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/service_detail.css">
</head>
<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="sub-main.php" class="active"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="sub-ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="sub-about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="sub-contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li><a href="../accounts/login.php" class="log"><i class="fas fa-right-to-bracket"></i><span class="side-nav-label">Login</span></a></li>
            <li><a href="../accounts/customer.php" class="sign"><i class="fas fa-user-plus"></i><span class="side-nav-label">Sign Up</span></a></li>
        </ul>
    </nav>

    <?php
    // Collect up to 5 product images: service-ID.jpg, service-ID-1.jpg ...
$product_images = [];
for ($i = 0; $i < 5; $i++) {
    $suffix = $i > 0 ? '-' . $i : '';
    $image_path = "../assets/images/services/service-" . $product['id'] . $suffix . ".jpg";

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
                <a href="sub-main.php">Home</a>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <a href="sub-main.php#<?php echo $cat_meta['anchor']; ?>"><?php echo htmlspecialchars($product['category']); ?></a>
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                <span aria-current="page"><?php echo htmlspecialchars($product['product_name']); ?></span>
            </nav>

            <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Order form for logged in users -->
            <form method="post" id="cartForm" action="add_to_cart.php" enctype="multipart/form-data">
                <input type="hidden" name="add_to_cart" value="1">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

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
                        <p class="pd-price"><span class="pd-price__from">From</span><span class="pd-price__amt">₱<?php echo number_format($product['price'], 2); ?></span></p>
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
                                <!-- Upload type selection -->
                                <div class="upload-type-section">
                                    <h3 class="pd-sub"><i class="fas fa-upload"></i> Upload type</h3>
                                    <div class="upload-type-buttons" role="radiogroup" aria-label="Upload type">
                                        <label>
                                            <input type="radio" name="upload_type" value="single" checked onchange="handleUploadTypeChange()">
                                            <span>Same on both sides</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="upload_type" value="separate" onchange="handleUploadTypeChange()">
                                            <span>Different per side</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Single image upload (default) -->
                                <div class="image-upload-section" id="singleUploadSection">
                                    <label for="designUpload" class="upload-btn required-field">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span class="upload-text">Choose design image (for both sides)</span>
                                    </label>
                                    <input type="file" id="designUpload" name="design_upload" accept="image/*" hidden>
                                    <div class="upload-preview" id="uploadPreview">
                                        <img src="" alt="Uploaded design" class="uploaded-image" id="uploadedImage">
                                        <button type="button" class="btn-remove-design" onclick="removeUploadedImage()">
                                            <i class="fas fa-trash"></i> Remove image
                                        </button>
                                    </div>
                                </div>

                                <!-- Separate image upload (hidden by default) -->
                                <div class="separate-upload-section" id="separateUploadSection" style="display: none;">
                                    <div class="front-upload">
                                        <h3 class="pd-sub"><i class="fas fa-tshirt"></i> Front design</h3>
                                        <label for="frontDesignUpload" class="upload-btn required-field">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span class="upload-text">Choose front design</span>
                                        </label>
                                        <input type="file" id="frontDesignUpload" name="front_design_upload" accept="image/*" hidden>
                                        <div class="upload-preview" id="frontUploadPreview">
                                            <img src="" alt="Front design" class="uploaded-image" id="frontUploadedImage">
                                            <button type="button" class="btn-remove-design" onclick="removeFrontUploadedImage()">
                                                <i class="fas fa-trash"></i> Remove front image
                                            </button>
                                        </div>
                                    </div>

                                    <?php if (!empty($back_base_image_url)): ?>
                                        <div class="back-upload">
                                            <h3 class="pd-sub"><i class="fas fa-tshirt"></i> Back design</h3>
                                            <label for="backDesignUpload" class="upload-btn required-field">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                                <span class="upload-text">Choose back design</span>
                                            </label>
                                            <input type="file" id="backDesignUpload" name="back_design_upload" accept="image/*" hidden>
                                            <div class="upload-preview" id="backUploadPreview">
                                                <img src="" alt="Back design" class="uploaded-image" id="backUploadedImage">
                                                <button type="button" class="btn-remove-design" onclick="removeBackUploadedImage()">
                                                    <i class="fas fa-trash"></i> Remove back image
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                            <button type="button" class="tool-btn" onclick="enableDragging()" id="dragBtn">
                                <i class="fas fa-arrows-alt"></i> Move design
                            </button>
                            <button type="button" class="tool-btn" onclick="resizeDesign(1.1)">
                                <i class="fas fa-search-plus"></i> Enlarge
                            </button>
                            <button type="button" class="tool-btn" onclick="resizeDesign(0.9)">
                                <i class="fas fa-search-minus"></i> Shrink
                            </button>
                            <button type="button" class="tool-btn" onclick="resetDesignPosition()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="button" class="tool-btn" onclick="toggleBoundary()" id="boundaryBtn">
                                <i class="fas fa-border-all"></i> Show boundaries
                            </button>
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

                        <div class="preview-section">
                    <div class="preview-container">
                        <img src="" alt="Mockup preview" class="mockup-preview" id="mockupPreview">
                        <p id="previewText">Upload an image to generate a preview</p>
                    </div>
                    <div class="pd-preview-actions">
                        <h3 class="pd-sub"><i class="fas fa-eye"></i> Design preview</h3>
                        <p class="pd-note">Generate a mockup to check how your design sits on the product, then apply it before adding to cart.</p>
                        <button type="button" class="btn pd-btn-ink" onclick="generateMockup()">
                            <i class="fas fa-image"></i> Generate mockup
                        </button>
                    </div>
                </div>
                    </section>
                    <?php endif; ?>

                <div class="pd-orderbar">
                    <div class="pd-orderbar__what">
                        <span class="pd-orderbar__name"><?php echo htmlspecialchars($product['product_name']); ?></span>
                        <span class="pd-orderbar__price">From ₱<?php echo number_format($product['price'], 2); ?></span>
                    </div>
                    <div class="quantity-selector">
                        <button type="button" class="quantity-btn" onclick="decreaseQuantity()" aria-label="Decrease quantity"><i class="fas fa-minus"></i></button>
                        <input type="number" name="quantity" class="quantity-input" id="quantity" value="1" min="1" aria-label="Quantity">
                        <button type="button" class="quantity-btn" onclick="increaseQuantity()" aria-label="Increase quantity"><i class="fas fa-plus"></i></button>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Add to cart
                    </button>
                </div>

                <input type="hidden" name="design_image" id="designImageInput" value="">
                <input type="hidden" name="front_design_image" id="frontDesignImageInput" value="">
                <input type="hidden" name="back_design_image" id="backDesignImageInput" value="">
                <input type="hidden" name="upload_type" id="uploadTypeInput" value="single">
            </form>
            <?php else: ?>
            <!-- Guests: product overview + login prompt -->
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
                        <p class="pd-price"><span class="pd-price__from">From</span><span class="pd-price__amt">₱<?php echo number_format($product['price'], 2); ?></span></p>
                    </header>

                    <div class="login-prompt">
                                <div class="login-message">
                                    <i class="fas fa-info-circle"></i>
                                    <h3>Please login to place an order</h3>
                                    <p>You need to be logged in to customize and add items to your cart.</p>
                                </div>
                                <div class="hero-actions">
                                    <a href="../accounts/login.php?redirect=<?php echo urlencode('service_detail_public.php?id=' . $product['id']); ?>" class="btn btn-primary">
                                        <i class="fas fa-sign-in-alt"></i> Login to Order
                                    </a>
                                    <a href="../accounts/customer.php" class="btn btn-secondary">
                                        <i class="fas fa-user-plus"></i> Sign Up for Free!
                                    </a>
                                </div>
                            </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Mockup Popup (only for logged in users) -->
    <?php if (isset($_SESSION['user_id'])): ?>
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
                <button type="button" class="btn btn-primary" onclick="useThisDesign()">
                    <i class="fas fa-check"></i> Use this design
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                        <li><a href="sub-main.php#offset">Offset Printing</a></li>
                        <li><a href="sub-main.php#digital">Digital Printing</a></li>
                        <li><a href="sub-main.php#riso">RISO Printing</a></li>
                        <li><a href="sub-main.php#other">Other Services</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="sub-about.php">About Us</a></li>
                        <li><a href="sub-about.php">Our Team</a></li>
                        <li><a href="sub-about.php">Careers</a></li>
                        <li><a href="sub-about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="sub-contact.php">Contact Us</a></li>
                        <li><a href="sub-contact.php">FAQ</a></li>
                        <li><a href="sub-contact.php">Shipping Info</a></li>
                        <li><a href="sub-contact.php">Returns</a></li>
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

    <script src="../assets/js/main.js"></script>
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
        // Global variables
        let userDesign = null;
        let frontUserDesign = null;
        let backUserDesign = null;
        let currentMockup = null;
        let backMockup = null;
        let isDraggingEnabled = false;
        let currentDesign = null;
        let currentView = 'front';
        let currentUploadType = 'single';
        let frontDesignPosition = {
            x: 100,
            y: 100,
            width: 200,
            height: 200
        };
        let backDesignPosition = {
            x: 100,
            y: 100,
            width: 200,
            height: 200
        };
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
            <?php if (isset($_SESSION['user_id'])): ?>
                // Setup design upload listeners only for logged in users
                document.getElementById('designUpload').addEventListener('change', function(e) {
                    handleSingleDesignUpload(e);
                });

                // Front design upload
                document.getElementById('frontDesignUpload').addEventListener('change', function(e) {
                    handleSeparateDesignUpload(e, 'front');
                });

                // Back design upload
                document.getElementById('backDesignUpload').addEventListener('change', function(e) {
                    handleSeparateDesignUpload(e, 'back');
                });

                // Close popup when clicking outside
                window.addEventListener('click', function(event) {
                    if (event.target === document.getElementById('mockupPopup')) {
                        closeModal();
                    }
                });

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
            <?php endif; ?>
        });

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

        // Handle upload type change
        function handleUploadTypeChange() {
            const uploadType = document.querySelector('input[name="upload_type"]:checked').value;
            currentUploadType = uploadType;
            document.getElementById('uploadTypeInput').value = uploadType;

            if (uploadType === 'single') {
                document.getElementById('singleUploadSection').style.display = 'block';
                document.getElementById('separateUploadSection').style.display = 'none';
                // Reset separate uploads when switching to single
                removeFrontUploadedImage();
                removeBackUploadedImage();
                // Switch to front view
                switchView('front');
            } else {
                document.getElementById('singleUploadSection').style.display = 'none';
                document.getElementById('separateUploadSection').style.display = 'block';
                // Reset single upload when switching to separate
                removeUploadedImage();
            }
        }

        // Handle single design upload with quality preservation
        function handleSingleDesignUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('uploadedImage').src = event.target.result;
                document.getElementById('uploadPreview').style.display = 'block';
                
                // Create a high-quality image object
                userDesign = new Image();
                userDesign.onload = function() {
                    console.log('Original image dimensions:', userDesign.width, 'x', userDesign.height);
                    
                    // Initialize the design overlay with the uploaded image
                    initDesignOverlay(event.target.result);
                    
                    document.getElementById('previewText').style.display = 'none';
                    document.getElementById('mockupPreview').style.display = 'block';
                    document.getElementById('mockupPreview').src = event.target.result;
                    
                    // Calculate the image boundary after the image is loaded
                    calculateImageBoundary();
                };
                userDesign.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }

        // Handle separate design upload
        function handleSeparateDesignUpload(e, side) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const previewId = side + 'UploadPreview';
                const imageId = side + 'UploadedImage';

                document.getElementById(imageId).src = event.target.result;
                document.getElementById(previewId).style.display = 'block';

                // Store the design
                if (side === 'front') {
                    frontUserDesign = new Image();
                    frontUserDesign.src = event.target.result;
                    frontUserDesign.onload = function() {
                        // If we're currently viewing front, update the overlay
                        if (currentView === 'front') {
                            initDesignOverlay(event.target.result);
                        }
                        calculateImageBoundary();
                        updatePreviewForSeparateDesigns();
                    };
                } else {
                    backUserDesign = new Image();
                    backUserDesign.src = event.target.result;
                    backUserDesign.onload = function() {
                        // If we're currently viewing back, update the overlay
                        if (currentView === 'back') {
                            initDesignOverlay(event.target.result);
                        }
                        calculateImageBoundary();
                        updatePreviewForSeparateDesigns();
                    };
                }
            };
            reader.readAsDataURL(file);
        }

        // Update preview for separate designs
        function updatePreviewForSeparateDesigns() {
            const previewText = document.getElementById('previewText');
            const mockupPreview = document.getElementById('mockupPreview');

            if (currentView === 'front' && frontUserDesign) {
                previewText.style.display = 'none';
                mockupPreview.style.display = 'block';
                mockupPreview.src = frontUserDesign.src;
            } else if (currentView === 'back' && backUserDesign) {
                previewText.style.display = 'none';
                mockupPreview.style.display = 'block';
                mockupPreview.src = backUserDesign.src;
            } else {
                previewText.style.display = 'block';
                mockupPreview.style.display = 'none';
            }
        }

        // Remove functions for separate designs
        function removeFrontUploadedImage() {
            document.getElementById('frontDesignUpload').value = '';
            document.getElementById('frontUploadPreview').style.display = 'none';
            frontUserDesign = null;
            resetDesignOverlay();
            updatePreviewForSeparateDesigns();
        }

        function removeBackUploadedImage() {
            document.getElementById('backDesignUpload').value = '';
            document.getElementById('backUploadPreview').style.display = 'none';
            backUserDesign = null;
            resetDesignOverlay();
            updatePreviewForSeparateDesigns();
        }

        // Update the existing remove function
        function removeUploadedImage() {
            document.getElementById('designUpload').value = '';
            document.getElementById('uploadPreview').style.display = 'none';
            document.getElementById('previewText').style.display = 'block';
            document.getElementById('mockupPreview').style.display = 'none';
            userDesign = null;
            resetDesignOverlay();
        }

        // Helper function to reset design overlay
        function resetDesignOverlay() {
            const overlay = document.getElementById('designOverlay');
            overlay.innerHTML = '';
            currentDesign = null;
            isDraggingEnabled = false;
            document.getElementById('dragBtn').innerHTML = '<i class="fas fa-arrows-alt"></i> Move design';
            document.getElementById('dragBtn').classList.remove('is-on');
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

            // Change the base image to use base template, not product image
            const baseImage = document.getElementById('baseImage');
            if (view === 'front') {
                baseImage.src = frontImageUrl;
            } else {
                baseImage.src = backImageUrl;
            }

            // Reinitialize design overlay for the current view based on upload type
            if (currentUploadType === 'single' && userDesign) {
                initDesignOverlay(userDesign.src);
            } else if (currentUploadType === 'separate') {
                if (view === 'front' && frontUserDesign) {
                    initDesignOverlay(frontUserDesign.src);
                } else if (view === 'back' && backUserDesign) {
                    initDesignOverlay(backUserDesign.src);
                } else {
                    resetDesignOverlay();
                }
            } else {
                resetDesignOverlay();
            }

            // Update preview
            updatePreviewForSeparateDesigns();

            // Recalculate boundaries
            setTimeout(calculateImageBoundary, 100);
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

        // Change main image when thumbnail is clicked (gallery only)
        function changeImage(element, view) {
            // Only update the gallery image, NOT the base image
            document.getElementById('mainImage').src = element.src;
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');

            // Don't change the base image - it should stay as the template
            // The base image should only show the template for positioning
        }

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

        // Enable/disable dragging
        function enableDragging() {
            if (currentUploadType === 'single' && !userDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate') {
                if (currentView === 'front' && !frontUserDesign) {
                    alert('Please upload a front design image first!');
                    return;
                }
                if (currentView === 'back' && !backUserDesign) {
                    alert('Please upload a back design image first!');
                    return;
                }
            }

            isDraggingEnabled = !isDraggingEnabled;
            const btn = document.getElementById('dragBtn');

            if (isDraggingEnabled) {
                btn.classList.add('is-on');
                btn.innerHTML = '<i class="fas fa-hand-paper"></i> Dragging on';

                if (currentDesign) {
                    currentDesign.style.cursor = 'move';
                    currentDesign.style.pointerEvents = 'auto';
                }
            } else {
                btn.classList.remove('is-on');
                btn.innerHTML = '<i class="fas fa-arrows-alt"></i> Move design';

                if (currentDesign) {
                    currentDesign.style.cursor = 'default';
                    currentDesign.style.pointerEvents = 'none';
                }
            }
        }

        // Initialize design overlay with better positioning
        function initDesignOverlay(imageSrc) {
            const overlay = document.getElementById('designOverlay');
            overlay.innerHTML = '';
            
            // Get the current design position for the active view
            const designPosition = currentView === 'front' ? frontDesignPosition : backDesignPosition;

            // Keep the starting box inside the printable area, whatever the layout width is
            calculateImageBoundary(); // make sure the boundary reflects the current canvas size
            if (imageBoundary.width > 0 && imageBoundary.height > 0) {
                designPosition.width = Math.min(designPosition.width, imageBoundary.width);
                designPosition.height = Math.min(designPosition.height, imageBoundary.height);
                designPosition.x = Math.min(Math.max(designPosition.x, imageBoundary.x), imageBoundary.x + imageBoundary.width - designPosition.width);
                designPosition.y = Math.min(Math.max(designPosition.y, imageBoundary.y), imageBoundary.y + imageBoundary.height - designPosition.height);
            }
            
            // Create design element with high-quality rendering
            const designElement = document.createElement('div');
            designElement.className = 'draggable-design';
            designElement.style.backgroundImage = `url(${imageSrc})`;
            designElement.style.backgroundSize = 'contain';
            designElement.style.backgroundRepeat = 'no-repeat';
            designElement.style.backgroundPosition = 'center';
            designElement.style.width = `${designPosition.width}px`;
            designElement.style.height = `${designPosition.height}px`;
            designElement.style.left = `${designPosition.x}px`;
            designElement.style.top = `${designPosition.y}px`;
            
            // Add resize handle
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle';
            designElement.appendChild(resizeHandle);
            
            // Add event listeners for dragging
            designElement.addEventListener('mousedown', startDrag);
            resizeHandle.addEventListener('mousedown', startResize);
            
            overlay.appendChild(designElement);
            currentDesign = designElement;
            
            // Enable dragging by default when design is loaded
            isDraggingEnabled = true;
            document.getElementById('dragBtn').classList.add('is-on');
            document.getElementById('dragBtn').innerHTML = '<i class="fas fa-hand-paper"></i> Dragging on';
            designElement.style.cursor = 'move';
            designElement.style.pointerEvents = 'auto';
        }

        // Start dragging
        function startDrag(e) {
            if (!isDraggingEnabled) return;
            if (e.target.classList.contains('resize-handle')) return;

            e.preventDefault();
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

            // Save the position for the current view
            if (currentView === 'front') {
                frontDesignPosition.x = parseInt(currentDesign.style.left);
                frontDesignPosition.y = parseInt(currentDesign.style.top);
            } else {
                backDesignPosition.x = parseInt(currentDesign.style.left);
                backDesignPosition.y = parseInt(currentDesign.style.top);
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

        // Perform resizing with boundary constraints
        function doResize(e) {
            if (!isResizing) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            let newWidth = Math.max(50, initialDesignPosition.width + dx);
            let newHeight = Math.max(50, initialDesignPosition.height + dy);

            // Apply boundary constraints during resizing
            const maxWidth = imageBoundary.x + imageBoundary.width - initialDesignPosition.x;
            const maxHeight = imageBoundary.y + imageBoundary.height - initialDesignPosition.y;

            newWidth = Math.min(newWidth, maxWidth);
            newHeight = Math.min(newHeight, maxHeight);

            currentDesign.style.width = `${newWidth}px`;
            currentDesign.style.height = `${newHeight}px`;
        }

        // Stop resizing
        function stopResize() {
            isResizing = false;

            // Save the size for the current view
            if (currentView === 'front') {
                frontDesignPosition.width = parseInt(currentDesign.style.width);
                frontDesignPosition.height = parseInt(currentDesign.style.height);
            } else {
                backDesignPosition.width = parseInt(currentDesign.style.width);
                backDesignPosition.height = parseInt(currentDesign.style.height);
            }

            document.removeEventListener('mousemove', doResize);
            document.removeEventListener('mouseup', stopResize);
        }

        // Resize design with buttons
        function resizeDesign(factor) {
            if (currentUploadType === 'single' && !currentDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate' && !currentDesign) {
                alert('Please upload a design image for the current view first!');
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

            newWidth = Math.min(newWidth, maxWidth);
            newHeight = Math.min(newHeight, maxHeight);

            currentDesign.style.width = `${newWidth}px`;
            currentDesign.style.height = `${newHeight}px`;

            // Save the size for the current view
            if (currentView === 'front') {
                frontDesignPosition.width = newWidth;
                frontDesignPosition.height = newHeight;
            } else {
                backDesignPosition.width = newWidth;
                backDesignPosition.height = newHeight;
            }
        }

        // Reset design position
        function resetDesignPosition() {
            if (currentUploadType === 'single' && !currentDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate' && !currentDesign) {
                alert('Please upload a design image for the current view first!');
                return;
            }

            // Center the design within the image boundary
            const newPosition = {
                x: imageBoundary.x + (imageBoundary.width - 200) / 2,
                y: imageBoundary.y + (imageBoundary.height - 200) / 2,
                width: 200,
                height: 200
            };

            currentDesign.style.width = `${newPosition.width}px`;
            currentDesign.style.height = `${newPosition.height}px`;
            currentDesign.style.left = `${newPosition.x}px`;
            currentDesign.style.top = `${newPosition.y}px`;

            // Save the position for the current view
            if (currentView === 'front') {
                frontDesignPosition = {
                    ...newPosition
                };
            } else {
                backDesignPosition = {
                    ...newPosition
                };
            }
        }

        // Generate mockup preview
        function generateMockup() {
            if (currentUploadType === 'single' && !userDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate') {
                if (!frontUserDesign && !backUserDesign) {
                    alert('Please upload at least one design image!');
                    return;
                }
            }

            // Use base images as templates, not product images
            const frontTemplate = "<?php echo $base_image_url; ?>";
            const backTemplate = "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>";

            // Show loading state
            document.querySelectorAll('.mockup-image').forEach(el => el.style.display = 'none');

            // Generate front mockup if design exists
            if (currentUploadType === 'single' && userDesign) {
                generateSingleMockup(
                    userDesign,
                    frontTemplate,
                    'mockupFront',
                    'frontMockupContainer',
                    frontDesignPosition
                );
            } else if (currentUploadType === 'separate' && frontUserDesign) {
                generateSingleMockup(
                    frontUserDesign,
                    frontTemplate,
                    'mockupFront',
                    'frontMockupContainer',
                    frontDesignPosition
                );
            } else {
                document.getElementById('frontMockupContainer').style.display = 'none';
            }

            // Generate back mockup if template and design exist
            if (backTemplate) {
                if (currentUploadType === 'single' && userDesign) {
                    generateSingleMockup(
                        userDesign,
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        backDesignPosition
                    );
                } else if (currentUploadType === 'separate' && backUserDesign) {
                    generateSingleMockup(
                        backUserDesign,
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        backDesignPosition
                    );
                } else {
                    document.getElementById('backMockupContainer').style.display = 'none';
                }
            } else {
                document.getElementById('backMockupContainer').style.display = 'none';
            }

            document.getElementById('mockupPopup').style.display = 'flex';
        }

        // Generate single mockup by embedding design onto product template WITHOUT compression
        function generateSingleMockup(designImage, templatePath, outputId, containerId, position) {
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
                const container = document.querySelector('.positioning-container');
                const baseImg = document.getElementById('baseImage');
                
                // Get the actual displayed image dimensions within the boundary
                const scaleX = productTemplate.width / imageBoundary.width;
                const scaleY = productTemplate.height / imageBoundary.height;
                
                // Calculate position relative to the actual image boundary
                const x = (position.x - imageBoundary.x) * scaleX;
                const y = (position.y - imageBoundary.y) * scaleY;
                const width = position.width * scaleX;
                const height = position.height * scaleY;

                // Draw user's design on top of the product template with high quality
                ctx.save();
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(designImage, x, y, width, height);
                ctx.restore();

                // Output the combined image to the mockup preview
                const finalImage = canvas.toDataURL('image/png', 1.0); // Maximum quality
                document.getElementById(outputId).src = finalImage;
                document.getElementById(containerId).style.display = 'block';
                
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

        // Use this design
        function useThisDesign() {
            if (currentUploadType === 'single' && currentMockup) {
                saveBothDesigns(currentMockup, currentMockup).then(designData => {
                    if (designData.front_mockup && designData.front_mockup !== 'error') {
                        const completeDesignData = {
                            front_mockup: designData.front_mockup || '',
                            back_mockup: designData.back_mockup || '',
                            uploaded_file: designData.uploaded_file || '',
                            upload_type: 'single'
                        };

                        // Validate the JSON before storing
                        const designDataString = JSON.stringify(completeDesignData);
                        console.log('Single design data to save:', designDataString); // Debug

                        // Store as JSON string
                        document.getElementById('designImageInput').value = designDataString;
                        alert('Design applied successfully! You can now add to cart.');
                        closeModal();
                    } else {
                        alert('Error saving design. Please try again.');
                    }
                });
            } else if (currentUploadType === 'separate') {
                // For separate uploads, we need to ensure both mockups exist
                if (!currentMockup && !backMockup) {
                    alert('Please generate mockups for both front and back designs first!');
                    return;
                }

                saveBothDesigns(
                    currentMockup || '',
                    backMockup || ''
                ).then(designData => {
                    console.log('Separate design save response:', designData); // Debug

                    if ((designData.front_mockup && designData.front_mockup !== 'error') ||
                        (designData.back_mockup && designData.back_mockup !== 'error')) {

                        const completeDesignData = {
                            front_mockup: designData.front_mockup || '',
                            back_mockup: designData.back_mockup || '',
                            front_uploaded_file: designData.front_uploaded_file || '',
                            back_uploaded_file: designData.back_uploaded_file || '',
                            upload_type: 'separate'
                        };

                        // Validate the JSON before storing
                        const designDataString = JSON.stringify(completeDesignData);
                        console.log('Separate design data to save:', designDataString); // Debug
                        console.log('JSON length:', designDataString.length); // Debug

                        // Store as JSON string in BOTH hidden inputs for safety
                        document.getElementById('frontDesignImageInput').value = designDataString;
                        document.getElementById('backDesignImageInput').value = designDataString;

                        alert('Designs applied successfully! You can now add to cart.');
                        closeModal();
                    } else {
                        alert('Error saving designs. Please try again.');
                    }
                });
            }
        }

        // Helper function to save designs and uploaded files
        async function saveBothDesigns(frontImageData, backImageData) {
            try {
                const formData = new FormData();

                // Add mockup images
                if (frontImageData) {
                    formData.append('front_image', frontImageData);
                }
                if (backImageData) {
                    formData.append('back_image', backImageData);
                }

                // Add the actual uploaded files based on upload type
                if (currentUploadType === 'single') {
                    const singleUploadInput = document.getElementById('designUpload');
                    if (singleUploadInput && singleUploadInput.files[0]) {
                        formData.append('design_file', singleUploadInput.files[0]);
                    }
                } else {
                    // For separate uploads, save both front and back files
                    const frontUploadInput = document.getElementById('frontDesignUpload');
                    if (frontUploadInput && frontUploadInput.files[0]) {
                        formData.append('front_design_file', frontUploadInput.files[0]);
                    }

                    const backUploadInput = document.getElementById('backDesignUpload');
                    if (backUploadInput && backUploadInput.files[0]) {
                        formData.append('back_design_file', backUploadInput.files[0]);
                    }
                }

                formData.append('user_id', '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>');

                console.log('Saving design data...'); // Debug
                const response = await fetch('save_design.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Save design result:', result); // Debug
                return result;
            } catch (error) {
                console.error('Error saving designs:', error);
                return {
                    front_mockup: 'error',
                    back_mockup: 'error'
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
                    <input type="file" id="userLayoutUpload" class="pd-file" name="user_layout_upload[]" multiple accept="image/*,.pdf,.ai,.psd">
                    <small class="pd-hint">You can upload multiple files (images, PDF, AI, PSD)</small>
                    <div id="userLayoutPreview" class="pd-filelist"></div>
                `;

                // Add event listener for file upload
                setTimeout(() => {
                    const uploadInput = document.getElementById('userLayoutUpload');
                    if (uploadInput) {
                        uploadInput.addEventListener('change', function(e) {
                            const files = e.target.files;
                            const previewContainer = document.getElementById('userLayoutPreview');
                            previewContainer.innerHTML = '';

                            if (files.length > 0) {
                                const title = document.createElement('p');
                                title.className = 'pd-filelist__title';
                                title.textContent = 'Uploaded files';
                                previewContainer.appendChild(title);

                                for (let i = 0; i < files.length; i++) {
                                    const file = files[i];
                                    const row = document.createElement('div');
                                    row.className = 'pd-filelist__item';
                                    row.innerHTML = '<i class="fas fa-file"></i>';
                                    const label = document.createElement('span');
                                    label.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
                                    row.appendChild(label);
                                    previewContainer.appendChild(row);
                                }
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

        // Form validation before submitting to cart
        const cartFormEl = document.getElementById('cartForm');
        if (cartFormEl) { // the form only exists for logged in users
            cartFormEl.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault(); // Stop form submission
                    return false;
                }
            });
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
                const uploadType = document.querySelector('input[name="upload_type"]:checked').value;

                if (uploadType === 'single') {
                    const designUpload = document.getElementById('designUpload');
                    if (!designUpload || !designUpload.files.length) {
                        isValid = false;
                        errorMessage += '• Please upload a design image\n';
                    }
                } else if (uploadType === 'separate') {
                    const frontDesignUpload = document.getElementById('frontDesignUpload');
                    const backDesignUpload = document.getElementById('backDesignUpload');

                    if (!frontDesignUpload || !frontDesignUpload.files.length) {
                        isValid = false;
                        errorMessage += '• Please upload a front design image\n';
                    }

                    <?php if (!empty($back_base_image_url)): ?>
                        if (!backDesignUpload || !backDesignUpload.files.length) {
                            isValid = false;
                            errorMessage += '• Please upload a back design image\n';
                        }
                    <?php endif; ?>
                }

                // Check if design was applied (mockup generated and used)
                const designImageInput = document.getElementById('designImageInput');
                const frontDesignImageInput = document.getElementById('frontDesignImageInput');

                if (uploadType === 'single') {
                    if (!designImageInput || !designImageInput.value) {
                        isValid = false;
                        errorMessage += '• Please generate and apply your design using the "Generate Mockup" and "Use This Design" buttons\n';
                    }
                } else if (uploadType === 'separate') {
                    if (!frontDesignImageInput || !frontDesignImageInput.value) {
                        isValid = false;
                        errorMessage += '• Please generate and apply your designs using the "Generate Mockup" and "Use This Design" buttons\n';
                    }
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
                    document.querySelector('.image-upload-section').scrollIntoView({
                        behavior: 'smooth'
                    });
                }

                return false;
            }

            return true;
        }
    </script>
</body>
</html>