<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

$request_id = intval($_GET['id']);

try {
    // Get pricing request details
    $query = "SELECT pr.*, 
                     u.username,
                     pc.first_name, pc.last_name, pc.contact_number AS personal_contact,
                     cc.company_name, cc.contact_person, cc.contact_number AS company_contact
              FROM pricing_requests pr
              LEFT JOIN users u ON pr.user_id = u.id
              LEFT JOIN personal_customers pc ON u.id = pc.user_id
              LEFT JOIN company_customers cc ON u.id = cc.user_id
              WHERE pr.id = ?";

    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    // Get cart items for this request
    $selected_items = json_decode($request['selected_items'], true);
    $items_html = '';
    $total_quoted = 0;

    if (!empty($selected_items)) {
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $items_query = "SELECT p.product_name, p.category, p.price as unit_price,
                            ci.quantity, ci.item_id, ci.design_image,
                            ci.quoted_price, ci.price_updated_by_admin,
                            ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                            ci.finish_option, ci.paper_option, ci.binding_option,
                            ci.layout_option, ci.layout_details, ci.gsm_option,
                            ci.user_layout_files,
                            po.option_name AS paper_option_name,
                            fo.option_name AS finish_option_name,
                            bo.option_name AS binding_option_name,
                            lo.option_name AS layout_option_name
                        FROM cart_items ci
                        JOIN products_offered p ON ci.product_id = p.id
                        LEFT JOIN paper_options po ON ci.paper_option = po.id
                        LEFT JOIN finish_options fo ON ci.finish_option = fo.id
                        LEFT JOIN binding_options bo ON ci.binding_option = bo.id
                        LEFT JOIN layout_options lo ON ci.layout_option = lo.id
                        WHERE ci.item_id IN ($placeholders)";

        $items_stmt = $inventory->prepare($items_query);
        $types = str_repeat('i', count($selected_items));
        $items_stmt->bind_param($types, ...$selected_items);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        while ($item = $items_result->fetch_assoc()) {
            $actual_price = $item['price_updated_by_admin'] && $item['quoted_price'] > 0
                ? $item['quoted_price']
                : $item['unit_price'];

            $subtotal = $actual_price * $item['quantity'];
            $total_quoted += $subtotal;

            $items_html .= "
            <div class='request-item'>
                <div class='item-header'>
                    <div class='item-title'>
                        <h4>{$item['product_name']}</h4>
                        <span class='item-category'>{$item['category']}</span>
                    </div>
                    <div class='item-price'>
                        <span class='subtotal'>₱" . number_format($subtotal, 2) . "</span>
                    </div>
                </div>
                
                <div class='item-details-grid'>
                    <div class='detail-group'>
                        <label>Quantity</label>
                        <span>{$item['quantity']}</span>
                    </div>
                    <div class='detail-group'>
                        <label>Unit Price</label>
                        <span>₱" . number_format($item['unit_price'], 2) . "</span>
                    </div>
                    " . ($item['price_updated_by_admin'] ? "
                    <div class='detail-group quoted-price'>
                        <label>Quoted Price</label>
                        <span>₱" . number_format($item['quoted_price'], 2) . "</span>
                    </div>" : "") . "
                </div>";

            // Add customization details if available
            $hasCustomizations = !empty($item['size_option']) || !empty($item['color_option']) || !empty($item['finish_option_name']) ||
                !empty($item['paper_option_name']) || !empty($item['binding_option_name']) || !empty($item['layout_option_name']) ||
                !empty($item['gsm_option']) || !empty($item['user_layout_files']);

            if ($hasCustomizations) {
                $items_html .= "<div class='customization-section'>
                    <h5>Customization Details</h5>
                    <div class='customization-grid'>";

                if (!empty($item['size_option'])) {
                    $items_html .= "<div class='customization-item'>
                        <span class='custom-label'>Size</span>
                        <span class='custom-value'>" . htmlspecialchars($item['size_option']) .
                        (!empty($item['custom_size']) ? " (Custom: " . htmlspecialchars($item['custom_size']) . ")" : "") . "</span>
                    </div>";
                }
                if (!empty($item['color_option'])) {
                    $items_html .= "<div class='customization-item'>
                        <span class='custom-label'>Color</span>
                        <span class='custom-value'>" . htmlspecialchars($item['color_option']) .
                        (!empty($item['custom_color']) ? " (Custom: " . htmlspecialchars($item['custom_color']) . ")" : "") . "</span>
                    </div>";
                }
                if (!empty($item['finish_option'])) {
                    $items_html .= "<div class='customization-item'>
                        <span class='custom-label'>Finish</span>
                        <span class='custom-value'>" . htmlspecialchars($item['finish_option_name']) . "</span>
                    </div>";
                }
                if (!empty($item['paper_option'])) {
                    $items_html .= "<div class='customization-item'>
                        <span class='custom-label'>Paper</span>
                        <span class='custom-value'>" . htmlspecialchars($item['paper_option_name']) . "</span>
                    </div>";
                }
                if (!empty($item['binding_option'])) {
                    $items_html .= "<div class='customization-item'>
                        <span class='custom-label'>Binding</span>
                        <span class='custom-value'>" . htmlspecialchars($item['binding_option_name']) . "</span>
                    </div>";
                }

                if (!empty($item['layout_option'])) {
                    $items_html .= "<div class='customization-item'>
        <span class='custom-label'>Layout</span>
        <div class='custom-value'>
            <div>" . htmlspecialchars($item['layout_option_name']) . "</div>";

                    $items_html .= "</div></div>";

                    if (!empty($item['layout_details'])) {
                        $items_html .= "<div class='customization-item'>
        <span class='custom-value' style='overflow: scroll;'>" . htmlspecialchars($item['layout_details']) . "</span></div>";
                    }

                    // User Layout Files in separate customization item - show images with download
                    if (!empty($item['user_layout_files'])) {
                        $layout_files = json_decode($item['user_layout_files'], true);
                        if (is_array($layout_files)) {
                            $items_html .= "<div class='customization-item'>
                <div class='custom-value layout-images'>";

                            foreach ($layout_files as $file_path) {
                                $clean_path = str_replace('../../', '', $file_path);
                                $full_path = "../../" . $clean_path;
                                $file_exists = file_exists($full_path);

                                // Check if file is an image
                                $is_image = preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $clean_path);

                                if ($file_exists && $is_image) {
                                    $items_html .= "<div class='layout-image-preview'>
                        <a href='$full_path' target='_blank' class='image-link'>
                            <img src='$full_path' alt='Layout File' class='layout-thumbnail'>
                            <div class='image-overlay'>
                                <i class='fas fa-expand'></i>
                            </div>
                        </a>
                        <div class='image-actions'>
                            <a href='$full_path' download='" . basename($clean_path) . "' class='download-btn'>
                                <i class='fas fa-download'></i> Download
                            </a>
                        </div>
                        <div class='image-filename'>" . basename($clean_path) . "</div>
                    </div>";
                                } else {
                                    // Fallback to download link for non-image files
                                    $items_html .= "<div class='layout-file'>
                        " . ($file_exists ? "
                        <a href='$full_path' download='" . basename($clean_path) . "' target='_blank'>
                            <i class='fas fa-download'></i> " . basename($clean_path) . "
                        </a>
                        " : "
                        <span class='file-missing'>
                            <i class='fas fa-exclamation-triangle'></i> " . basename($clean_path) . "
                        </span>
                        ") . "
                    </div>";
                                }
                            }

                            $items_html .= "</div></div>";
                        }
                    }
                }

                if (!empty($item['gsm_option'])) {
                    $items_html .= "<div class='customization-item'>
                        <span class='custom-label'>GSM</span>
                        <span class='custom-value'>" . htmlspecialchars($item['gsm_option']) . "</span>
                    </div>";
                }

                $items_html .= "</div></div>";
            }

            // Add design image if available
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

                    // Get ALL images
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

                if ($hasDesigns) {
                    $items_html .= "
        <div class='design-preview-section'>
            <h5>Design Files</h5>
            <div class='design-previews'>";

                    // Show uploaded original files
                    if ($uploadType === 'single' && !empty($uploadedFile)) {
                        $uploadedFilePath = "../../assets/uploads/" . $uploadedFile;
                        $uploadedFileExists = file_exists($uploadedFilePath);

                        $items_html .= "
                <div class='design-preview'>
                    " . ($uploadedFileExists ? "
                    <a href='$uploadedFilePath' download='" . basename($uploadedFile) . "' style='text-decoration: none;'>
                        <img src='$uploadedFilePath' alt='Original Design File' class='design-image'>
                        <div class='design-overlay'>
                            <i class='fas fa-download'></i>
                        </div>
                        <div class='design-label'>Original File</div>
                    </a>
                    " : "
                    <div class='design-file-missing'>
                        <i class='fas fa-file-image'></i>
                        <div class='design-label'>File not found</div>
                    </div>
                    ") . "
                </div>";
                    }

                    // Front and back uploaded files
                    if (!empty($frontUploadedFile) || !empty($backUploadedFile)) {
                        if (!empty($frontUploadedFile)) {
                            $frontUploadedFilePath = "../../assets/uploads/" . $frontUploadedFile;
                            $frontUploadedFileExists = file_exists($frontUploadedFilePath);

                            $items_html .= "
                <div class='design-preview'>
                    " . ($frontUploadedFileExists ? "
                    <a href='$frontUploadedFilePath' download='" . basename($frontUploadedFile) . "' style='text-decoration: none;'>
                        <img src='$frontUploadedFilePath' alt='Front Original Design' class='design-image'>
                        <div class='design-overlay'>
                            <i class='fas fa-download'></i>
                        </div>
                        <div class='design-label'>Front Original</div>
                    </a>
                    " : "
                    <div class='design-file-missing'>
                        <i class='fas fa-file-image'></i>
                        <div class='design-label'>File not found</div>
                    </div>
                    ") . "
                </div>";
                        }

                        if (!empty($backUploadedFile)) {
                            $backUploadedFilePath = "../../assets/uploads/" . $backUploadedFile;
                            $backUploadedFileExists = file_exists($backUploadedFilePath);

                            $items_html .= "
                <div class='design-preview'>
                    " . ($backUploadedFileExists ? "
                    <a href='$backUploadedFilePath' download='" . basename($backUploadedFile) . "' style='text-decoration: none;'>
                        <img src='$backUploadedFilePath' alt='Back Original Design' class='design-image'>
                        <div class='design-overlay'>
                            <i class='fas fa-download'></i>
                        </div>
                        <div class='design-label'>Back Original</div>
                    </a>
                    " : "
                    <div class='design-file-missing'>
                        <i class='fas fa-file-image'></i>
                        <div class='design-label'>File not found</div>
                    </div>
                    ") . "
                </div>";
                        }
                    }

                    // Mockup Previews
                    if (!empty($frontMockup)) {
                        $frontMockupPath = "../../assets/uploads/" . $frontMockup;
                        $frontMockupExists = file_exists($frontMockupPath);

                        $items_html .= "
            <div class='design-preview'>
                " . ($frontMockupExists ? "
                <a href='$frontMockupPath' download='" . basename($frontMockup) . "' style='text-decoration: none;'>
                    <img src='$frontMockupPath' alt='Front Mockup' class='design-image'>
                    <div class='design-overlay'>
                        <i class='fas fa-download'></i>
                    </div>
                    <div class='design-label'>Front Mockup</div>
                </a>
                " : "
                <div class='design-file-missing'>
                    <i class='fas fa-image'></i>
                    <div class='design-label'>File not found</div>
                </div>
                ") . "
            </div>";
                    }

                    if (!empty($backMockup)) {
                        $backMockupPath = "../../assets/uploads/" . $backMockup;
                        $backMockupExists = file_exists($backMockupPath);

                        $items_html .= "
            <div class='design-preview'>
                " . ($backMockupExists ? "
                <a href='$backMockupPath' download='" . basename($backMockup) . "' style='text-decoration: none;'>
                    <img src='$backMockupPath' alt='Back Mockup' class='design-image'>
                    <div class='design-overlay'>
                        <i class='fas fa-download'></i>
                    </div>
                    <div class='design-label'>Back Mockup</div>
                </a>
                " : "
                <div class='design-file-missing'>
                    <i class='fas fa-image'></i>
                    <div class='design-label'>File not found</div>
                </div>
                ") . "
            </div>";
                    }

                    $items_html .= "
            </div>
        </div>";
                }
            }

            $items_html .= "</div>";
        }
    }

    // Determine customer type and info
    $customer_type = !empty($request['company_name']) ? 'company' : (!empty($request['first_name']) ? 'personal' : 'basic');
    $customer_html = '';

    if ($customer_type === 'company') {
        $customer_html = "
        <div class='info-card'>
            <div class='info-icon'>
                <i class='fas fa-building'></i>
            </div>
            <div class='info-content'>
                <h4>Company Customer</h4>
                <div class='info-grid'>
                    <div class='info-item'>
                        <span class='info-label'>Company</span>
                        <span class='info-value'>{$request['company_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Contact Person</span>
                        <span class='info-value'>{$request['contact_person']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Contact No.</span>
                        <span class='info-value'>{$request['company_contact']}</span>
                    </div>
                </div>
            </div>
        </div>";
    } else if ($customer_type === 'personal') {
        $customer_html = "
        <div class='info-card'>
            <div class='info-icon'>
                <i class='fas fa-user'></i>
            </div>
            <div class='info-content'>
                <h4>Personal Customer</h4>
                <div class='info-grid'>
                    <div class='info-item'>
                        <span class='info-label'>Full Name</span>
                        <span class='info-value'>{$request['first_name']} {$request['last_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Contact No.</span>
                        <span class='info-value'>{$request['personal_contact']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'>Email</span>
                        <span class='info-value'>{$request['username']}</span>
                    </div>
                </div>
            </div>
        </div>";
    } else {
        $customer_html = "
        <div class='info-card'>
            <div class='info-icon'>
                <i class='fas fa-user-tie'></i>
            </div>
            <div class='info-content'>
                <h4>Customer Information</h4>
                <div class='info-grid'>
                    <div class='info-item'>
                        <span class='info-label'>Username</span>
                        <span class='info-value'>{$request['username']}</span>
                    </div>
                </div>
            </div>
        </div>";
    }

    // Status badge with better styling
    $status_badge = "
    <span class='status-badge status-{$request['status']}'>
        <i class='fas " . ($request['status'] == 'pending' ? 'fa-clock' : ($request['status'] == 'reviewed' ? 'fa-eye' : ($request['status'] == 'quoted' ? 'fa-comment-dollar' : 'fa-check-circle'))) . "'></i>
        " . ucfirst($request['status']) . "
    </span>";

    $html = "
    <div class='professional-modal'>
        <div class='modal-header-section'>
            <div class='header-content'>
                <h2>Pricing Request Details</h2>
                <div class='header-badges'>
                    <span class='request-id'>#{$request['id']}</span>
                    {$status_badge}
                </div>
            </div>
            <div class='header-meta'>
                <div class='meta-item'>
                    <i class='fas fa-calendar'></i>
                    <span>" . date('F j, Y', strtotime($request['request_date'])) . "</span>
                </div>
                <div class='meta-item'>
                    <i class='fas fa-clock'></i>
                    <span>" . date('g:i A', strtotime($request['request_date'])) . "</span>
                </div>
            </div>
        </div>

        <div class='modal-body-section'>
            <div class='content-grid'>
                <div class='left-column'>
                    {$customer_html}
                    
                    <div class='info-card'>
                        <div class='info-icon'>
                            <i class='fas fa-file-invoice-dollar'></i>
                        </div>
                        <div class='info-content'>
                            <h4>Pricing Summary</h4>
                            <div class='pricing-summary'>
                                <div class='price-row'>
                                    <span class='price-label'>Estimated Total</span>
                                    </br>
                                    <span class='price-value'>₱" . number_format($request['estimated_total'], 2) . "</span>
                                </div>
                                " . ($request['final_price'] ? "
                                <div class='price-row final'>
                                    <span class='price-label'>Final Price</span>
                                    </br>
                                    <span class='price-value'>₱" . number_format($request['final_price'], 2) . "</span>
                                </div>
                                <div class='price-difference " . ($request['final_price'] > $request['estimated_total'] ? 'increase' : 'decrease') . "'>
                                    <span class='difference-label'>Price Difference</span>
                                    <span class='difference-value'>
                                        " . ($request['final_price'] > $request['estimated_total'] ? '+' : '') . "₱" .
        number_format($request['final_price'] - $request['estimated_total'], 2) . "
                                        (" . number_format((($request['final_price'] - $request['estimated_total']) / $request['estimated_total']) * 100, 1) . "%)
                                    </span>
                                </div>" : "") . "
                            </div>
                        </div>
                    </div>
                </div>

                <div class='right-column'>
                    <div class='items-section'>
                        <div class='section-header'>
                            <h3>Selected Items</h3>
                            <span class='items-count'>" . count($selected_items) . " items</span>
                        </div>
                        <div class='items-container'>
                            {$items_html}
                        </div>
                        " . ($total_quoted > 0 ? "
                        <div class='total-section'>
                            <div class='total-row'>
                                <span class='total-label'>Total Quoted Amount</span>
                                <span class='total-amount'>₱" . number_format($total_quoted, 2) . "</span>
                            </div>
                        </div>" : "") . "
                    </div>
                </div>
            </div>
        </div>
    </div>";

    echo json_encode(['html' => $html]);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
