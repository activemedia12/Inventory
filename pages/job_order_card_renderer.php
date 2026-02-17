<?php if (!empty($orders_to_show)): ?>
  <div class="compact-orders">
    <?php foreach ($orders_to_show as $client => $dates): ?>
      <div class="compact-client hide">
        <div class="compact-client-header" data-client="<?= htmlspecialchars($client) ?>" onclick="toggleClient(this)">
          <span class="compact-client-name"><?= htmlspecialchars($client) ?></span>
          <span class="compact-client-count"><?= count($dates) ?> projects</span>
        </div>

        <div class="compact-project-group" style="display:none;">
          <?php
          // First collect all projects across all dates for this client
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
                              
                              // Calculate profit
                              $expenses = floatval($order['grand_total'] ?? 0);
                              $total_cost = floatval($order['total_cost'] ?? 0);
                              $profit = $total_cost - $expenses;
                              $profit_class = $profit >= 0 ? 'profit-positive' : 'profit-negative';
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
                                    onclick="setTotalCost(<?= $order['id'] ?>, '<?= htmlspecialchars($order['client_name']) ?>', '<?= htmlspecialchars($order['project_name']) ?>')"
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
                                  <?php if (!empty($order['total_cost']) && $order['total_cost'] > 0): ?>
                                    ₱ <?= number_format($order['total_cost'], 2) ?>
                                  <?php else: ?>
                                    <span class="text-muted">Not Set</span>
                                  <?php endif; ?>
                                </td>
                                <td class="profit-cell <?= $profit_class ?>" id="profit-<?= $order['id'] ?>">
                                  <?php 
                                  // Check if expenses are computed AND total cost is set
                                  if (!empty($order['grand_total']) && $order['grand_total'] > 0 && 
                                      !empty($order['total_cost']) && $order['total_cost'] > 0): 
                                  ?>
                                    ₱ <?= number_format($profit, 2) ?>
                                    <br>
                                    <small class="<?= $profit_class ?>">
                                      (<?= number_format(($expenses > 0 ? ($profit / $expenses) * 100 : 0), 1) ?>%)
                                    </small>
                                  <?php elseif (empty($order['grand_total']) || $order['grand_total'] == 0.00): ?>
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
<?php endif; ?>