<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

require_once '../config/db.php';

$low_stock_items = $mysqli->query("
    SELECT 
        p.id,
        p.product_type,
        p.product_group,
        p.product_name,
        ((SELECT IFNULL(SUM(delivered_reams), 0) FROM delivery_logs WHERE product_id = p.id) * 500 -
        (SELECT IFNULL(SUM(used_sheets), 0) FROM usage_logs WHERE product_id = p.id)) / 500 AS available_reams
    FROM products p
    HAVING available_reams < 20
    ORDER BY available_reams ASC
");

if ($low_stock_items->num_rows > 0) {
    echo '<table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Available Reams</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';
    
    while ($item = $low_stock_items->fetch_assoc()) {
        $status = $item['available_reams'] <= 0 ? 'Out of Stock' : 'Low Stock';
        $status_class = $item['available_reams'] <= 0 ? 'out-of-stock' : 'low-stock';
        
        echo '<tr class="clickable-row '.$status_class.'" data-id="'.$item['id'].'">
            <td>'.htmlspecialchars($item['product_type'].' - '.$item['product_group'].' - '.$item['product_name']).'</td>
            <td>'.number_format($item['available_reams'], 2).'</td>
            <td><span class="status-badge '.$status_class.'">'.$status.'</span></td>
        </tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<p>No low stock or out-of-stock items found.</p>';
}
?>