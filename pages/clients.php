<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';

$search = trim($_GET['search_client'] ?? '');
$letterParam = strtoupper(trim($_GET['letter'] ?? ''));
$letter = (strlen($letterParam) === 1 && ctype_alpha($letterParam)) ? $letterParam : '';
$clients = [];

$perPage = 15;
$page = max(1, intval($_GET['page'] ?? 1));

// Free-text search takes precedence over the letter filter if both are present
if ($search !== '') {
    $letter = '';
}

// Which letters actually have at least one client, so the nav can grey out empty ones
$availableLetters = [];
$letterResult = $inventory->query("SELECT DISTINCT UPPER(LEFT(client_name, 1)) AS letter FROM clients ORDER BY letter ASC");
while ($row = $letterResult->fetch_assoc()) {
    if ($row['letter'] !== null && ctype_alpha($row['letter'])) {
        $availableLetters[] = $row['letter'];
    }
}

if ($search !== '') {
    $where = "client_name LIKE ?";
    $likeValue = '%' . $search . '%';
    $paramType = "s";
} elseif ($letter !== '') {
    $where = "client_name LIKE ?";
    $likeValue = $letter . '%';
    $paramType = "s";
} else {
    $where = "";
}

if ($where !== '') {
    $countStmt = $inventory->prepare("SELECT COUNT(*) FROM clients WHERE $where");
    $countStmt->bind_param($paramType, $likeValue);
    $countStmt->execute();
    $countStmt->bind_result($totalClients);
    $countStmt->fetch();
    $countStmt->close();
} else {
    $totalClients = (int) $inventory->query("SELECT COUNT(*) FROM clients")->fetch_row()[0];
}

