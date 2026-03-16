<?php
// job_order_card_renderer.php

// ────────────────────────────────────────────────
// Show nice empty state when there are no orders
// ────────────────────────────────────────────────
if (empty($orders_to_show)) {
    ?>
    <div class="empty-status-state">
        <div class="empty-icon">
            <i class="far fa-folder-open fa-2x"></i>
        </div>
        <p>No <?= htmlspecialchars(strtolower($status_title)) ?> job orders right now</p>
        
        <?php if ($status_title === 'Pending'): ?>
            <small>New job orders will appear here</small>
        <?php elseif ($status_title === 'Completed'): ?>
            <small>Finished orders move here automatically</small>
        <?php elseif ($status_title === 'Unpaid'): ?>
            <small>Paid orders will be removed from this list</small>
        <?php elseif ($status_title === 'For Delivery'): ?>
            <small>Orders ready for delivery will appear here</small>
        <?php endif; ?>
    </div>
    <?php
    return;
}

// ────────────────────────────────────────────────
// For completed orders: flatten → paginate → rebuild
// ────────────────────────────────────────────────
if ($status_title === 'Completed' && isset($completed_per_page)) {
    // Flatten all records into a single list preserving client/date/project
    $flat = [];
    foreach ($orders_to_show as $client => $dates) {
        foreach ($dates as $date => $projects) {
            foreach ($projects as $project_key => $project_data) {
                foreach ($project_data['records'] as $record) {
                    $flat[] = [
                        'client'          => $client,
                        'date'            => $date,
                        'project_key'     => $project_key,
                        'project_display' => $project_data['display'],
                        'record'          => $record,
                    ];
                }
            }
        }
    }

    $completed_total      = count($flat);
    $completed_total_pages = max(1, (int)ceil($completed_total / $completed_per_page));
    $completed_page       = min($completed_page, $completed_total_pages);
    $completed_offset     = ($completed_page - 1) * $completed_per_page;
    $page_slice           = array_slice($flat, $completed_offset, $completed_per_page);

    // Rebuild the nested structure from the page slice only
    $orders_to_show = [];
    foreach ($page_slice as $item) {
        $c  = $item['client'];
        $d  = $item['date'];
        $pk = $item['project_key'];
        if (!isset($orders_to_show[$c]))           $orders_to_show[$c] = [];
        if (!isset($orders_to_show[$c][$d]))       $orders_to_show[$c][$d] = [];
        if (!isset($orders_to_show[$c][$d][$pk]))  $orders_to_show[$c][$d][$pk] = [
            'display'  => $item['project_display'],
            'records'  => [],
        ];
        $orders_to_show[$c][$d][$pk]['records'][] = $item['record'];
    }

    // Build pagination URL (preserve all existing GET params except completed_page)
    $qp = $_GET;
    unset($qp['completed_page']);
    $base_url   = 'job_orders.php?' . http_build_query($qp) . ($qp ? '&' : '');
    $win        = 2;
    $start_pg   = max(1, $completed_page - $win);
    $end_pg     = min($completed_total_pages, $completed_page + $win);
}
?>

