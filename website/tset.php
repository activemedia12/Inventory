<?php foreach ($cart_items as $row): 
    // Use quoted price if available, otherwise use unit price
    $actual_price = $row['price_updated_by_admin'] ? $row['quoted_price'] : $row['unit_price'];
    $item_total = $actual_price * $row['quantity'];
    $is_selected = in_array($row['item_id'], $selected_items);
    $has_admin_price = $row['price_updated_by_admin'];
?>
    <div class="cart-item">
        <div class="item-checkbox">
            <input type="checkbox" name="selected_items[]" value="<?php echo $row['item_id']; ?>" 
                <?php echo $is_selected ? 'checked' : ''; ?>
                onchange="updateCartTotal()">
        </div>
        
        <div class="cart-item-content">
            <div class="cart-item-image">
                <?php
                $image_path = "../assets/images/services/service-" . $row['id'] . ".jpg";
                $image_url = file_exists($image_path) ? $image_path : "https://via.placeholder.com/140x140/2c5aa0/ffffff?text=Product";
                ?>
                <img src="<?php echo $image_url; ?>" 
                     alt="<?php echo $row['product_name']; ?>">
            </div>

            <div class="cart-item-info">
                <h3><?php echo $row['product_name']; ?></h3>
                <span class="product-group"><?php echo $row['product_group']; ?></span>
                
                <!-- Price Display with Admin Update Indicator -->
                <div class="price-display">
                    <?php if ($has_admin_price): ?>
                        <div class="admin-price-notice">
                            <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                            <span style="color: #27ae60; font-weight: bold;">Price Updated by Admin</span>
                        </div>
                        <p class="price">
                            <span style="text-decoration: line-through; color: #999; margin-right: 10px;">
                                ₱<?php echo number_format($row['unit_price'], 2); ?>
                            </span>
                            <span style="color: #e74c3c; font-size: 1.3em;">
                                ₱<?php echo number_format($actual_price, 2); ?>
                            </span>
                        </p>
                    <?php else: ?>
                        <p class="price">₱<?php echo number_format($actual_price, 2); ?></p>
                    <?php endif; ?>
                </div>

                <!-- ... rest of the product details ... -->
                
                <p class="subtotal">
                    Subtotal: ₱<?php echo number_format($item_total, 2); ?>
                    <?php if ($has_admin_price): ?>
                        <br><small style="color: #27ae60;">Final price confirmed by admin</small>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- ... quantity controls and remove button ... -->
    </div>
<?php endforeach; ?>