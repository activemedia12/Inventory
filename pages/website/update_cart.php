<?php
session_start();
require_once '../../config/db.php';

// In update_cart.php - add this to handle saving selected items
if (isset($_POST['action']) && $_POST['action'] === 'save_selected_items') {
    session_start();
    
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $_SESSION['selected_cart_items'] = $_POST['selected_items'];
        echo json_encode(['status' => 'success', 'message' => 'Selected items saved for checkout']);
    } else {
        $_SESSION['selected_cart_items'] = [];
        echo json_encode(['status' => 'success', 'message' => 'Selected items cleared']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $action = $_POST['action'] ?? '';
    
    header('Content-Type: application/json');
    
    error_log("=== UPDATE_CART.PHP DEBUG ===");
    error_log("Action: " . $action);
    error_log("User ID: " . $user_id);
    
    if ($action === 'update') {
        // Update quantity
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($item_id > 0 && $quantity > 0) {
            // Get user's cart_id
            $cart_query = "SELECT cart_id FROM carts WHERE user_id = ?";
            $stmt = $inventory->prepare($cart_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $cart_result = $stmt->get_result();
            
            if ($cart_result->num_rows > 0) {
                $cart_row = $cart_result->fetch_assoc();
                $cart_id = $cart_row['cart_id'];
                
                // Update quantity
                $update_query = "UPDATE cart_items SET quantity = ? WHERE item_id = ? AND cart_id = ?";
                $stmt = $inventory->prepare($update_query);
                $stmt->bind_param("iii", $quantity, $item_id, $cart_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Quantity updated']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to update quantity']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Cart not found']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid item or quantity']);
        }
        
    } elseif ($action === 'remove') {
        // Remove single item
        $item_id = intval($_POST['item_id'] ?? 0);
        
        error_log("=== REMOVE ITEM DEBUG ===");
        error_log("User ID: " . $user_id);
        error_log("Item ID: " . $item_id);
        
        if ($item_id > 0) {
            // First, get the design_image and user_layout_files to delete files
            $select_query = "SELECT design_image, user_layout_files FROM cart_items WHERE item_id = ?";
            $stmt = $inventory->prepare($select_query);
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $design_image = $row['design_image'];
                $user_layout_files = $row['user_layout_files'];
                
                error_log("Design Image Data: " . substr($design_image, 0, 200));
                error_log("User Layout Files: " . $user_layout_files);
                
                // Delete associated design files
                if (!empty($design_image)) {
                    deleteDesignFiles($design_image, $user_id);
                }
                
                // Also delete user layout files if they exist
                if (!empty($user_layout_files)) {
                    deleteUserLayoutFiles($user_layout_files, $user_id);
                }
            } else {
                error_log("No cart item found with ID: " . $item_id);
            }
            
            // Now delete the cart item
            $delete_query = "DELETE FROM cart_items WHERE item_id = ?";
            $stmt = $inventory->prepare($delete_query);
            $stmt->bind_param("i", $item_id);
            
            if ($stmt->execute()) {
                error_log("✅ Item removed from database");
                echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
            } else {
                error_log("❌ Failed to remove item from database");
                echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
            }
        } else {
            error_log("Invalid item ID: " . $item_id);
            echo json_encode(['status' => 'error', 'message' => 'Invalid item']);
        }
        error_log("=== END REMOVE DEBUG ===");
        
    } elseif ($action === 'remove_selected') {
        // Remove multiple selected items
        $selected_items = $_POST['selected_items'] ?? [];
        
        error_log("=== REMOVE SELECTED DEBUG ===");
        error_log("Selected items count: " . count($selected_items));
        error_log("Selected items: " . print_r($selected_items, true));
        
        if (!empty($selected_items) && is_array($selected_items)) {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($selected_items as $item_id) {
                $item_id = intval($item_id);
                error_log("Processing item ID: " . $item_id);
                
                if ($item_id > 0) {
                    // First, get the design_image and user_layout_files to delete files
                    $select_query = "SELECT design_image, user_layout_files FROM cart_items WHERE item_id = ?";
                    $stmt = $inventory->prepare($select_query);
                    $stmt->bind_param("i", $item_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $design_image = $row['design_image'];
                        $user_layout_files = $row['user_layout_files'];
                        
                        error_log("Design Image for item {$item_id}: " . substr($design_image, 0, 100));
                        error_log("User Layout Files for item {$item_id}: " . $user_layout_files);
                        
                        // Delete associated design files
                        if (!empty($design_image)) {
                            deleteDesignFiles($design_image, $user_id);
                        }
                        
                        // Also delete user layout files if they exist
                        if (!empty($user_layout_files)) {
                            deleteUserLayoutFiles($user_layout_files, $user_id);
                        }
                    } else {
                        error_log("No cart item found with ID: " . $item_id);
                    }
                    
                    // Now delete the cart item
                    $delete_query = "DELETE FROM cart_items WHERE item_id = ?";
                    $stmt = $inventory->prepare($delete_query);
                    $stmt->bind_param("i", $item_id);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                        error_log("✅ Successfully removed item: " . $item_id);
                    } else {
                        $error_count++;
                        error_log("❌ Failed to remove item: " . $item_id);
                    }
                }
            }
            
            if ($error_count === 0) {
                echo json_encode(['status' => 'success', 'message' => "Successfully removed {$success_count} items"]);
            } else {
                echo json_encode(['status' => 'partial', 'message' => "Removed {$success_count} items, {$error_count} failed"]);
            }
        } else {
            error_log("No items selected for removal");
            echo json_encode(['status' => 'error', 'message' => 'No items selected']);
        }
        error_log("=== END REMOVE SELECTED DEBUG ===");
        
    } else {
        error_log("Invalid action: " . $action);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
    
    error_log("=== END UPDATE_CART.PHP DEBUG ===");
    
} else {
    error_log("Invalid request or not logged in");
    echo json_encode(['status' => 'error', 'message' => 'Invalid request or not logged in']);
}

