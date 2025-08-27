<?php if (!empty($orders_to_show)): ?>
  <div class="compact-orders">
    <?php foreach ($orders_to_show as $client => $dates): ?>
      <div class="compact-client">
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
                              <!-- <th>Tax Payer Name</th>
                              <th>OCN Number</th>
                              <th>Date Issued</th> -->
                              <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Recorded By</th>
                              <?php endif; ?>
                              <?php if ($status_title === 'Completed'): ?>
                                <th>Date Completed</th>
                              <?php endif; ?>
                              <th>Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($records as $order): ?>
                              <?php
                              $order_with_date = $order;
                              $order_with_date['job_order_date'] = $date;
                              ?>
                              <tr class="clickable-row"
                                data-order='<?= htmlspecialchars(json_encode($order_with_date), ENT_QUOTES, 'UTF-8') ?>'
                                data-role="<?= htmlspecialchars($_SESSION['role']) ?>">
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
                                <!-- <td><?= htmlspecialchars($order['taxpayer_name']) ?></td>
                                <td><?= htmlspecialchars($order['ocn_number']) ?></td>
                                <td><?= $order['date_issued'] ? date("F j, Y", strtotime($order['date_issued'])) : 'Pending' ?></td> -->
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                  <td><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></td>
                                <?php endif; ?>
                                <?php if ($status_title === 'Completed'): ?>
                                  <td><?= $order['completed_date'] ? date("F j, Y - g:i A", strtotime($order['completed_date'])) : '-' ?></td>
                                <?php endif; ?>
                                <td>
                                  <button class="quick-fill-btn" data-order='<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>'>
                                    Load to Form
                                  </button>
                                  <button class="quick-fill-btn" onclick="printJobOrder(this)">
                                    Print Job Order
                                  </button>
                                  <button class="quick-fill-btn" 
                                    onclick="window.location.href='paper_cost.php?id=<?= $order['id'] ?>'">
                                    Compute Paper Cost
                                  </button>
                                </td>
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