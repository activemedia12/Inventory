    <?php
if (!function_exists('like_escape')) {
    // Escapes % and _ (LIKE wildcards) and backslash (the default LIKE
    // escape character) so a search like "50%" or "a_b" is matched
    // literally instead of as a wildcard pattern.
    function like_escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

if (!function_exists('get_filtered_papers')) {
    /**
     * Fetches paper products matching the given (partial, case-insensitive)
     * search terms. Any term left blank is skipped, so passing just one of
     * the three still returns a valid result set.
     *
     * @param string $type_filter Paper Type search term (product_type)
     * @param string $size_filter Paper Size search term (product_group)
     * @param string $name_filter Paper Name search term (product_name)
     */
    function get_filtered_papers(mysqli $inventory, string $type_filter, string $size_filter, string $name_filter): array
    {
        $sql = "
          SELECT 
            p.id,
            p.product_type, 
            p.product_group AS paper_size, 
            p.product_name, 
            p.unit_price,
            COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS available_sheets,
            u2.username
          FROM products p
          LEFT JOIN (
            SELECT product_id, SUM(delivered_reams * 500) AS total_delivered
            FROM delivery_logs
            GROUP BY product_id
          ) d ON d.product_id = p.id
          LEFT JOIN (
            SELECT product_id, SUM(used_sheets + COALESCE(spoilage_sheets, 0)) AS total_used
            FROM usage_logs
            GROUP BY product_id
          ) u ON u.product_id = p.id
          LEFT JOIN users u2 ON p.created_by = u2.id
          WHERE 1=1
        ";

        $params = [];
        $types = '';

        if ($type_filter !== '') {
            $sql .= " AND p.product_type LIKE ?";
            $params[] = '%' . like_escape($type_filter) . '%';
            $types .= 's';
        }
        if ($size_filter !== '') {
            $sql .= " AND p.product_group LIKE ?";
            $params[] = '%' . like_escape($size_filter) . '%';
            $types .= 's';
        }
        if ($name_filter !== '') {
            $sql .= " AND p.product_name LIKE ?";
            $params[] = '%' . like_escape($name_filter) . '%';
            $types .= 's';
        }
        $sql .= " ORDER BY p.product_type, p.product_group, p.product_name";

        $stmt = $inventory->prepare($sql);
        if (!$stmt) {
            die("Error in papers search query: " . $inventory->error);
        }
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();

        return $products;
    }
}