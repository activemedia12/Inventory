<?php
require_once '../config/db.php';

$job_id = $_GET['id'] ?? 0;

if (!$job_id) {
    die("No job order ID provided.");
}

// 1. Fetch job order data
$sql = "SELECT quantity, number_of_sets, product_size, paper_size, paper_type, paper_sequence 
        FROM job_orders WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Job order not found.");
}

$quantity       = $order['quantity'];
$number_of_sets = $order['number_of_sets'];
$product_size   = $order['product_size'];
$paper_size     = strtolower(trim($order['paper_size'])); 
$paper_type     = strtolower(trim($order['paper_type'])); 
$paper_sequence = array_map('trim', explode(',', $order['paper_sequence']));

// 2. Calculate reams (allow fractions)
$cut_size_map = [
    '1/2'=>2,'1/3'=>3,'1/4'=>4,'1/6'=>6,'1/8'=>8,
    '1/10'=>10,'1/12'=>12,'1/14'=>14,'1/16'=>16,
    '1/18'=>18,'1/20'=>20,'whole'=>1
];
$cut_size     = $cut_size_map[$product_size] ?? 1;
$total_sheets = $number_of_sets * $quantity;
$cut_sheets   = $total_sheets / $cut_size;
$reams        = $cut_sheets / 500; // fractional reams allowed

// 3. Pick correct price table
$table = ($paper_type === 'carbonless') ? "paper_prices" : "paper_cut_prices";

// 4. Mapping function (job order names → DB names)
function mapPaperType($color, $paper_type) {
    $c = strtolower($color);

    if ($paper_type === 'carbonless') {
        if (strpos($c, 'top') !== false) return 'TOP WHITE';
        if (strpos($c, 'middle') !== false) return 'MIDDLE';
        if (strpos($c, 'bottom') !== false) return 'BOTTOM';
    } else { // ordinary paper
        if (strpos($c, 'white') !== false) return 'WHITE';
        return 'COLORED';
    }

    return strtoupper($color); // fallback
}

// 5. Compute paper cost per sequence
$total_paper_cost = 0;
$layer_details = [];

foreach ($paper_sequence as $color) {
    $mappedType = mapPaperType($color, $paper_type);

    $stmt2 = $mysqli->prepare("SELECT short_price, long_price FROM $table WHERE paper_type = ?");
    $stmt2->bind_param("s", $mappedType);
    $stmt2->execute();
    $price = $stmt2->get_result()->fetch_assoc();

    if ($price) {
        if (strpos($paper_size, "long") !== false || strpos($paper_size, "f4") !== false) {
            $unit_price = $price['long_price'];
        } elseif (strpos($paper_size, "short") !== false || strpos($paper_size, "qto") !== false) {
            $unit_price = $price['short_price'];
        } elseif ($paper_size === "11x17") {
            $unit_price = $price['short_price'] * 2; // double short price
        } else {
            $unit_price = $price['long_price']; // fallback
        }

        // Compute cost for this layer
        $layer_cost = $unit_price * $reams;
        $total_paper_cost += $layer_cost;

        $layer_details[] = [
            "color" => $color, // show original label for clarity
            "mapped" => $mappedType,
            "unit_price" => $unit_price,
            "reams" => $reams,
            "cost" => $layer_cost
        ];
    }
}

// 6. Display results
echo "<h3>Paper Cost Computation</h3>";
echo "Quantity: $quantity<br>";
echo "Number of Sets: $number_of_sets<br>";
echo "Cut Size: $product_size<br>";
echo "Sheets after cut: $cut_sheets<br>";
echo "Reams per sequence: " . number_format($reams, 2) . "<br>";
echo "Paper Type: $paper_type<br>";
echo "Paper Size: $paper_size<br><br>";

echo "<ul>";
foreach ($layer_details as $layer) {
    echo "<li>{$layer['color']} (→ {$layer['mapped']}) – ₱" . number_format($layer['unit_price'], 2) .
         " × " . number_format($layer['reams'], 2) . " reams = ₱" .
         number_format($layer['cost'], 2) . "</li>";
}
echo "</ul>";

echo "<strong>Total Paper Cost: ₱" . number_format($total_paper_cost, 2) . "</strong>";