<!-- If we reached here → there ARE orders -->
<div class="compact-orders">
    <?php foreach ($orders_to_show as $client => $dates): ?>
      <div class="compact-client">
        <div class="compact-client-header" data-client="<?= htmlspecialchars($client) ?>" onclick="toggleClient(this)">
          <span class="compact-client-name"><?= htmlspecialchars($client) ?></span>
          <span class="compact-client-count"><?= count($dates) ?> projects</span>
        </div>
        <div class="compact-project-group" style="display:none;">
          <?php
          $all_projects = [];
          foreach ($dates as $date => $projects) {
            foreach ($projects as $project_key => $project_data) {
              if (!isset($all_projects[$project_key])) {
                $all_projects[$project_key] = [
                  'display' => $project_data['display'],
                  'dates' => []
                ];
              }
              $all_projects[$project_key]['dates'][$date] = $project_data['records'];
            }
          }
          ?>
          <?php foreach ($all_projects as $project_key => $project_data): ?>
            <div>
              <div class="compact-project-header" data-client="<?= htmlspecialchars($client) ?>" data-project="<?= htmlspecialchars($project_key) ?>" onclick="toggleProject(this)">
                <span>
                  <i class="fas fa-folder-open"></i>
                  <?= htmlspecialchars($project_data['display']) ?>
                </span>
                <span class="compact-client-count">
                  <?= array_sum(array_map('count', $project_data['dates'])) ?> dates
                </span>
              </div>
              <div class="compact-date-group" style="display:none;">
                <?php foreach ($project_data['dates'] as $date => $records): ?>
                  <div>
                    <div class="compact-date-header" data-client="<?= htmlspecialchars($client) ?>" data-project="<?= htmlspecialchars($project_key) ?>" data-date="<?= htmlspecialchars($date) ?>" onclick="toggleDate(this)">
                      <span class="compact-date-text">
                        <i class="fas fa-calendar-alt"></i>
                        <?= date("F j, Y", strtotime($date)) ?>
                      </span>
                      <span class="compact-client-count"><?= count($records) ?> orders</span>
                    </div>
                    <div class="compact-order-item" style="display:none;">
                      <div class="order-details-table-container">
                        <table class="order-details-table">
                          <thead>
                            <tr>
                              <th>Actions</th>
                              <th>Quantity</th>
                              <th>Sets per bind</th>
                              <th>Cut Size</th>
                              <th>Paper Size</th>
                              <th>Serial Range</th>
                              <th>Paper Type</th>
                              <th>Copies per Set</th>
                              <th>Binding</th>
                              <th>Color Sequence</th>
                              <th>Special Instructions</th>
                              <th>Total Expenses</th>
                              <th>Total Cost (₱)</th>
                              <th>Profit (₱)</th>
                              <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Recorded By</th>
                              <?php endif; ?>
                              <?php if ($status_title === 'Completed'): ?>
                                <th>Date Completed</th>
                              <?php endif; ?>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($records as $order): ?>
                              <?php
                              $order_with_date = $order;
                              $order_with_date['job_order_date'] = $date;
                              $expenses      = floatval($order['grand_total']    ?? 0);
                              $total_cost    = floatval($order['total_cost']     ?? 0);
                              $layout_fee    = floatval($order['layout_fee']     ?? 0);
                              $discount_type = $order['discount_type']           ?? 'amount';
                              $discount_val  = floatval($order['discount_value'] ?? 0);
                              $discount_amt  = $discount_type === 'percent'
                                ? ($total_cost + $layout_fee) * ($discount_val / 100)
                                : $discount_val;
                              $final_amount  = $total_cost + $layout_fee - $discount_amt;
                              $profit        = $final_amount - $expenses;
                              $profit_margin = $final_amount > 0 ? ($profit / $final_amount) * 100 : 0;
                              $profit_class  = $profit >= 0 ? 'profit-positive' : 'profit-negative';
                              ?>
                              <tr class="clickable-row"
                                data-order='<?= htmlspecialchars(json_encode($order_with_date), ENT_QUOTES, 'UTF-8') ?>'
                                data-role="<?= htmlspecialchars($_SESSION['role']) ?>">
                                <td>
                                  <button class="quick-fill-btn" data-order='<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>'>
                                    Load to Form
                                  </button>
                                  <button class="quick-fill-btn" onclick="printJobOrder(this)">
                                    Print Job Order
                                  </button>
                                  <button class="quick-fill-btn" 
                                    onclick="window.location.href='paper_cost.php?id=<?= $order['id'] ?>'">
                                    Show Expenses
                                  </button>
                                  <button class="quick-fill-btn set-cost-btn" 
                                    onclick="setTotalCost(this)"
                                    data-id="<?= $order['id'] ?>"
                                    data-client="<?= htmlspecialchars($order['client_name'], ENT_QUOTES) ?>"
                                    data-project="<?= htmlspecialchars($order['project_name'], ENT_QUOTES) ?>"
                                    title="Set Total Cost">
                                    Set Total Cost
                                  </button>
                                </td>
                                <td><?= $order['quantity'] ?></td>
                                <td><?= $order['number_of_sets'] ?></td>
                                <td><?= htmlspecialchars($order['product_size']) ?></td>
                                <td><?= $order['paper_size'] === 'custom' ? htmlspecialchars($order['custom_paper_size']) : htmlspecialchars($order['paper_size']) ?></td>
                                <td><?= htmlspecialchars($order['serial_range']) ?></td>
                                <td><?= htmlspecialchars($order['paper_type']) ?></td>
                                <td><?= $order['copies_per_set'] ?></td>
                                <td><?= $order['binding_type'] === 'Custom' ? htmlspecialchars($order['custom_binding']) : htmlspecialchars($order['binding_type']) ?></td>
                                <td>
                                  <?php foreach (explode(',', $order['paper_sequence']) as $color): ?>
                                    <span class="sequence-item"><?= trim(htmlspecialchars($color)) ?></span>
                                  <?php endforeach; ?>
                                </td>
                                <td><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></td>
                                <td>
                                  <?php 
                                  if (empty($order['grand_total']) || $order['grand_total'] == 0.00) {
                                      echo "Not Computed";
                                      echo '<br><a href="paper_cost.php?id=' . $order['id'] . '" class="btn">Compute Now</a>';
                                  } else {
                                      echo "₱ " . number_format($order['grand_total'], 2);
                                  }
                                  ?>
                                </td>
                                <td class="total-cost-cell" id="total-cost-<?= $order['id'] ?>">
                                  <?php if ($total_cost > 0): ?>
                                    ₱ <?= number_format($final_amount, 2) ?>
                                    <?php if ($layout_fee > 0 || $discount_val > 0): ?>
                                      <br><small class="text-muted">
                                        Base: ₱<?= number_format($total_cost, 2) ?>
                                        <?php if ($layout_fee > 0): ?> +₱<?= number_format($layout_fee, 2) ?> fee<?php endif; ?>
                                        <?php if ($discount_val > 0): ?> -<?= $discount_type === 'percent' ? number_format($discount_val, 1).'%' : '₱'.number_format($discount_amt, 2) ?> disc<?php endif; ?>
                                      </small>
                                    <?php endif; ?>
                                  <?php else: ?>
                                    <span class="text-muted">Not Set</span>
                                  <?php endif; ?>
                                </td>
                                <td class="profit-cell <?= $profit_class ?>" id="profit-<?= $order['id'] ?>">
                                  <?php
                                  if ($expenses > 0 && $total_cost > 0):
                                  ?>
                                    ₱ <?= number_format($profit, 2) ?>
                                    <br>
                                    <small class="<?= $profit_class ?>">
                                      (<?= number_format($profit_margin, 1) ?>%)
                                    </small>
                                  <?php elseif ($expenses <= 0): ?>
                                    <span class="text-muted" title="Expenses not computed yet">Compute Expenses First</span>
                                  <?php else: ?>
                                    <span class="text-muted" title="Total cost not set">-</span>
                                  <?php endif; ?>
                                </td>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                  <td><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></td>
                                <?php endif; ?>
                                <?php if ($status_title === 'Completed'): ?>
                                  <td><?= $order['completed_date'] ? date("F j, Y - g:i A", strtotime($order['completed_date'])) : '-' ?></td>
                                <?php endif; ?>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
