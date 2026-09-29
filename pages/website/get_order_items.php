<?php
session_start();
require_once '../../config/db.php';

/* ------------------------------------------------------------
   Presentation helpers for the items list (display only)
------------------------------------------------------------ */
function oi_h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function oi_str($v)
{
    return is_scalar($v) ? (string) $v : '';
}

// Same product-group -> brand-ink mapping the cart uses
function oi_ink_for_category($category)
{
    $c = strtolower((string) $category);
    if (strpos($c, 'offset') !== false)  return 'black';
    if (strpos($c, 'digital') !== false) return 'cyan';
    if (strpos($c, 'riso') !== false)    return 'magenta';
    return 'yellow';
}

// design_image is JSON, "repaired" JSON, or (legacy) a bare filename
function oi_parse_design($raw)
{
    $out = [
        'upload_type'         => 'single',
        'front_mockup'        => '',
        'back_mockup'         => '',
        'uploaded_file'       => '',
        'front_uploaded_file' => '',
        'back_uploaded_file'  => '',
    ];

    $arr = json_decode($raw, true);
    if (!(json_last_error() === JSON_ERROR_NONE && is_array($arr))) {
        if (preg_match('/\{.*\}/', $raw)) {
            $fixed = stripslashes(str_replace('\\"', '"', $raw));
            $arr = json_decode($fixed, true);
            if (!(json_last_error() === JSON_ERROR_NONE && is_array($arr))) {
                return $out;
            }
        } else {
            $out['uploaded_file'] = $raw; // legacy: a bare filename
            return $out;
        }
    }

    $out['upload_type'] = oi_str($arr['upload_type'] ?? 'single') ?: 'single';
    foreach (['front_mockup', 'back_mockup', 'uploaded_file', 'front_uploaded_file', 'back_uploaded_file'] as $k) {
        $out[$k] = oi_str($arr[$k] ?? '');
    }
    return $out;
}

// [label, file, icon if the file is missing, alt text, kind, path, exists]
function oi_design_tiles($d)
{
    $tiles = [];
    if ($d['upload_type'] === 'single' && $d['uploaded_file'] !== '') {
        $tiles[] = ['Original File', $d['uploaded_file'], 'fa-file-image', 'Original Design File', 'original'];
    }
    if ($d['front_uploaded_file'] !== '') {
        $tiles[] = ['Front Original', $d['front_uploaded_file'], 'fa-file-image', 'Front Original Design', 'original'];
    }
    if ($d['back_uploaded_file'] !== '') {
        $tiles[] = ['Back Original', $d['back_uploaded_file'], 'fa-file-image', 'Back Original Design', 'original'];
    }
    if ($d['front_mockup'] !== '') {
        $tiles[] = ['Front Mockup', $d['front_mockup'], 'fa-image', 'Front Mockup', 'mockup'];
    }
    if ($d['back_mockup'] !== '') {
        $tiles[] = ['Back Mockup', $d['back_mockup'], 'fa-image', 'Back Mockup', 'mockup'];
    }
    foreach ($tiles as &$t) {
        $t[5] = '../../assets/uploads/' . $t[1];
        $t[6] = file_exists($t[5]);
    }
    unset($t);
    return $tiles;
}

// Image for the collapsed row: a mockup if there is one, else any design image that exists
function oi_pick_thumb($tiles)
{
    foreach (['mockup', 'original'] as $kind) {
        foreach ($tiles as $t) {
            if ($t[4] === $kind && $t[6]) return $t;
        }
    }
    return null;
}

// Layout files are saved with whatever relative prefix the service page used
// (e.g. "../../assets/uploads/user_layouts/5/layout_xyz.jpg"); normalise it
function oi_layout_file_url($stored)
{
    $pos = strpos($stored, 'assets/uploads/');
    if ($pos === false) {
        return null;
    }
    return '../../' . substr($stored, $pos);
}

function oi_layout_files($raw)
{
    $files = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($files)) {
        return [];
    }
    return array_values(array_filter($files, 'is_string'));
}

