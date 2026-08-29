<?php
if (!function_exists('get_delivery_date_page')) {
    /**
     * Returns a page of distinct delivery dates (paper + insuance combined),
     * most recent first. Requests one extra date over $limit so we can tell
     * if there's a next page without a separate COUNT query.
     *
     * @return array{0: string[], 1: bool} [dates, has_more]
     */
    function get_delivery_date_page(mysqli $inventory, string $date_filter_sql, string $date_filter_sql_ins, int $limit, int $offset): array
    {
        $fetch_limit = $limit + 1;
        $query = "
            SELECT d FROM (
                SELECT dl.delivery_date AS d FROM delivery_logs dl WHERE 1=1 {$date_filter_sql}
                UNION
                SELECT idl.delivery_date AS d FROM insuance_delivery_logs idl WHERE 1=1 {$date_filter_sql_ins}
            ) combined_dates
            ORDER BY d DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $inventory->prepare($query);
        if (!$stmt) {
            die("Error in delivery dates query: " . $inventory->error);
        }
        $stmt->bind_param("ii", $fetch_limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $dates = array_column($rows, 'd');
        $has_more = count($dates) > $limit;
        if ($has_more) {
            array_pop($dates); // drop the lookahead row
        }

        return [$dates, $has_more];
    }

    /**
     * Fetches paper delivery logs for only the given dates, grouped by date.
     * (Instead of pulling every row in the whole date-range filter.)
     */
    function get_product_logs_for_dates(mysqli $inventory, array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $query = "
            SELECT dl.*, p.product_type, p.product_group, p.product_name, u.username
            FROM delivery_logs dl
            JOIN products p ON dl.product_id = p.id
            LEFT JOIN users u ON dl.created_by = u.id
            WHERE dl.delivery_date IN ($placeholders)
            ORDER BY dl.delivery_date DESC, dl.id DESC
        ";
        $stmt = $inventory->prepare($query);
        if (!$stmt) {
            die("Error in product logs query: " . $inventory->error);
        }
        $types = str_repeat('s', count($dates));
        $stmt->bind_param($types, ...$dates);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['delivery_date']][] = $row;
        }
        return $grouped;
    }

    /**
     * Fetches insuance delivery logs for only the given dates, grouped by date.
     */
    function get_insuance_logs_for_dates(mysqli $inventory, array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $query = "
            SELECT idl.*, u.username
            FROM insuance_delivery_logs idl
            LEFT JOIN users u ON idl.created_by = u.id
            WHERE idl.delivery_date IN ($placeholders)
            ORDER BY idl.delivery_date DESC, idl.id DESC
        ";
        $stmt = $inventory->prepare($query);
        if (!$stmt) {
            die("Error in insuance logs query: " . $inventory->error);
        }
        $types = str_repeat('s', count($dates));
        $stmt->bind_param($types, ...$dates);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['delivery_date']][] = $row;
        }
        return $grouped;
    }
}