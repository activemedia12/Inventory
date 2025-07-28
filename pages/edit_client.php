<?php
session_start();
require_once '../config/db.php';

if (!isset($_GET['id'])) {
    echo "No client selected.";
    exit;
}

$client_id = intval($_GET['id']);
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $mysqli->prepare("UPDATE clients SET 
        client_name = ?, 
        taxpayer_name = ?, 
        tin = ?, 
        tax_type = ?, 
        rdo_code = ?, 
        client_address = ?, 
        contact_person = ?, 
        contact_number = ?, 
        client_by = ?
        WHERE id = ?");

    $stmt->bind_param(
        "sssssssssi",
        $_POST['client_name'],
        $_POST['taxpayer_name'],
        $_POST['tin'],
        $_POST['tax_type'],
        $_POST['rdo_code'],
        $_POST['client_address'],
        $_POST['contact_person'],
        $_POST['contact_number'],
        $_POST['client_by'],
        $client_id
    );

    if ($stmt->execute()) {
        $message = "Client updated successfully. Redirecting to client list...";
        echo "<meta http-equiv='refresh' content='3;url=clients.php'>";
    } else {
        $message = "Failed to update client.";
    }
}

// Fetch current client data
$stmt = $mysqli->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

if (!$client) {
    echo "Client not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Client</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #eef2ff;
            --success: #28a745;
            --danger: #dc3545;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --lighter-gray: #f8f9fa;
            --border-radius: 0.375rem;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--lighter-gray);
            color: var(--dark);
            line-height: 1.6;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .header {
            background-color: var(--primary);
            color: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .header h2 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }

        .alert {
            padding: 1rem;
            margin: 1rem;
            border-radius: var(--border-radius);
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success);
            border-left: 4px solid var(--success);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert i {
            font-size: 1.25rem;
        }

        .form-container {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 0.9375rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--light-gray);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: #3a56d5;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--gray);
            text-decoration: none;
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
            color: var(--dark);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>
                <i class="fas fa-user-edit"></i>
                Edit Client Information
            </h2>
        </div>

        <?php if ($message): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="client_name">Client Name *</label>
                        <input type="text" id="client_name" name="client_name" class="form-control" required
                            value="<?= htmlspecialchars($client['client_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="taxpayer_name">Taxpayer Name *</label>
                        <input type="text" id="taxpayer_name" name="taxpayer_name" class="form-control" required
                            value="<?= htmlspecialchars($client['taxpayer_name']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="tin">TIN</label>
                        <input type="text" id="tin" name="tin" class="form-control"
                            value="<?= htmlspecialchars($client['tin']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="tax_type">Tax Type *</label>
                        <select id="tax_type" name="tax_type" class="form-control" required>
                            <?php
                            $types = ['VAT', 'NONVAT', 'VAT-EXEMPT', 'NON-VAT EXEMPT', 'EXEMPT'];
                            foreach ($types as $type):
                            ?>
                                <option value="<?= $type ?>" <?= $client['tax_type'] === $type ? 'selected' : '' ?>>
                                    <?= $type ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="rdo_code">RDO Code</label>
                        <input type="text" id="rdo_code" name="rdo_code" class="form-control"
                            value="<?= htmlspecialchars($client['rdo_code']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="client_address">Client Address</label>
                        <textarea id="client_address" name="client_address" class="form-control"><?= htmlspecialchars($client['client_address']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="contact_person">Contact Person *</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" required
                            value="<?= htmlspecialchars($client['contact_person']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number *</label>
                        <input type="text" id="contact_number" name="contact_number" class="form-control" required
                            value="<?= htmlspecialchars($client['contact_number']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="client_by">Client By *</label>
                        <input type="text" id="client_by" name="client_by" class="form-control" required
                            value="<?= htmlspecialchars($client['client_by']) ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <a href="clients.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Format phone number input
        document.getElementById('contact_number')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d+]/g, '');
        });

        // Focus first field with error
        document.querySelector('form').addEventListener('submit', function(e) {
            const invalidFields = this.querySelectorAll(':invalid');
            if (invalidFields.length > 0) {
                e.preventDefault();
                invalidFields[0].focus();
                invalidFields[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    </script>
</body>

</html>