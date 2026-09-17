<?php
if (!function_exists('paper_stock_class')) {
    // Same thresholds used on the dashboard: <=0 sheets is out of stock,
    // under 10,000 sheets (20 reams) is running low.
    function paper_stock_class($sheets)
    {
        if ($sheets <= 0) return 'low';
        if ($sheets < 10000) return 'mid';
        return 'high';
    }
}

if (!function_exists('format_stock')) {
    function format_stock($sheets, $stock_unit)
    {
        return $stock_unit === 'reams'
            ? number_format($sheets / 500, 2) . ' reams'
            : number_format($sheets, 2) . ' sheets';
    }
}

if (!function_exists('render_papers_table')) {
    /**
     * Renders the "Paper Inventory" table content: grouped by product_type,
     * then by paper size (product_group) within each type. Used both for
     * papers.php's initial render and for papers_search.php's live-search
     * AJAX responses, so the two can never drift apart.
     *
     * @param array  $products   Flat product rows: id, product_type, paper_size,
     *                           product_name, unit_price, available_sheets, username
     * @param string $stock_unit 'reams' or 'sheets'
     * @param bool   $is_admin   Whether to show the "Recorded By" / "Actions" columns
     */
    function render_papers_table(array $products, string $stock_unit, bool $is_admin): string
    {
        $grouped_products = [];
        foreach ($products as $prod) {
            $grouped_products[$prod['product_type']][$prod['paper_size']][] = $prod;
        }

        ob_start();

        if (empty($grouped_products)) {
        ?>
          <div class="empty-message"><i class="fas fa-info-circle"></i> No papers found for the selected filters.</div>
        <?php
        }

        foreach ($grouped_products as $type => $sizes):
          $type_item_count = array_sum(array_map('count', $sizes));
          $type_total_sheets = 0;
          foreach ($sizes as $items) {
            $type_total_sheets += array_sum(array_column($items, 'available_sheets'));
          }
        ?>
          <div class="product-type-block">
            <h4 class="collapsible-header" data-key="<?= htmlspecialchars($type) ?>" onclick="toggleProductGroup(this)">
              <span><i class="fas fa-chevron-right"></i> <?= htmlspecialchars($type) ?></span>
              <span class="type-header-meta">
                <span class="badge"><?= count($sizes) ?> size<?= count($sizes) === 1 ? '' : 's' ?></span>
                <span class="badge"><?= $type_item_count ?> item<?= $type_item_count === 1 ? '' : 's' ?></span>
                <span class="stock-pill <?= paper_stock_class($type_total_sheets) ?>"><?= format_stock($type_total_sheets, $stock_unit) ?></span>
              </span>
            </h4>

            <div class="product-content">
              <div class="size-groups">
                <?php foreach ($sizes as $size => $items):
                  $size_total_sheets = array_sum(array_column($items, 'available_sheets'));
                ?>
                  <div class="size-group">
                    <div class="size-group-header">
                      <div class="size-group-title">
                        <span class="size-tag"><i class="fas fa-ruler-combined"></i> <?= htmlspecialchars($size) ?></span>
                        <span class="size-count"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
                      </div>
                      <span class="stock-pill <?= paper_stock_class($size_total_sheets) ?>">
                        <?= format_stock($size_total_sheets, $stock_unit) ?> total
                      </span>
                    </div>

                    <table>
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Unit Price</th>
                          <th>Stock</th>
                          <?php if ($is_admin): ?>
                            <th>Recorded By</th>
                            <th>Actions</th>
                          <?php endif; ?>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($items as $prod): ?>
                          <tr class="clickable-row" data-id="<?= $prod['id'] ?>">
                            <td><?= htmlspecialchars($prod['product_name']) ?></td>
                            <td>₱<?= number_format($prod['unit_price'], 2) ?></td>
                            <td>
                              <span class="stock-pill <?= paper_stock_class($prod['available_sheets']) ?>">
                                <?= format_stock($prod['available_sheets'], $stock_unit) ?>
                              </span>
                            </td>
                            <?php if ($is_admin): ?>
                              <td><?= htmlspecialchars($prod['username'] ?? 'Unknown') ?></td>
                              <td class="action-cell">
                                <a href="edit_product.php?id=<?= $prod['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete_product.php?id=<?= $prod['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete"><i class="fas fa-trash"></i></a>
                              </td>
                            <?php endif; ?>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endforeach;

        return ob_get_clean();
    }
}