// Styles for the items list. Mobile-first: on phones the dialog on profile.php is
// a bottom sheet, so everything here is sized for a narrow, touch-only screen and
// then relaxed for wider screens at the bottom.
?>
<style>
/* Order items as tap-to-open cards (same pattern as the cart page).
   Mobile-first: the dialog is a bottom sheet on phones. */
.acct-page .order-items-list {
    display: grid;
    gap: 10px;
}

.order-items-list .oi-toolbar {
    display: flex;
    justify-content: flex-end;
}

.order-items-list .oi-expand {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 14px;
    background: transparent;
    border: 1.5px solid var(--ink);
    border-radius: var(--r-sm);
    color: var(--ink);
    font-family: var(--font-body);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.order-items-list .oi-expand:hover {
    background: var(--ink);
    color: var(--paper-white);
}

.order-items-list .oi-expand i {
    font-size: 11px;
    transition: transform 0.3s var(--ease);
}

.order-items-list .oi-expand[aria-pressed="true"] i {
    transform: rotate(180deg);
}

.acct-page .order-items-list .order-item-detail {
    --item-ink: var(--ink);
    min-width: 0;
    padding: 0;
    border-left-color: var(--item-ink);
    transition: var(--transition);
}

.acct-page .order-items-list .order-item-detail[data-ink="black"] {
    --item-ink: var(--cmyk-black);
}

.acct-page .order-items-list .order-item-detail[data-ink="cyan"] {
    --item-ink: var(--cmyk-cyan);
}

.acct-page .order-items-list .order-item-detail[data-ink="magenta"] {
    --item-ink: var(--cmyk-magenta);
}

.acct-page .order-items-list .order-item-detail[data-ink="yellow"] {
    --item-ink: var(--cmyk-yellow);
}

.acct-page .order-items-list .order-item-detail.is-open {
    box-shadow: var(--shadow-sm);
}

/* Always-visible row */
.order-items-list .oi-toggle {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) 14px;
    grid-template-areas:
        "img sum chev"
        "img sub chev";
    align-items: center;
    gap: 2px 12px;
    width: 100%;
    min-height: 64px;
    padding: 10px 12px;
    background: none;
    border: 0;
    border-radius: var(--r-md);
    font: inherit;
    color: inherit;
    text-align: left;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.order-items-list .oi-toggle:focus-visible {
    outline: 2px solid var(--riso-blue);
    outline-offset: -2px;
}

.order-items-list .oi-thumb {
    grid-area: img;
    align-self: start;
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    overflow: hidden;
    background: var(--paper-dim);
    border-radius: var(--r-sm);
    color: var(--ink-faint);
    font-size: 17px;
}

.order-items-list .oi-thumb img {
    width: 100%;
    height: 100%;
    max-width: none;
    object-fit: cover;
    border-radius: 0;
}

.order-items-list .oi-summary {
    grid-area: sum;
    min-width: 0;
    display: block;
}

.order-items-list .order-item-cat {
    display: block;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ink-faint);
}

.acct-page .order-items-list .order-item-name {
    display: block;
    flex: none;
    margin: 1px 0 2px;
    font-size: 0.95rem;
    line-height: 1.25;
}

.order-items-list .order-item-calc {
    display: flex;
    flex-wrap: wrap;
    gap: 0 6px;
    margin: 0;
    font-size: 12.5px;
    color: var(--ink-soft);
}

.order-items-list .order-item-calc strong {
    color: var(--ink);
}

.order-items-list .oi-sub {
    grid-area: sub;
    font-family: var(--font-display);
    font-size: 1rem;
    font-weight: 700;
    white-space: nowrap;
    color: var(--ink);
}

.order-items-list .oi-chev {
    grid-area: chev;
    font-size: 12px;
    color: var(--ink-faint);
    transition: transform 0.3s var(--ease);
}

.order-item-detail.is-open .oi-chev {
    transform: rotate(180deg);
}

/* The dropdown (animates height without JS measuring) */
.order-items-list .oi-panel {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.32s var(--ease);
}

.order-item-detail.is-open .oi-panel {
    grid-template-rows: 1fr;
}