$totalPages = max(1, (int) ceil($totalClients / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

if ($where !== '') {
    $stmt = $inventory->prepare("SELECT id, client_name FROM clients WHERE $where ORDER BY client_name ASC LIMIT ? OFFSET ?");
    $stmt->bind_param($paramType . "ii", $likeValue, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $stmt = $inventory->prepare("SELECT id, client_name FROM clients ORDER BY client_name ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
}

while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}

$provinces = [];
$result = $inventory->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $result->fetch_assoc()) {
    $provinces[] = $row['province'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Client Information - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/pages/clients.css">
</head>

<body>

    <body>
        <div class="sidebar-con">
            <div class="sidebar">
                <div class="brand">
                    <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
                </div>
                <ul class="nav-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li>
                        <a href="papers.php">
                            <i class="fas fa-boxes"></i> <span>Products</span>
                        </a>
                    </li>
                    <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                    <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                    <li class="active"><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                    <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                    <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div>
                    <h1>Client Information</h1>
                    <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
                        <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
                    </p>
                </div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                        <small><?php echo $_SESSION['role']; ?></small>
                    </div>
                </div>
            </header>

            <div class="card">
                <div class="card-toggle" id="addClientToggle" onclick="toggleAddClientForm()">
                    <h3><i class="fa-solid fa-user-plus"></i> Add Client</h3>
                    <i class="fas fa-chevron-down chevron" id="addClientChevron"></i>
                </div>
                <div class="card-body-inner" id="addClientBody" style="display:none;">
                    <form id="clientForm" action="save_client.php" method="post">
                        <fieldset class="form-section">
                            <legend><i class="fas fa-building"></i> Business Details</legend>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="client_name">Company / Trade Name *</label>
                                    <input type="text" id="client_name" name="client_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="taxpayer_name">Taxpayer Name *</label>
                                    <input type="text" id="taxpayer_name" name="taxpayer_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="tin">TIN</label>
                                    <input type="text" name="tin" id="tin" class="form-control" placeholder="e.g. 123-456-789-0000">
                                </div>
                                <div class="vat-group">
                                    <label>Tax Type *</label>
                                    <div class="vatlabels">
                                        <label><input type="radio" name="tax_type" value="VAT" required> VAT</label>
                                        <label><input type="radio" name="tax_type" value="NONVAT"> NONVAT</label>
                                        <label><input type="radio" name="tax_type" value="VAT-EXEMPT"> VAT-EXEMPT</label>
                                        <label><input type="radio" name="tax_type" value="NON-VAT EXEMPT"> NON-VAT EXEMPT</label>
                                        <label><input type="radio" name="tax_type" value="EXEMPT"> EXEMPT</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="rdo_code">BIR RDO Code</label>
                                    <input list="rdo_list" id="rdo_code" name="rdo_code" placeholder="Enter or select RDO code">
                                    <datalist id="rdo_list">
                                        <option value="001 - Laoag City, Ilocos Norte">
                                        <option value="002 - Vigan, Ilocos Sur">
                                        <option value="003 - San Fernando, La Union">
                                        <option value="004 - Calasiao, West Pangasinan">
                                        <option value="005 - Alaminos, Pangasinan">
                                        <option value="006 - Urdaneta, Pangasinan">
                                        <option value="007 - Bangued, Abra">
                                        <option value="008 - Baguio City">
                                        <option value="009 - La Trinidad, Benguet">
                                        <option value="010 - Bontoc, Mt. Province">
                                        <option value="011 - Tabuk City, Kalinga">
                                        <option value="012 - Lagawe, Ifugao">
                                        <option value="013 - Tuguegarao, Cagayan">
                                        <option value="014 - Bayombong, Nueva Vizcaya">
                                        <option value="015 - Naguilian, Isabela">
                                        <option value="016 - Cabarroguis, Quirino">
                                        <option value="17A - Tarlac City, Tarlac">
                                        <option value="17B - Paniqui, Tarlac">
                                        <option value="018 - Olongapo City">
                                        <option value="019 - Subic Bay Freeport Zone">
                                        <option value="020 - Balanga, Bataan">
                                        <option value="21A - North Pampanga">
                                        <option value="21B - South Pampanga">
                                        <option value="21C - Clark Freeport Zone">
                                        <option value="022 - Baler, Aurora">
                                        <option value="23A - North Nueva Ecija">
                                        <option value="23B - South Nueva Ecija">
                                        <option value="024 - Valenzuela City">
                                        <option value="25A - Plaridel, Bulacan (now RDO West Bulacan)">
                                        <option value="25B - Sta. Maria, Bulacan (now RDO East Bulacan)">
                                        <option value="026 - Malabon-Navotas">
                                        <option value="027 - Caloocan City">
                                        <option value="028 - Novaliches">
                                        <option value="029 - Tondo – San Nicolas">
                                        <option value="030 - Binondo">
                                        <option value="031 - Sta. Cruz">
                                        <option value="032 - Quiapo-Sampaloc-San Miguel-Sta. Mesa">
                                        <option value="033 - Intramuros-Ermita-Malate">
                                        <option value="034 - Paco-Pandacan-Sta. Ana-San Andres">
                                        <option value="035 - Romblon">
                                        <option value="036 - Puerto Princesa">
                                        <option value="037 - San Jose, Occidental Mindoro">
                                        <option value="038 - North Quezon City">
                                        <option value="039 - South Quezon City">
                                        <option value="040 - Cubao">
                                        <option value="041 - Mandaluyong City">
                                        <option value="042 - San Juan">
                                        <option value="043 - Pasig">
                                        <option value="044 - Taguig-Pateros">
                                        <option value="045 - Marikina">
                                        <option value="046 - Cainta-Taytay">
                                        <option value="047 - East Makati">
                                        <option value="048 - West Makati">
                                        <option value="049 - North Makati">
                                        <option value="050 - South Makati">
                                        <option value="051 - Pasay City">
                                        <option value="052 - Parañaque">
                                        <option value="53A - Las Piñas City">
                                        <option value="53B - Muntinlupa City">
                                        <option value="54A - Trece Martirez City, East Cavite">
                                        <option value="54B - Kawit, West Cavite">
                                        <option value="055 - San Pablo City">
                                        <option value="056 - Calamba, Laguna">
                                        <option value="057 - Biñan, Laguna">
                                        <option value="058 - Batangas City">
                                        <option value="059 - Lipa City">
                                        <option value="060 - Lucena City">
                                        <option value="061 - Gumaca, Quezon">
                                        <option value="062 - Boac, Marinduque">
                                        <option value="063 - Calapan, Oriental Mindoro">
                                        <option value="064 - Talisay, Camarines Norte">
                                        <option value="065 - Naga City">
                                        <option value="066 - Iriga City">
                                        <option value="067 - Legazpi City, Albay">
                                        <option value="068 - Sorsogon, Sorsogon">
                                        <option value="069 - Virac, Catanduanes">
                                        <option value="070 - Masbate, Masbate">
                                        <option value="071 - Kalibo, Aklan">
                                        <option value="072 - Roxas City">
                                        <option value="073 - San Jose, Antique">
                                        <option value="074 - Iloilo City">
                                        <option value="075 - Zarraga, Iloilo City">
                                        <option value="076 - Victorias City, Negros Occidental">
                                        <option value="077 - Bacolod City">
                                        <option value="078 - Binalbagan, Negros Occidental">
                                        <option value="079 - Dumaguete City">
                                        <option value="080 - Mandaue City">
                                        <option value="081 - Cebu City North">
                                        <option value="082 - Cebu City South">
                                        <option value="083 - Talisay City, Cebu">
                                        <option value="084 - Tagbilaran City">
                                        <option value="085 - Catarman, Northern Samar">
                                        <option value="086 - Borongan, Eastern Samar">
                                        <option value="087 - Calbayog City, Samar">
                                        <option value="088 - Tacloban City">
                                        <option value="089 - Ormoc City">
                                        <option value="090 - Maasin, Southern Leyte">
                                        <option value="091 - Dipolog City">
                                        <option value="092 - Pagadian City, Zamboanga del Sur">
                                        <option value="093A - Zamboanga City, Zamboanga del Sur">
                                        <option value="093B - Ipil, Zamboanga Sibugay">
                                        <option value="094 - Isabela, Basilan">
                                        <option value="095 - Jolo, Sulu">
                                        <option value="096 - Bongao, Tawi-Tawi">
                                        <option value="097 - Gingoog City">
                                        <option value="098 - Cagayan de Oro City">
                                        <option value="099 - Malaybalay City, Bukidnon">
                                        <option value="100 - Ozamis City">
                                        <option value="101 - Iligan City">
                                        <option value="102 - Marawi City">
                                        <option value="103 - Butuan City">
                                        <option value="104 - Bayugan City, Agusan del Sur">
                                        <option value="105 - Surigao City">
                                        <option value="106 - Tandag, Surigao del Sur">
                                        <option value="107 - Cotabato City">
                                        <option value="108 - Kidapawan, North Cotabato">
                                        <option value="109 - Tacurong, Sultan Kudarat">
                                        <option value="110 - General Santos City">
                                        <option value="111 - Koronadal City, South Cotabato">
                                        <option value="112 - Tagum, Davao del Norte">
                                        <option value="113A - West Davao City">
                                        <option value="113B - East Davao City">
                                        <option value="114 - Mati, Davao Oriental">
                                        <option value="115 - Digos, Davao del Sur">
                                    </datalist>
                                </div>
                                <input type="hidden" name="client_address" id="client_address" oninput="suggestRDO()" required>
                            </div>
                        </fieldset>

                        <fieldset class="form-section">
                            <legend><i class="fas fa-map-marker-alt"></i> Address</legend>
                            <div class="form-grid">
                                <div class="section-hint">Feeds the address used when creating job orders for this client.</div>

                                <div class="form-group">
                                    <label for="province">Province *</label>
                                    <select id="province" name="province" required>
                                        <option value="">Select Province</option>
                                        <?php foreach ($provinces as $prov): ?>
                                            <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="city">City / Municipality *</label>
                                    <select id="city" name="city" required>
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label for="barangay">Barangay</label>
                                    <span style="
                                position: absolute;
                                top: 60%;
                                left: 12px;
                                transform: translateY(-20%);
                                color: var(--gray);
                                pointer-events: none;
                                font-size: 13px;
                                ">Brgy.
                                    </span>
                                    <input type="text"
                                        id="barangay"
                                        name="barangay"
                                        class="form-control"
                                        placeholder="e.g. San Isidro"
                                        style="padding-left: 52px;" pattern="[^,]*" title="Commas are not allowed" />
                                </div>
                                <div class="form-group">
                                    <label for="street">Subdivision / Street</label>
                                    <input type="text" id="street" name="street" placeholder="e.g. Rizal St." pattern="[^,]*" title="Commas are not allowed">
                                </div>
                                <div class="form-group">
                                    <label for="building_no">Building / House No.</label>
                                    <input type="text" id="building_no" name="building_no" placeholder="e.g. Bldg 4, Lot 6" pattern="[^,]*" title="Commas are not allowed">
                                </div>
                                <div class="form-group">
                                    <label for="floor_no">Floor / Room No.</label>
                                    <input type="text" id="floor_no" name="floor_no" placeholder="e.g. 2F, Room 201" pattern="[^,]*" title="Commas are not allowed">
                                </div>
                                <div class="form-group">
                                    <label for="zip_code">ZIP Code</label>
                                    <input type="text" id="zip_code" name="zip_code" placeholder="e.g. 3020" pattern="[^,]*" title="Commas are not allowed">
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form-section">
                            <legend><i class="fas fa-address-book"></i> Contact</legend>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="contact_person">Contact Person *</label>
                                    <input type="text" id="contact_person" name="contact_person" required>
                                </div>
                                <div class="form-group">
                                    <label for="contact_number">Contact Number *</label>
                                    <input type="text" id="contact_number" name="contact_number" required>
                                </div>
                                <div class="form-group">
                                    <label for="client_by">Client By *</label>
                                    <input type="text" name="client_by" id="client_by" class="form-control" required>
                                </div>
                            </div>
                        </fieldset>
                        <button type="submit" class="btn"><i class="fas fa-save"></i>Save Client</button>
                    </form>
                </div>
            </div>

            <div class="client-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Saved Clients</h3>
                </div>
                <div class="card-body">
                    <div class="search">
                        <input type="text" id="clientSearchInput" placeholder="Search clients..." class="form-control" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="letter-nav">
                        <a class="letter-btn<?= $letter === '' && $search === '' ? ' active' : '' ?>" href="?letter=&page=1">All</a>
                        <?php foreach (range('A', 'Z') as $L): ?>
                            <?php $hasClients = in_array($L, $availableLetters, true); ?>
                            <?php if ($hasClients): ?>
                                <a class="letter-btn<?= $letter === $L ? ' active' : '' ?>" href="?letter=<?= $L ?>&page=1"><?= $L ?></a>
                            <?php else: ?>
                                <span class="letter-btn disabled"><?= $L ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <ul class="client-list">
                        <?php foreach ($clients as $client): ?>
                            <li class="client-item" data-id="<?= (int) $client['id'] ?>">
                                <div class="client-info">
                                    <span class="client-name"><?= htmlspecialchars($client['client_name']) ?></span>
                                </div>
                                <a href="job_orders.php?client_id=<?= $client['id'] ?>" class="btn cjo" onclick="event.stopPropagation()">
                                    <i class="fas fa-file-alt eyo"></i><span class="cjo-label">Create Job Order</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($totalPages > 1): ?>
                        <?php
                            $baseParams = [];
                            if ($search !== '') $baseParams['search_client'] = $search;
                            if ($letter !== '') $baseParams['letter'] = $letter;
                        ?>
                        <div class="pagination">
                            <a class="btn btn-outline<?= $page <= 1 ? ' disabled' : '' ?>"
                               href="?<?= http_build_query($baseParams + ['page' => $page - 1]) ?>"
                               <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                            <span class="pagination-status">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $totalClients ?> client<?= $totalClients === 1 ? '' : 's' ?></span>
                            <a class="btn btn-outline<?= $page >= $totalPages ? ' disabled' : '' ?>"
                               href="?<?= http_build_query($baseParams + ['page' => $page + 1]) ?>"
                               <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="empty-state" style="display: <?= count($clients) === 0 ? 'block' : 'none' ?>;">
                    <i class="fas fa-inbox"></i>
                    <p><?= $search !== '' ? 'No clients match "' . htmlspecialchars($search) . '"' : ($letter !== '' ? 'No clients starting with "' . $letter . '"' : 'No saved clients found') ?></p>
                </div>
            </div>

            <div id="clientModal" class="modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-container animate__animated animate__fadeInUp">
                    <div class="modal-header">
                        <h3>
                            <i class="fas fa-building"></i>
                            <span id="modalClientName"></span>
                        </h3>
                        <button class="close-btn"><i class="fas fa-times"></i></button>
                    </div>

                    <div class="modal-body">
                        <div class="client-details-grid">
                            <!-- Column 1 -->
                            <div class="detail-group">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-id-card"></i> Taxpayer</span>
                                    <span id="modalTaxpayer" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-hashtag"></i> TIN</span>
                                    <span id="modalTIN" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-map-marker-alt"></i> RDO Code</span>
                                    <span id="modalRDO" class="detail-value"></span>
                                </div>
                            </div>

                            <!-- Column 2 -->
                            <div class="detail-group">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-phone"></i> Contact</span>
                                    <span id="modalContact" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-plus"></i> Client By</span>
                                    <span id="modalClientBy" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-map-marked-alt"></i> Address</span>
                                    <span id="modalAddress" class="detail-value"></span>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="stats-section">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Total Job Orders</span>
                                    <span id="modalTotalOrders" class="stat-value">...</span>
                                </div>
                            </div>
                        </div>

                        <div class="recent-orders">
                            <h4><i class="fas fa-clock"></i> Recent Orders</h4>
                            <ul id="modalRecentOrders" class="order-list">
                                <!-- Dynamically populated -->
                            </ul>
                        </div>
                    </div>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="modal-footer">
                            <button id="editClientBtn" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit Client
                            </button>
                            <button id="deleteClientBtn" class="btn btn-delete">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <script src="../assets/js/pages/clients.js"></script>
        </div>
    </body>
    
</html>