<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Stock Report</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <div class="header-container">
      <div class="company-name">Active Media Designs & Printing</div>
      <h1 class="report-title">Daily Stock Report</h1>
      <div class="report-subtitle">Real-time inventory overview</div>
    </div>
    <div class="logout">
      <a href="logout.php">Logout</a>
    </div>
    <div id="report-container"></div>
    
    <div class="form-container">
      <button class="btn" id="open-modal-btn" >+ Add New Product</button>
      <!-- <button id="download-pdf" class="btn" style="margin-left: 10px;">Download Report as PDF</button> -->
      <div class="modal-overlay" id="modal-overlay" style="animation: fadeIn 0.3s ease;">
        <div class="edit-modal-content">
          <h3>Add New Product</h3>
          <form method="POST" action="insert_product.php">
            <div class="form-grid">
              <div class="form-group">
                <label for="type">Product Type</label>
                <input type="text" id="type" name="type" class="form-control" placeholder="e.g., Carbonless, Ordinary Paper" required>
              </div>
              <div class="form-group">
                <label for="section">Product Size</label>
                <input type="text" id="section" name="section" class="form-control" placeholder="e.g., SHORT, LONG" required>
              </div>
              <div class="form-group">
                <label for="product">Product Name</label>
                <input type="text" id="product" name="product" class="form-control" placeholder="Product name" required>
              </div>
              <div class="form-group">
                <label for="unit_price">Unit Price (Pesos)</label>
                <input type="number" step="0.01" id="unit_price" name="unit_price" class="form-control" placeholder="0.00">
              </div>
              <div class="form-group">
                <label for="initial_stock">Initial Stock (optional)</label>
                <input type="number" step="0.01" id="initial_stock" name="initial_stock" class="form-control" placeholder="Leave blank if none">
              </div>
              <div class="form-actions">
                <button type="submit" class="btn save-btn" style="transition: all 0.3s ease">Add Product</button>
                <button type="button" class="btn cancel-btn" id="close-modal-btn" style="background: var(--gray); transition: all 0.3s ease">Cancel</button>
              </div>
            </div>
          </form>
        </div>
      </div>     
    </div>
    <div id="addProductModal" class="edit-modal" style="display:none; animation: fadeIn 0.3s ease;">
      <div class="edit-modal-content">
        <h3>Add Product</h3>
        <form id="addProductForm">
          <div class="form-group">
            <label for="newProduct">Product Name</label>
            <input type="text" id="newProduct" name="product" placeholder="Product name" required>
          </div>
          <div class="form-group">
            <label for="newPrice">Unit Price (Pesos)</label>
            <input type="number" id="newPrice" name="unit_price" step="0.01" min="0" placeholder="0.00">
          </div>
          <div class="form-group">
            <label for="initialStock">Initial Stock (optional)</label>
            <input type="number" id="initialStock" name="initial_stock" step="0.01" min="0" placeholder="Leave blank if none">
          </div>
          <div class="form-actions">
            <button type="submit" class="btn save-btn">Add Product</button>
            <button type="button" class="btn cancel-btn" style="background: var(--gray)";>Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="script.js"></script>
  <script>
    const openModalBtn = document.getElementById('open-modal-btn');
    const closeModalBtn = document.getElementById('close-modal-btn');
    const modalOverlay = document.getElementById('modal-overlay');

    openModalBtn.addEventListener('click', () => {
      modalOverlay.style.display = 'flex';
    });

    closeModalBtn.addEventListener('click', () => {
      modalOverlay.style.display = 'none';
    });

    // Close modal if user clicks outside the modal window
    window.addEventListener('click', (e) => {
      if (e.target === modalOverlay) {
        modalOverlay.style.display = 'none';
      }
    });
  </script>
</body>
</html>