/**
 * Delete design files associated with a cart item
 */
function deleteDesignFiles($design_image, $user_id) {
    $result = "=== DELETE DESIGN FILES DEBUG ===\n";
    $result .= "User ID: " . $user_id . "\n";
    $result .= "Raw design_image: " . $design_image . "\n";
    
    // First, check if it's already a JSON string with escaped characters
    if (strpos($design_image, '\\"') !== false) {
        $result .= "Found escaped JSON, unescaping...\n";
        $design_image = stripslashes($design_image);
    }
    
    // Try to decode as JSON
    $design_data = json_decode($design_image, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($design_data)) {
        $result .= "✅ JSON parsed successfully\n";
        $result .= "Design data: " . print_r($design_data, true) . "\n";
        
        $files_to_delete = [];
        
        // Extract all file paths from the design data
        if (isset($design_data['upload_type']) && $design_data['upload_type'] === 'single') {
            $result .= "Single upload type detected\n";
            
            if (!empty($design_data['front_mockup'])) {
                $files_to_delete[] = $design_data['front_mockup'];
            }
            if (!empty($design_data['back_mockup'])) {
                $files_to_delete[] = $design_data['back_mockup'];
            }
            if (!empty($design_data['uploaded_file'])) {
                $files_to_delete[] = $design_data['uploaded_file'];
            }
        } else {
            $result .= "Separate upload type detected\n";
            
            if (!empty($design_data['front_mockup'])) {
                $files_to_delete[] = $design_data['front_mockup'];
            }
            if (!empty($design_data['back_mockup'])) {
                $files_to_delete[] = $design_data['back_mockup'];
            }
            if (!empty($design_data['front_uploaded_file'])) {
                $files_to_delete[] = $design_data['front_uploaded_file'];
            }
            if (!empty($design_data['back_uploaded_file'])) {
                $files_to_delete[] = $design_data['back_uploaded_file'];
            }
        }
        
        $result .= "Files to delete: " . print_r($files_to_delete, true) . "\n";
        
        // Delete the files using the exact paths from your save_design.php
        $deleted_count = 0;
        foreach ($files_to_delete as $file_path) {
            $result .= "Processing: {$file_path}\n";
            
            // Convert to actual file system path - match exactly how save_design.php saves them
            $actual_path = convertToActualPath($file_path);
            $result .= "Actual path: {$actual_path}\n";
            
            if (file_exists($actual_path)) {
                if (unlink($actual_path)) {
                    $result .= "✅ SUCCESS: Deleted {$actual_path}\n";
                    $deleted_count++;
                } else {
                    $result .= "❌ FAILED: Could not delete {$actual_path}\n";
                    // Check permissions
                    if (file_exists($actual_path)) {
                        $result .= "File permissions: " . substr(sprintf('%o', fileperms($actual_path)), -4) . "\n";
                    }
                }
            } else {
                $result .= "⚠️ File not found: {$actual_path}\n";
                
                // Try alternative paths
                $alternative_paths = [
                    "../../assets/uploads/" . $file_path,
                    $file_path,
                    str_replace('save_design/', '../../assets/uploads/save_design/', $file_path)
                ];
                
                foreach ($alternative_paths as $alt_path) {
                    $result .= "Trying alternative: {$alt_path}\n";
                    if (file_exists($alt_path)) {
                        if (unlink($alt_path)) {
                            $result .= "✅ SUCCESS: Deleted (alternative) {$alt_path}\n";
                            $deleted_count++;
                            break;
                        }
                    }
                }
            }
        }
        
        $result .= "Total design files deleted: {$deleted_count}\n";
        
    } else {
        $result .= "❌ JSON decode failed: " . json_last_error_msg() . "\n";
        $result .= "Attempting manual extraction from: " . $design_image . "\n";
        
        // Manual extraction for malformed JSON
        $files_to_delete = [];
        
        // Look for file patterns in the string
        preg_match_all('/save_design\/\d+\/[^"\'},\s]+\.(png|jpg|jpeg|gif|svg)/i', $design_image, $matches);
        if (!empty($matches[0])) {
            $files_to_delete = array_merge($files_to_delete, $matches[0]);
        }
        
        if (!empty($files_to_delete)) {
            $result .= "Manually extracted files: " . print_r($files_to_delete, true) . "\n";
            
            $deleted_count = 0;
            foreach ($files_to_delete as $file_path) {
                $actual_path = convertToActualPath($file_path);
                $result .= "Trying to delete: {$actual_path}\n";
                
                if (file_exists($actual_path) && unlink($actual_path)) {
                    $result .= "✅ SUCCESS: Deleted {$actual_path}\n";
                    $deleted_count++;
                }
            }
            $result .= "Total manually extracted files deleted: {$deleted_count}\n";
        }
    }
    
    // Clean up empty user directories
    cleanupUserDirectories($user_id);
    
    $result .= "=== END DELETE DESIGN FILES DEBUG ===\n\n";
    file_put_contents('../debug_file_deletion.txt', $result, FILE_APPEND);
    return $result;
}