.order-items-list .oi-panel__inner {
    min-height: 0;
    overflow: hidden;
    visibility: hidden;
    transition: visibility 0s linear 0.32s;
}

.order-item-detail.is-open .oi-panel__inner {
    visibility: visible;
    transition-delay: 0s;
}

.order-items-list .oi-body {
    margin: 0 12px;
    padding: 12px 0 14px;
    border-top: 1px dashed var(--line);
}

.order-items-list .oi-block {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--line);
}

.order-items-list .oi-block__title {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 2px 8px;
    margin-bottom: 8px;
    font-family: var(--font-display);
    font-size: 12.5px;
    font-weight: 600;
    color: var(--ink);
}

.order-items-list .oi-block__title i {
    align-self: center;
    color: var(--riso-red);
}

.order-items-list .oi-block__title small {
    font-family: var(--font-body);
    font-size: 11.5px;
    font-weight: 400;
    color: var(--ink-faint);
}

/* Price breakdown tiles */
.order-items-list .oi-figures {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    margin-bottom: 10px;
}

.order-items-list .oi-figure {
    min-width: 0;
    padding: 6px 10px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: var(--r-sm);
}

.order-items-list .oi-figure--sub {
    grid-column: 1 / -1;
}

.order-items-list .oi-figure span {
    display: block;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ink-faint);
}

.order-items-list .oi-figure strong {
    display: block;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--ink);
    overflow-wrap: anywhere;
}

/* Options as small chips, two per row on a phone */
.order-items-list .printing-details {
    margin: 0;
    padding: 0;
    background: none;
    font-size: inherit;
}

.order-items-list .details-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
}

.order-items-list .detail-item {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
    padding: 6px 10px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: var(--r-sm);
}

.order-items-list .detail-item:has(small) {
    grid-column: 1 / -1;
}

.order-items-list .detail-label {
    min-width: 0;
    font-size: 10.5px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ink-faint);
}

.order-items-list .detail-value {
    flex: none;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.3;
    color: var(--ink);
    overflow-wrap: anywhere;
}

.order-items-list .detail-value small {
    display: block;
    font-size: 11.5px;
    font-weight: 400;
    color: var(--ink-faint);
}

