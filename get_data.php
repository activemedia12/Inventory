<?php
$mysqli = new mysqli("localhost", "u382513771_admin", "Amdp@1205", "u382513771_inventory");

// Fetch all products
$result = $mysqli->query("SELECT id, type, section, product, unit_price FROM stock_data ORDER BY type, section, product");

$data = [];

while ($row = $result->fetch_assoc()) {
    $product_id = $row['id'];
    $type = $row['type'];
    $section = $row['section'];
    $product = $row['product'];
    $existing_unit_price = floatval($row['unit_price']);

    // Calculate stock balance
    $delivery_total = 0;
    $dres = $mysqli->query("SELECT delivery_total FROM stock_delivery_logs WHERE product_id = $product_id");
    while ($d = $dres->fetch_assoc()) {
        $delivery_total += floatval($d['delivery_total']);
    }

    $used_total = 0;
    $ures = $mysqli->query("SELECT used_total FROM stock_usage_logs WHERE product_id = $product_id");
    while ($u = $ures->fetch_assoc()) {
        $used_total += floatval($u['used_total']);
    }

    $stock_balance = $delivery_total - $used_total;

    // Determine base price fallback
    if (stripos($product, 'Top White') !== false) {
        $base_price = 2425;
    } elseif (stripos($product, 'Middle') !== false) {
        $base_price = 2525;
    } elseif (stripos($product, 'Bottom') !== false) {
        $base_price = 2375;
    } else {
        $base_price = 0;
    }

    // Determine unit price
    $section_upper = strtoupper($section);
    if ($existing_unit_price > 0) {
        $unit_price = $existing_unit_price;
    } else {
        if (in_array($section_upper, ['SHORT', 'LONG', '11X17'])) {
            $divider = match($section_upper) {
                'SHORT' => 10,
                'LONG' => 8,
                '11X17' => 5,
                default => 1
            };
            $unit_price = $divider > 0 ? $base_price / $divider : 0;
        } else {
            $unit_price = $base_price;
        }
    }

    $amount = $unit_price * $stock_balance;

    // Update stock_data table
    $update = $mysqli->prepare("UPDATE stock_data SET stock_balance = ?, unit_price = ?, amount = ? WHERE id = ?");
    $update->bind_param("dddi", $stock_balance, $unit_price, $amount, $product_id);
    $update->execute();

    // Prepare row for grouping
    $row['stock_balance'] = $stock_balance;
    $row['unit_price'] = $unit_price;
    $row['amount'] = $amount;

    // Add to flat list
    $data[] = $row;
}

// Group by type → section
$grouped = [];

foreach ($data as $item) {
    $type = $item['type'];
    $section = $item['section'];

    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }

    if (!isset($grouped[$type][$section])) {
        $grouped[$type][$section] = [];
    }

    $grouped[$type][$section][] = $item;
}

// Output JSON
header("Content-Type: application/json");
echo json_encode($grouped);
?>