/**
 * Convert stored file path to actual file system path
 * This matches exactly how save_design.php saves files
 */
function convertToActualPath($stored_path) {
    // If it's already a full path, return as-is
    if (strpos($stored_path, '../../assets/uploads/') === 0) {
        return $stored_path;
    }
    
    // If it starts with assets/uploads/, add ../
    if (strpos($stored_path, '../../assets/uploads/') === 0) {
        return '../' . $stored_path;
    }
    
    // If it's in the format "save_design/21/filename.png" (from your save_design.php)
    if (preg_match('/^save_design\/\d+\/.+\.(png|jpg|jpeg|gif|svg)$/i', $stored_path)) {
        return '../../assets/uploads/' . $stored_path;
    }
    
    // If it's in the format "user_layouts/21/filename.png"
    if (preg_match('/^user_layouts\/\d+\/.+\.(png|jpg|jpeg|gif|svg)$/i', $stored_path)) {
        return '../../assets/uploads/' . $stored_path;
    }
    
    // Return as-is if no pattern matches
    return $stored_path;
}

/**
 * Delete user layout files associated with a cart item
 */
function deleteUserLayoutFiles($user_layout_files_json, $user_id) {
    $result = "=== DELETE USER LAYOUT FILES DEBUG ===\n";
    $result .= "User ID: " . $user_id . "\n";
    $result .= "Raw layout data: " . $user_layout_files_json . "\n";
    
    // Handle escaped JSON
    if (strpos($user_layout_files_json, '\\"') !== false) {
        $user_layout_files_json = stripslashes($user_layout_files_json);
    }
    
    $user_layout_files = json_decode($user_layout_files_json, true);
    
    if (is_array($user_layout_files)) {
        $result .= "✅ JSON parsed successfully\n";
        $result .= "Layout files: " . print_r($user_layout_files, true) . "\n";
        
        $deleted_count = 0;
        foreach ($user_layout_files as $layout_file) {
            $result .= "Processing: {$layout_file}\n";
            
            // Convert to actual path
            $actual_path = convertToActualPath($layout_file);
            $result .= "Actual path: {$actual_path}\n";
            
            if (file_exists($actual_path)) {
                if (unlink($actual_path)) {
                    $result .= "✅ SUCCESS: Deleted {$actual_path}\n";
                    $deleted_count++;
                } else {
                    $result .= "❌ FAILED: Could not delete {$actual_path}\n";
                }
            } else {
                $result .= "⚠️ File not found: {$actual_path}\n";
                
                // Try alternative paths
                $alternative_paths = [
                    "../../assets/uploads/" . $layout_file,
                    $layout_file,
                    str_replace('../../assets/uploads/', '', $layout_file)
                ];
                
                foreach ($alternative_paths as $alt_path) {
                    $result .= "Trying alternative: {$alt_path}\n";
                    if (file_exists($alt_path)) {
                        if (unlink($alt_path)) {
                            $result .= "✅ SUCCESS: Deleted (alternative) {$alt_path}\n";
                            $deleted_count++;
                            break;
                        }
                    }
                }
            }
        }
        
        $result .= "Total layout files deleted: {$deleted_count}\n";
        
    } else {
        $result .= "❌ Invalid user layout files JSON\n";
        
        // Manual extraction
        preg_match_all('/user_layouts\/\d+\/[^"\'\]\s]+\.(png|jpg|jpeg|gif|svg)/i', $user_layout_files_json, $matches);
        if (!empty($matches[0])) {
            $result .= "Manually extracted files: " . print_r($matches[0], true) . "\n";
            
            $deleted_count = 0;
            foreach ($matches[0] as $file_path) {
                $actual_path = convertToActualPath($file_path);
                if (file_exists($actual_path) && unlink($actual_path)) {
                    $result .= "✅ SUCCESS: Deleted {$actual_path}\n";
                    $deleted_count++;
                }
            }
            $result .= "Total manually extracted layout files deleted: {$deleted_count}\n";
        }
    }
    
    // Clean up empty user directories
    cleanupUserDirectories($user_id);
    
    $result .= "=== END DELETE USER LAYOUT FILES DEBUG ===\n\n";
    file_put_contents('../debug_file_deletion.txt', $result, FILE_APPEND);
    return $result;
}

