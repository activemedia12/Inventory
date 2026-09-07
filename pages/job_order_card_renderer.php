<?php if ($status_title === 'Completed' && isset($completed_available_letters)): ?>
  <?php
    $letter_qp = $_GET;
    unset($letter_qp['completed_page'], $letter_qp['completed_letter']);
    $letter_base = 'job_orders.php?' . http_build_query($letter_qp) . ($letter_qp ? '&' : '');
  ?>
  <div class="letter-nav">
    <a class="letter-btn<?= $completed_letter === '' ? ' active' : '' ?>" href="<?= $letter_base ?>completed_letter=&completed_page=1">All</a>
    <?php foreach (range('A', 'Z') as $L): ?>
      <?php $hasOrders = in_array($L, $completed_available_letters, true); ?>
      <?php if ($hasOrders): ?>
        <a class="letter-btn<?= $completed_letter === $L ? ' active' : '' ?>" href="<?= $letter_base ?>completed_letter=<?= $L ?>&completed_page=1"><?= $L ?></a>
      <?php else: ?>
        <span class="letter-btn disabled"><?= $L ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
if (empty($orders_to_show)) {
?>
  <div class="empty-status-state">
    <div class="empty-icon">
      <i class="far fa-folder-open fa-2x"></i>
    </div>
    <p>
      <?php if ($status_title === 'Completed' && isset($completed_letter) && $completed_letter !== ''): ?>
        No completed job orders for clients starting with "<?= htmlspecialchars($completed_letter) ?>"
      <?php else: ?>
        No <?= htmlspecialchars(strtolower($status_title)) ?> job orders right now
      <?php endif; ?>
    </p>

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
// Completed orders arrive here already paginated at the SQL level
// (see job_orders.php) — we just need the URL/window math for the bar.
// ────────────────────────────────────────────────
if ($status_title === 'Completed' && isset($completed_per_page)) {
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
                            <th>Print Type</th>
                            <th>Quantity</th>
                            <th>Specifications</th>
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
                              id="job-order-row-<?= $order['id'] ?>"
                              data-order='<?= htmlspecialchars(json_encode($order_with_date), ENT_QUOTES, 'UTF-8') ?>'
                              data-role="<?= htmlspecialchars($_SESSION['role']) ?>">
                              <td>
                                <button class="quick-fill-btn" data-order='<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>'>
                                  Load to Form
                                </button>
                                <button class="quick-fill-btn" onclick="printJobOrder(this)">
                                  Print Job Order
                                </button>
                              </td>
                              <td>
                                <?php
                                $pt_id = $order['product_type_id'] ?? null;
                                if ($pt_id && isset($pt_lookup[$pt_id])):
                                  $pt_info = $pt_lookup[$pt_id];
                                ?>
                                  <span class="badge badge-success" style="white-space:nowrap;">
                                    <i class="fas <?= htmlspecialchars($pt_info['icon'] ?? 'fa-print') ?>"></i>
                                    <?= htmlspecialchars($pt_info['name']) ?>
                                  </span>
                                <?php else: ?>
                                  <span class="badge badge-secondary">
                                    <i class="fas fa-file-alt"></i> Paper
                                  </span>
                                <?php endif; ?>
                              </td>

                              <td><?= $order['quantity'] ?></td>

                              <td>
                                <?php
                                  $np_uses_paper = $pt_id && !empty(trim($order['paper_type'] ?? '')) && trim($order['paper_type']) !== 'N/A';
                                ?>
                                <?php if ($pt_id && isset($job_field_values[$order['id']])): ?>
                                  <!-- Non-paper job: show dynamic field values -->
                                  <div style="display:flex;flex-direction:column;gap:4px;">
                                    <?php foreach ($job_field_values[$order['id']] as $fv): ?>
                                      <?php if (trim($fv['field_value']) === '') continue; ?>
                                      <div style="font-size:12px;">
                                        <strong><?= htmlspecialchars($fv['field_label']) ?>:</strong>
                                        <?php if ($fv['field_type'] === 'checkbox'): ?>
                                          <?= $fv['field_value'] == '1' ? 'Yes' : 'No' ?>
                                        <?php elseif ($fv['field_type'] === 'dropdown'): ?>
                                          <?= htmlspecialchars($fv['option_label'] ?? $fv['field_value']) ?>
                                        <?php else: ?>
                                          <?= htmlspecialchars($fv['field_value']) ?>
                                        <?php endif; ?>
                                      </div>
                                    <?php endforeach; ?>
                                    <?php if ($np_uses_paper): ?>
                                      <div style="font-size:12px;border-top:1px dashed var(--light-gray);padding-top:4px;margin-top:2px;">
                                        <strong><i class="fas fa-scroll"></i> Paper Used:</strong>
                                        <?= htmlspecialchars($order['paper_type']) ?> / <?= htmlspecialchars($order['paper_size']) ?>
                                        <?php if (!empty($order['paper_sequence']) && trim($order['paper_sequence']) !== 'Any'): ?>
                                          (<?= htmlspecialchars($order['paper_sequence']) ?>)
                                        <?php endif; ?>
                                        <br><strong>Cut Size:</strong> <?= htmlspecialchars($order['product_size']) ?>
                                      </div>
                                    <?php endif; ?>
                                  </div>
                                <?php elseif ($pt_id && $np_uses_paper): ?>
                                  <!-- Non-paper job with no custom fields, but still consumes paper -->
                                  <div style="font-size:12px;">
                                    <strong><i class="fas fa-scroll"></i> Paper Used:</strong>
                                    <?= htmlspecialchars($order['paper_type']) ?> / <?= htmlspecialchars($order['paper_size']) ?>
                                    <?php if (!empty($order['paper_sequence']) && trim($order['paper_sequence']) !== 'Any'): ?>
                                      (<?= htmlspecialchars($order['paper_sequence']) ?>)
                                    <?php endif; ?>
                                    <br><strong>Cut Size:</strong> <?= htmlspecialchars($order['product_size']) ?>
                                  </div>
                                <?php elseif ($pt_id): ?>
                                  <span class="text-muted">No specifications recorded</span>
                                <?php else: ?>
                                  <!-- Paper job: show original paper-specific info, same as before -->
                                  <div style="display:flex;flex-direction:column;gap:3px;font-size:12px;">
                                    <div><strong>Sets:</strong> <?= $order['number_of_sets'] ?></div>
                                    <div><strong>Cut Size:</strong> <?= htmlspecialchars($order['product_size']) ?></div>
                                    <div><strong>Paper Size:</strong> <?= $order['paper_size'] === 'custom' ? htmlspecialchars($order['custom_paper_size']) : htmlspecialchars($order['paper_size']) ?></div>
                                    <div><strong>Serial Range:</strong> <?= htmlspecialchars($order['serial_range']) ?></div>
                                    <div><strong>Paper Type:</strong> <?= htmlspecialchars($order['paper_type']) ?></div>
                                    <div><strong>Copies/Set:</strong> <?= $order['copies_per_set'] ?></div>
                                    <div><strong>Binding:</strong> <?= $order['binding_type'] === 'Custom' ? htmlspecialchars($order['custom_binding']) : htmlspecialchars($order['binding_type']) ?></div>
                                    <div>
                                      <strong>Color Sequence:</strong>
                                      <?php foreach (explode(',', $order['paper_sequence']) as $color): ?>
                                        <span class="sequence-item"><?= trim(htmlspecialchars($color)) ?></span>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                <?php endif; ?>
                              </td>

                              <td><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></td>
                              <td>
                                <?php
                                $is_non_paper = !empty($order['product_type_id']) && empty($np_uses_paper);
                                if (empty($order['grand_total']) || $order['grand_total'] == 0.00) {
                                  if ($is_non_paper) {
                                    echo '<span class="text-muted">Not Set</span>';
                                    echo '<br><button class="quick-fill-btn set-expenses-btn" style="justify-self: center;" onclick="setManualExpenses(this)" data-id="' . $order['id'] . '" data-client="' . htmlspecialchars($order['client_name'], ENT_QUOTES) . '" data-project="' . htmlspecialchars($order['project_name'], ENT_QUOTES) . '">Set Expenses</button>';
                                  } else {
                                    echo "Not Computed";
                                    echo '<br><a href="paper_cost.php?id=' . $order['id'] . '" class="btn">Compute Now</a>';
                                  }
                                } else {
                                  echo "₱ " . number_format($order['grand_total'], 2);
                                  if ($_SESSION['role'] === 'admin') {
                                    if ($is_non_paper) {
                                      echo ' <button type="button" class="edit-icon-btn" onclick="setManualExpenses(this)" data-id="' . $order['id'] . '" data-client="' . htmlspecialchars($order['client_name'], ENT_QUOTES) . '" data-project="' . htmlspecialchars($order['project_name'], ENT_QUOTES) . '" title="Edit expenses"><i class="fas fa-pencil-alt"></i></button>';
                                    } else {
                                      echo ' <a href="paper_cost.php?id=' . $order['id'] . '" class="edit-icon-btn" title="Recompute expenses"><i class="fas fa-pencil-alt"></i></a>';
                                    }
                                  }
                                }
                                ?>
                              </td>
                              <td class="total-cost-cell" id="total-cost-<?= $order['id'] ?>">
                                <?php if ($total_cost > 0): ?>
                                  ₱ <?= number_format($final_amount, 2) ?>
                                  <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <button type="button" class="edit-icon-btn"
                                      onclick="setTotalCost(this)"
                                      data-id="<?= $order['id'] ?>"
                                      data-client="<?= htmlspecialchars($order['client_name'], ENT_QUOTES) ?>"
                                      data-project="<?= htmlspecialchars($order['project_name'], ENT_QUOTES) ?>"
                                      title="Edit total cost">
                                      <i class="fas fa-pencil-alt"></i>
                                    </button>
                                  <?php endif; ?>
                                  <?php if ($layout_fee > 0 || $discount_val > 0): ?>
                                    <br><small class="text-muted">
                                      Base: ₱<?= number_format($total_cost, 2) ?>
                                      <?php if ($layout_fee > 0): ?> +₱<?= number_format($layout_fee, 2) ?> fee<?php endif; ?>
                                        <?php if ($discount_val > 0): ?> -<?= $discount_type === 'percent' ? number_format($discount_val, 1) . '%' : '₱' . number_format($discount_amt, 2) ?> disc<?php endif; ?>
                                    </small>
                                  <?php endif; ?>
                                <?php else: ?>
                                  <span class="text-muted">Not Set</span>
                                  <br><button class="quick-fill-btn set-cost-btn"
                                    onclick="setTotalCost(this)"
                                    data-id="<?= $order['id'] ?>"
                                    data-client="<?= htmlspecialchars($order['client_name'], ENT_QUOTES) ?>"
                                    data-project="<?= htmlspecialchars($order['project_name'], ENT_QUOTES) ?>"
                                    title="Set Total Cost">
                                    Set Total Cost
                                  </button>
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