<?php
session_start();
require_once '../../config/db.php';

// Add some CSS for proper formatting
echo '<style>
.printing-details {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 5px;
    font-size: 0.9em;
}

.details-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
}

.detail-label {
    font-weight: 600;
    color: #2c3e50;
    min-width: 120px;
}

.detail-value {
    color: #495057;
    flex: 1;
}

.design-previews {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: flex-start;
    margin-top: 10px;
}

.design-preview {
    text-align: center;
    flex: 0 0 auto;
}

.design-preview img {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border-radius: 8px;
    border: 2px solid #007bff;
    padding: 5px;
    background: white;
}

.design-label {
    font-size: 0.7em;
    color: #666;
    margin-top: 5px;
    font-weight: 500;
}

.design-preview:has(img[alt*="Original"]) img {
    border-color: #28a745;
}

.design-preview:has(img[alt*="Mockup"]) img {
    border-color: #007bff;
}
</style>';

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify the order belongs to the user
    $query = "SELECT o.order_id FROM orders o WHERE o.order_id = ? AND o.user_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
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
        
        echo '<div class="order-items-list">';
        $total = 0;
        while ($item = $result->fetch_assoc()) {
            $item_total = $item['unit_price'] * $item['quantity'];
            $total += $item_total;
            ?>
            <div class="order-item-detail">
                <h4><?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['product_category']); ?>)</h4>
                <p><strong>Quantity:</strong> <?php echo $item['quantity']; ?></p>
                <p><strong>Unit Price:</strong> ₱<?php echo number_format($item['unit_price'], 2); ?></p>
                <p><strong>Subtotal:</strong> ₱<?php echo number_format($item_total, 2); ?></p>

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
                                            T-Shirt Size:
                                        <?php elseif ($isTote): ?>
                                            Tote Bag Size:
                                        <?php elseif ($isPaperBag): ?>
                                            Paper Bag Size:
                                        <?php elseif ($isMug): ?>
                                            Mug Size:
                                        <?php else: ?>
                                            Size:
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
                                            T-Shirt Color:
                                        <?php elseif ($isTote): ?>
                                            Tote Bag Color:
                                        <?php elseif ($isMug): ?>
                                            Mug Color:
                                        <?php else: ?>
                                            Color:
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
                                        <span class="detail-label">Finish:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['finish_option_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['paper_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Paper:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['paper_option_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['binding_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Binding:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['binding_option_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['layout_option_name'])): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Layout Type:</span>
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
                                        <span class="detail-label">GSM:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($item['gsm_option']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Custom Design Previews -->
                <?php
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
                        
                        // Get mockup images
                        $frontMockup = $designArray['front_mockup'] ?? '';
                        $backMockup = $designArray['back_mockup'] ?? '';
                        
                        // Get uploaded files based on upload type
                        if ($uploadType === 'single') {
                            $uploadedFile = $designArray['uploaded_file'] ?? '';
                        } else {
                            $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                            $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                        }
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
                                
                                if ($uploadType === 'single') {
                                    $uploadedFile = $designArray['uploaded_file'] ?? '';
                                } else {
                                    $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                    $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                                }
                            }
                        } else {
                            // Legacy format - single image
                            $frontMockup = $designData;
                            $uploadType = 'single';
                        }
                    }
                    
                    // Display design previews if we have valid images
                    $hasDesigns = !empty($frontMockup) || !empty($backMockup) || !empty($uploadedFile) || !empty($frontUploadedFile) || !empty($backUploadedFile);
                    
                    if ($hasDesigns): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 2px dashed #007bff;">
                            <div style="font-weight: bold; margin-bottom: 10px; color: #007bff; font-size: 1em;">
                                <i class="fas fa-palette"></i> Custom Design
                            </div>
                            <div class="design-previews">
                                <?php 
                                // Show uploaded original files
                                if ($uploadType === 'single' && !empty($uploadedFile)): 
                                    $uploadedFilePath = "../../assets/uploads/" . $uploadedFile;
                                    $uploadedFileExists = file_exists($uploadedFilePath);
                                    ?>
                                    <div class="design-preview">
                                        <?php if ($uploadedFileExists): ?>
                                            <img src="<?php echo $uploadedFilePath; ?>" 
                                                alt="Original Design File">
                                        <?php else: ?>
                                            <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                <i class="fas fa-file-image" style="font-size: 20px; color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="design-label">Original File</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($uploadType === 'separate'): ?>
                                    <?php if (!empty($frontUploadedFile)): 
                                        $frontUploadedFilePath = "../../assets/uploads/" . $frontUploadedFile;
                                        $frontUploadedFileExists = file_exists($frontUploadedFilePath);
                                        ?>
                                        <div class="design-preview">
                                            <?php if ($frontUploadedFileExists): ?>
                                                <img src="<?php echo $frontUploadedFilePath; ?>" 
                                                    alt="Front Original Design">
                                            <?php else: ?>
                                                <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                    <i class="fas fa-file-image" style="font-size: 20px; color: #999;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="design-label">Front Original</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($backUploadedFile)): 
                                        $backUploadedFilePath = "../../assets/uploads/" . $backUploadedFile;
                                        $backUploadedFileExists = file_exists($backUploadedFilePath);
                                        ?>
                                        <div class="design-preview">
                                            <?php if ($backUploadedFileExists): ?>
                                                <img src="<?php echo $backUploadedFilePath; ?>" 
                                                    alt="Back Original Design">
                                            <?php else: ?>
                                                <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                    <i class="fas fa-file-image" style="font-size: 20px; color: #999;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="design-label">Back Original</div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Mockup Previews -->
                                <?php 
                                // Front mockup
                                if (!empty($frontMockup)): 
                                    $frontMockupPath = "../../assets/uploads/" . $frontMockup;
                                    $frontMockupExists = file_exists($frontMockupPath);
                                    ?>
                                    <div class="design-preview">
                                        <?php if ($frontMockupExists): ?>
                                            <img src="<?php echo $frontMockupPath; ?>" 
                                                alt="Front Mockup">
                                        <?php else: ?>
                                            <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #007bff;">
                                                <i class="fas fa-image" style="font-size: 20px; color: #007bff;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="design-label">Front Mockup</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php 
                                // Back mockup
                                if (!empty($backMockup)): 
                                    $backMockupPath = "../../assets/uploads/" . $backMockup;
                                    $backMockupExists = file_exists($backMockupPath);
                                    ?>
                                    <div class="design-preview">
                                        <?php if ($backMockupExists): ?>
                                            <img src="<?php echo $backMockupPath; ?>" 
                                                alt="Back Mockup">
                                        <?php else: ?>
                                            <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #007bff;">
                                                <i class="fas fa-image" style="font-size: 20px; color: #007bff;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="design-label">Back Mockup</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif;
                }
                ?>
            </div>
            <?php
        }
        echo '<div class="order-total" style="margin-top: 20px; padding: 15px; background: #e9ecef; border-radius: 8px; text-align: right; font-weight: bold; font-size: 1.2em;">';
        echo '<strong>Total: ₱' . number_format($total, 2) . '</strong>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<p>Order not found.</p>';
    }
}
?>