/* Uploaded layout files */
.order-items-list .oi-files {
    display: grid;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.order-items-list .oi-file {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 40px;
    padding: 6px 10px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: var(--r-sm);
    font-size: 13px;
    color: var(--ink);
    overflow-wrap: anywhere;
}

.order-items-list a.oi-file {
    color: var(--riso-blue);
    font-weight: 600;
}

.order-items-list .oi-file i {
    flex-shrink: 0;
    color: var(--ink-faint);
}

.order-items-list .oi-file small {
    margin-left: auto;
    flex-shrink: 0;
    color: var(--ink-faint);
    font-weight: 400;
}

/* Artwork: one swipeable row of tappable thumbnails */
.order-items-list .design-previews {
    display: flex;
    flex-wrap: nowrap;
    gap: 8px;
    margin: 0;
    padding-bottom: 2px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scroll-snap-type: x proximity;
}

.order-items-list .design-preview {
    flex: 0 0 auto;
    width: 68px;
    margin: 0;
    text-align: center;
    scroll-snap-align: start;
}

.order-items-list .design-preview a {
    display: block;
}

.order-items-list .design-preview img,
.order-items-list .design-missing {
    display: block;
    width: 68px;
    height: 68px;
    border-radius: var(--r-sm);
}

.order-items-list .design-preview img {
    padding: 3px;
    object-fit: contain;
    background: var(--paper-white);
    border: 1.5px solid var(--line);
}

.order-items-list .design-preview--original img {
    border-color: var(--ok);
}

.order-items-list .design-preview--mockup img {
    border-color: var(--riso-blue);
}

.order-items-list .design-missing {
    display: grid;
    place-items: center;
    border: 1.5px dashed var(--ink-faint);
    color: var(--ink-faint);
    font-size: 18px;
}

.order-items-list .design-label {
    margin-top: 4px;
    font-size: 10.5px;
    font-weight: 500;
    line-height: 1.2;
    color: var(--ink-soft);
}

.order-items-list .oi-meta {
    margin: 10px 0 0;
    font-size: 11.5px;
    color: var(--ink-faint);
    overflow-wrap: anywhere;
}

.order-items-list .oi-meta strong {
    font-weight: 600;
    color: var(--ink-soft);
}

/* Price breakdown for the whole order */
.acct-page .order-items-list .modal-order-summary {
    margin-top: 2px;
    padding: 4px 14px 12px;
}

.acct-page .order-items-list .summary-row {
    padding: 7px 0;
    font-size: 13.5px;
}

.acct-page .order-items-list .summary-row:last-child {
    padding-top: 12px;
    font-size: 1.1rem;
}

/* Larger screens: a little more room */
@media (min-width: 641px) {
    .acct-page .order-items-list {
        gap: 12px;
    }

    .order-items-list .oi-toggle {
        grid-template-columns: 52px minmax(0, 1fr) auto 14px;
        grid-template-areas: "img sum sub chev";
        gap: 16px;
        padding: 12px 16px;
    }

    .order-items-list .oi-thumb {
        align-self: center;
        width: 52px;
        height: 52px;
    }

    .acct-page .order-items-list .order-item-name {
        font-size: 1.05rem;
    }

    .order-items-list .oi-sub {
        font-size: 1.1rem;
    }

    .order-items-list .oi-body {
        margin: 0 16px;
        padding-bottom: 16px;
    }

    .order-items-list .oi-figures {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .order-items-list .oi-figure--sub {
        grid-column: auto;
    }

    .order-items-list .details-row {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 8px;
    }

    .order-items-list .detail-item {
        padding: 8px 12px;
    }

    .order-items-list .design-preview,
    .order-items-list .design-preview img,
    .order-items-list .design-missing {
        width: 80px;
    }

    .order-items-list .design-preview img,
    .order-items-list .design-missing {
        height: 80px;
    }

    .order-items-list .design-previews {
        flex-wrap: wrap;
        overflow-x: visible;
    }

    .acct-page .order-items-list .summary-row {
        padding: 8px 0;
        font-size: 14.5px;
    }
}
</style>
<?php

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify the order belongs to the user, and grab the order's stored
    // total (this is the same value shown on the order card / used
    // elsewhere in profile.php). We'll compare it against the sum of the
    // line items below, since the two are calculated independently.
    $query = "SELECT o.order_id, o.total_amount FROM orders o WHERE o.order_id = ? AND o.user_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order_row = $result->fetch_assoc();

    if ($order_row) {
        $order_total_amount = (float) $order_row['total_amount'];
       // Get order items with all customization options and their proper names
        $query = "SELECT 
            oi.product_name, 
            oi.product_category, 
            oi.unit_price, 
            oi.quantity, 
            oi.layout_option, 
            oi.layout_details, 
            oi.gsm_option, 
            oi.user_layout_files,
            oi.design_image, 
            oi.size_option, 
            oi.custom_size, 
            oi.color_option, 
            oi.custom_color, 
            oi.finish_option, 
            oi.paper_option, 
            oi.binding_option,
            -- Get option names from related tables
            po.option_name as paper_option_name,
            fo.option_name as finish_option_name,
            bo.option_name as binding_option_name,
            lo.option_name as layout_option_name,
            -- Get other services option names using CASE statements
            CASE 
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' THEN ts.size_name
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' THEN tos.size_name
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'Paper Bag' THEN pbs.size_name
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'Mug' THEN ms.size_name
                ELSE NULL
            END as size_option_name,
            CASE 
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' THEN tc.color_name
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' THEN toc.color_name
                WHEN oi.product_category = 'Other Services' AND oi.product_name = 'Mug' THEN mc.color_name
                ELSE NULL
            END as color_option_name,
            pbs.dimensions as paperbag_dimensions
        FROM order_items oi 
        LEFT JOIN paper_options po ON oi.paper_option = po.id
        LEFT JOIN finish_options fo ON oi.finish_option = fo.id
        LEFT JOIN binding_options bo ON oi.binding_option = bo.id
        LEFT JOIN layout_options lo ON oi.layout_option = lo.id
        -- Left join all other services tables with specific conditions
        LEFT JOIN tshirt_sizes ts ON (oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' AND oi.size_option = ts.id)
        LEFT JOIN tshirt_colors tc ON (oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' AND oi.color_option = tc.id)
        LEFT JOIN totesize_options tos ON (oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' AND oi.size_option = tos.id)
        LEFT JOIN totecolor_options toc ON (oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' AND oi.color_option = toc.id)
        LEFT JOIN paperbag_size_options pbs ON (oi.product_category = 'Other Services' AND oi.product_name = 'Paper Bag' AND oi.size_option = pbs.id)
        LEFT JOIN mug_size_options ms ON (oi.product_category = 'Other Services' AND oi.product_name = 'Mug' AND oi.size_option = ms.id)
        LEFT JOIN mug_color_options mc ON (oi.product_category = 'Other Services' AND oi.product_name = 'Mug' AND oi.color_option = mc.id)
        WHERE oi.order_id = ?";
        $stmt = $inventory->prepare($query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $item_count = (int) $result->num_rows;

        echo '<div class="order-items-list">';
        if ($item_count > 1) {
            echo '<div class="oi-toolbar"><button type="button" class="oi-expand" aria-pressed="false"><i class="fas fa-chevron-down" aria-hidden="true"></i> <span>Expand all</span></button></div>';
        }

        $total = 0;
        $idx = 0;
        while ($item = $result->fetch_assoc()) {
            $idx++;
            $item_total = $item['unit_price'] * $item['quantity'];
            $total += $item_total;

            $ink          = oi_ink_for_category($item['product_category']);
            $design       = !empty($item['design_image']) ? oi_parse_design($item['design_image']) : null;
            $design_tiles = $design ? oi_design_tiles($design) : [];
            $layout_files = !empty($item['user_layout_files']) ? oi_layout_files($item['user_layout_files']) : [];
            $thumb        = oi_pick_thumb($design_tiles);
            $is_open      = false; // every item starts closed; tap a row to open it
            ?>
            <article class="order-item-detail<?php echo $is_open ? ' is-open' : ''; ?>" data-ink="<?php echo $ink; ?>">
                <button type="button" class="oi-toggle" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="oi-panel-<?php echo $idx; ?>">
                    <span class="oi-thumb">
                        <?php if ($thumb): ?>
                            <img src="<?php echo oi_h($thumb[5]); ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <i class="fas fa-image" aria-hidden="true"></i>
                        <?php endif; ?>
                    </span>
                    <span class="oi-summary">
                        <span class="order-item-cat"><?php echo oi_h($item['product_category']); ?></span>
                        <span class="order-item-name"><?php echo oi_h($item['product_name']); ?></span>
                        <span class="order-item-calc">
                            <span>Qty <strong><?php echo (int) $item['quantity']; ?></strong></span>
                            <span>&times; &#8369;<?php echo number_format($item['unit_price'], 2); ?></span>
                        </span>
                    </span>
                    <span class="oi-sub">&#8369;<?php echo number_format($item_total, 2); ?></span>
                    <i class="fas fa-chevron-down oi-chev" aria-hidden="true"></i>
                </button>

                <div class="oi-panel" id="oi-panel-<?php echo $idx; ?>">
                    <div class="oi-panel__inner">
                        <div class="oi-body">

                            <!-- Price for this line -->
                            <div class="oi-figures">
                                <div class="oi-figure"><span>Unit price</span><strong>&#8369;<?php echo number_format($item['unit_price'], 2); ?></strong></div>
                                <div class="oi-figure"><span>Quantity</span><strong><?php echo (int) $item['quantity']; ?></strong></div>
                                <div class="oi-figure oi-figure--sub"><span>Subtotal</span><strong>&#8369;<?php echo number_format($item_total, 2); ?></strong></div>
                            </div>

                            <!-- Display all customization options -->
                <?php if (!empty($item['size_option']) || !empty($item['color_option']) || !empty($item['finish_option_name']) || !empty($item['paper_option_name']) || !empty($item['binding_option_name']) || !empty($item['layout_option_name']) || !empty($item['gsm_option'])): ?>
                    <div class="printing-details">
                        <div class="details-row">
                            <?php
                            // Determine product type based on category
                            $category = strtolower($item['product_category']);
                            $isTshirt = strpos($category, 't-shirt') !== false || strpos($category, 'tshirt') !== false;
                            $isTote = strpos($category, 'tote') !== false;
                            $isPaperBag = strpos($category, 'paper bag') !== false;
                            $isMug = strpos($category, 'mug') !== false;
                            ?>
                            
                            <!-- Size Options -->
                            <?php if (!empty($item['size_option'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <?php if ($isTshirt): ?>
                                            T-Shirt Size
                                        <?php elseif ($isTote): ?>
                                            Tote Bag Size
                                        <?php elseif ($isPaperBag): ?>
                                            Paper Bag Size
                                        <?php elseif ($isMug): ?>
                                            Mug Size
                                        <?php else: ?>
                                            Size
                                        <?php endif; ?>
                                    </span>
                                    <span class="detail-value">
                                        <?php
                                        // Use the size_option_name from our CASE statement
                                        if (!empty($item['size_option_name'])) {
                                            echo htmlspecialchars($item['size_option_name']);
                                            // Show dimensions for paper bags
                                            if ($isPaperBag && !empty($item['paperbag_dimensions'])) {
                                                echo '<br><small>(' . htmlspecialchars($item['paperbag_dimensions']) . ')</small>';
                                            }
                                        } else {
                                            // Fallback to the raw value
                                            echo htmlspecialchars($item['size_option']);
                                        }
                                        ?>
                                        <?php if (!empty($item['custom_size'])): ?>
                                            <br><small>Custom: <?php echo htmlspecialchars($item['custom_size']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Color Options -->
                            <?php if (!empty($item['color_option'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <?php if ($isTshirt): ?>
                                            T-Shirt Color
                                        <?php elseif ($isTote): ?>
                                            Tote Bag Color
                                        <?php elseif ($isMug): ?>
                                            Mug Color
                                        <?php else: ?>
                                            Color
                                        <?php endif; ?>
                                    </span>
                                    <span class="detail-value">
                                        <?php
                                        // Use the color_option_name from our CASE statement
                                        if (!empty($item['color_option_name'])) {
                                            echo htmlspecialchars($item['color_option_name']);
                                        } else {
                                            // Fallback to the raw value
                                            echo htmlspecialchars($item['color_option']);
                                        }
                                        ?>
                                        <?php if (!empty($item['custom_color'])): ?>
                                            <br><small>Custom: <?php echo htmlspecialchars($item['custom_color']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Printing Options (only show for printing products) -->
                            <?php if (!$isTshirt && !$isTote && !$isPaperBag && !$isMug): ?>
                                <?php if (!empty($item['finish_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Finish</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['finish_option_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['paper_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Paper</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['paper_option_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['binding_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Binding</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['binding_option_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['layout_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Layout Type</span>
                                        <span class="detail-value">
                                            <?php echo htmlspecialchars($item['layout_option_name']); ?>
                                            <?php if (!empty($item['layout_details'])): ?>
                                                <br><small>Details: <?php echo htmlspecialchars($item['layout_details']); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['gsm_option'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">GSM</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['gsm_option']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                            <!-- Uploaded layout files -->
                            <?php if (!empty($layout_files)): ?>
                                <div class="oi-block">
                                    <div class="oi-block__title">
                                        <i class="fas fa-file-upload" aria-hidden="true"></i> Your uploaded layout file<?php echo count($layout_files) > 1 ? 's' : ''; ?>
                                    </div>
                                    <ul class="oi-files">
                                        <?php foreach ($layout_files as $lf):
                                            $lf_url  = oi_layout_file_url($lf);
                                            $lf_name = basename($lf);
                                            $lf_ext  = strtolower(pathinfo($lf_name, PATHINFO_EXTENSION));
                                            $lf_icon = $lf_ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
                                        ?>
                                            <li>
                                                <?php if ($lf_url && file_exists($lf_url)): ?>
                                                    <a class="oi-file" href="<?php echo oi_h($lf_url); ?>" target="_blank" rel="noopener">
                                                        <i class="fas <?php echo $lf_icon; ?>" aria-hidden="true"></i> <?php echo oi_h($lf_name); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="oi-file">
                                                        <i class="fas <?php echo $lf_icon; ?>" aria-hidden="true"></i> <?php echo oi_h($lf_name); ?>
                                                        <small>(file not found)</small>
                                                    </span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Custom design previews (tap a thumbnail to open it full size) -->
                            <?php if (!empty($design_tiles)):
                                $upType = $design['upload_type'];
                            ?>
                                <div class="oi-block">
                                    <div class="oi-block__title">
                                        <i class="fas fa-palette" aria-hidden="true"></i> Your custom design
                                        <small><?php echo $upType === 'single' ? 'Same design on both sides' : 'Different designs for front and back'; ?></small>
                                    </div>
                                    <div class="design-previews">
                                        <?php foreach ($design_tiles as $tile): ?>
                                            <figure class="design-preview design-preview--<?php echo $tile[4]; ?>">
                                                <?php if ($tile[6]): ?>
                                                    <a href="<?php echo oi_h($tile[5]); ?>" target="_blank" rel="noopener" aria-label="Open <?php echo oi_h($tile[0]); ?> full size">
                                                        <img src="<?php echo oi_h($tile[5]); ?>" alt="<?php echo oi_h($tile[3]); ?>" loading="lazy">
                                                    </a>
                                                <?php else: ?>
                                                    <div class="design-missing" title="Preview not available"><i class="fas <?php echo $tile[2]; ?>" aria-hidden="true"></i></div>
                                                <?php endif; ?>
                                                <figcaption class="design-label"><?php echo oi_h($tile[0]); ?></figcaption>
                                            </figure>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="oi-meta">
                                        <strong>Upload type:</strong> <?php echo oi_h(ucfirst($upType)); ?>
                                        <?php if ($upType === 'single' && $design['uploaded_file'] !== ''): ?>
                                            &nbsp;&middot;&nbsp; <strong>File:</strong> <?php echo oi_h(basename($design['uploaded_file'])); ?>
                                        <?php endif; ?>
                                        <?php if ($upType === 'separate'): ?>
                                            <?php if ($design['front_uploaded_file'] !== ''): ?>
                                                &nbsp;&middot;&nbsp; <strong>Front:</strong> <?php echo oi_h(basename($design['front_uploaded_file'])); ?>
                                            <?php endif; ?>
                                            <?php if ($design['back_uploaded_file'] !== ''): ?>
                                                &nbsp;&middot;&nbsp; <strong>Back:</strong> <?php echo oi_h(basename($design['back_uploaded_file'])); ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </article>
            <?php
        }
        // Build the same price breakdown shown at checkout (see checkout.php):
        // subtotal = sum(unit_price * quantity), tax = 3% of subtotal.
        $subtotal = $total;
        $tax = round($subtotal * 0.03, 2);
        $expected_total = $subtotal + $tax;

        // The order's stored total_amount should equal subtotal + tax. If it
        // doesn't (e.g. the order predates the tax being added, an item was
        // edited after checkout, or a discount/fee was applied), surface the
        // difference explicitly instead of letting the numbers silently
        // disagree.
        $adjustment = round($order_total_amount - $expected_total, 2);

        echo '<div class="modal-order-summary order-total">';
        echo '<div class="summary-row"><span>Subtotal</span><span>₱' . number_format($subtotal, 2) . '</span></div>';
        echo '<div class="summary-row"><span>Tax (3%)</span><span>₱' . number_format($tax, 2) . '</span></div>';
        if (abs($adjustment) > 0.005) {
            $adj_label = $adjustment > 0 ? 'Additional fee' : 'Discount';
            echo '<div class="summary-row"><span>' . $adj_label . '</span><span>₱' . number_format(abs($adjustment), 2) . '</span></div>';
        }
        echo '<div class="summary-row total-row"><span>Total</span><span>₱' . number_format($order_total_amount, 2) . '</span></div>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<p>Order not found.</p>';
    }
}
?>