</div>

<?php if ($status_title === 'Completed' && isset($completed_total_pages) && $completed_total_pages > 1): ?>
<div class="pagination-bar">
  <span style="font-size:12px; color:var(--gray); margin-right:6px;">
    <?= number_format($completed_offset + 1) ?>–<?= number_format(min($completed_offset + $completed_per_page, $completed_total)) ?> of <?= number_format($completed_total) ?>
  </span>

  <?php if ($completed_page > 1): ?>
    <a href="<?= $base_url ?>completed_page=<?= $completed_page - 1 ?>" class="page-btn"><i class="fas fa-chevron-left"></i> Prev</a>
  <?php else: ?>
    <span class="page-btn disabled"><i class="fas fa-chevron-left"></i> Prev</span>
  <?php endif; ?>

  <?php if ($start_pg > 1): ?>
    <a href="<?= $base_url ?>completed_page=1" class="page-btn">1</a>
    <?php if ($start_pg > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $start_pg; $i <= $end_pg; $i++): ?>
    <?php if ($i === $completed_page): ?>
      <span class="page-btn active"><?= $i ?></span>
    <?php else: ?>
      <a href="<?= $base_url ?>completed_page=<?= $i ?>" class="page-btn"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>

  <?php if ($end_pg < $completed_total_pages): ?>
    <?php if ($end_pg < $completed_total_pages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
    <a href="<?= $base_url ?>completed_page=<?= $completed_total_pages ?>" class="page-btn"><?= $completed_total_pages ?></a>
  <?php endif; ?>

  <?php if ($completed_page < $completed_total_pages): ?>
    <a href="<?= $base_url ?>completed_page=<?= $completed_page + 1 ?>" class="page-btn">Next <i class="fas fa-chevron-right"></i></a>
  <?php else: ?>
    <span class="page-btn disabled">Next <i class="fas fa-chevron-right"></i></span>
  <?php endif; ?>
</div>
<?php endif; ?>