/**
 * Convert database file path to actual file system path
 */
function convertDbPathToFilePath($db_path) {
    // If path already starts with ../assets/uploads/, return as-is
    if (strpos($db_path, '../../assets/uploads/') === 0) {
        return $db_path;
    }
    
    // If path starts with assets/uploads/, add ../
    if (strpos($db_path, '../../assets/uploads/') === 0) {
        return '../' . $db_path;
    }
    
    // If it's a relative path like "save_design/21/filename.png"
    // or "user_layouts/21/filename.png", prepend the base path
    if (strpos($db_path, 'save_design/') === 0 || strpos($db_path, 'user_layouts/') === 0) {
        return '../../assets/uploads/' . $db_path;
    }
    
    // Return as-is if none of the above
    return $db_path;
}

/**
 * Clean up empty user directories in both save_design and user_layouts
 */
function cleanupUserDirectories($user_id) {
    error_log("=== CLEANUP DIRECTORIES DEBUG ===");
    
    $base_dirs = [
        "../../assets/uploads/save_design/",
        "../../assets/uploads/user_layouts/"
    ];
    
    foreach ($base_dirs as $base_dir) {
        $user_dir = $base_dir . $user_id . '/';
        error_log("Checking directory: " . $user_dir);
        
        if (is_dir($user_dir)) {
            $files_in_dir = scandir($user_dir);
            // Remove . and .. from count
            $actual_files = array_diff($files_in_dir, ['.', '..']);
            
            error_log("Files in directory: " . print_r($actual_files, true));
            
            if (count($actual_files) === 0) {
                if (rmdir($user_dir)) {
                    error_log("✅ Removed empty directory: " . $user_dir);
                } else {
                    error_log("❌ Failed to remove directory: " . $user_dir);
                }
            } else {
                error_log("📁 Directory not empty, keeping: " . $user_dir . " (contains " . count($actual_files) . " files)");
            }
        } else {
            error_log("Directory does not exist: " . $user_dir);
        }
    }
    error_log("=== END CLEANUP DIRECTORIES DEBUG ===");
}

$inventory->close();
?>