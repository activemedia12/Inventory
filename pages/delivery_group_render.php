<?php
if (!function_exists('render_delivery_group')) {
    /**
     * Renders one date's delivery-group block (paper table + insuance table).
     *
     * @param string $date           Y-m-d
     * @param array  $product_logs   Rows for this date from delivery_logs (delivery-date-desc, id-desc order as fetched)
     * @param array  $insuance_logs  Rows for this date from insuance_delivery_logs
     * @param bool   $is_admin       Whether to show the "Recorded By" / "Actions" columns
     * @param bool   $reveal         Apply the scroll-reveal ".hide" class — only used for the batch
     *                               rendered on initial page load, since that's the only batch the
     *                               page's IntersectionObserver is set up to watch. Groups appended
     *                               later via AJAX render already-visible (no reveal animation).
     */
    function render_delivery_group(string $date, array $product_logs, array $insuance_logs, bool $is_admin, bool $reveal = false): string
    {
        ob_start();
        $groupClass = $reveal ? 'delivery-group hide' : 'delivery-group';
        ?>
        <div class="<?= $groupClass ?>">
          <button class="toggle-btn" onclick="toggleGroup(this)">
            <i class="fas fa-calendar-alt"></i> <?= date("F j, Y", strtotime($date)) ?>
          </button>

          <div class="group-content" style="display: none;">

            <?php if (!empty($product_logs)): ?>
              <!-- Table 1: Paper Deliveries -->
              <table>
                <thead>
                  <tr>
                    <th>Paper</th>
                    <th>Reams</th>
                    <th>Unit</th>
                    <th>Amount</th>
                    <th>Supplier</th>
                    <th>Note</th>
                    <?php if ($is_admin): ?>
                      <th>Recorded By</th>
                      <th>Actions</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_reverse($product_logs) as $log): ?>
                    <tr class="clickable-row" data-id="<?= $log['product_id'] ?>">
                      <td><?= htmlspecialchars("{$log['product_type']} - {$log['product_group']} - {$log['product_name']}") ?></td>
                      <td><?= number_format($log['delivered_reams'], 2) ?></td>
                      <td><?= htmlspecialchars($log['unit'] ?? '---') ?></td>
                      <td>₱<?= number_format($log['amount_per_ream'], 2) ?></td>
                      <td><?= htmlspecialchars($log['supplier_name'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($log['delivery_note']) ?></td>
                      <?php if ($is_admin): ?>
                        <td><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                        <td class="action-cell">
                          <a href="edit_delivery.php?id=<?= $log['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                          <a href="delete_delivery.php?id=<?= $log['id'] ?>" title="Delete"><i class="fas fa-trash"></i></a>
                        </td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>

            <?php if (!empty($insuance_logs)): ?>
              <!-- Table 2: Insuance Deliveries -->
              <div style="margin-top: 20px;"></div>
              <table>
                <thead>
                  <tr>
                    <th>Consumables</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Amount/Unit</th>
                    <th>Supplier</th>
                    <th>Note</th>
                    <?php if ($is_admin): ?>
                      <th>Recorded By</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_reverse($insuance_logs) as $log): ?>
                    <tr>
                      <td><?= htmlspecialchars($log['insuance_name']) ?></td>
                      <td><?= number_format($log['delivered_quantity'], 2) ?></td>
                      <td><?= htmlspecialchars($log['unit'] ?? '-') ?></td>
                      <td>₱<?= number_format($log['amount_per_unit'], 2) ?></td>
                      <td><?= htmlspecialchars($log['supplier_name'] ?: '-') ?></td>
                      <td><?= htmlspecialchars($log['delivery_note'] ?? '-') ?></td>
                      <?php if ($is_admin): ?>
                        <td><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                      <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>

          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}