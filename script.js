// Stock Level Calculation
function getStockLevel(balance) {
  if (balance <= 0) return { label: "No Stock", color: "#ef233c", bg: "#f8d7da", text: "#721c24" };
  if (balance <= 25) return { label: "Low", color: "#dc3545", bg: "#f8d7da", text: "#721c24" };
  if (balance <= 50) return { label: "Almost Low", color: "#ffc107", bg: "#fff3cd", text: "#856404" };
  if (balance <= 80) return { label: "High", color: "#28a745", bg: "#d4edda", text: "#155724" };
  return { label: "Very High", color: "#20c997", bg: "#d1f2eb", text: "#0c5460" };
}

// Number Formatting
function formatNumber(value) {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2
  }).format(value);
}

// Animation Functions
function fadeIn(element, duration = 300) {
  element.style.opacity = 0;
  element.style.display = 'block';
  let start = null;
  
  function step(timestamp) {
    if (!start) start = timestamp;
    const progress = timestamp - start;
    const opacity = Math.min(progress / duration, 1);
    element.style.opacity = opacity;
    if (progress < duration) {
      window.requestAnimationFrame(step);
    }
  }
  
  window.requestAnimationFrame(step);
}

function fadeOut(element, duration = 300) {
  let start = null;
  
  function step(timestamp) {
    if (!start) start = timestamp;
    const progress = timestamp - start;
    const opacity = Math.max(1 - progress / duration, 0);
    element.style.opacity = opacity;
    if (progress < duration) {
      window.requestAnimationFrame(step);
    } else {
      element.style.display = 'none';
    }
  }
  
  window.requestAnimationFrame(step);
}

// Main Rendering Function
function renderStockData(data) {
  const container = document.getElementById('report-container');
  container.innerHTML = '';
  
  // Loading animation
  container.style.opacity = 0;
  setTimeout(() => {
    container.style.transition = 'opacity 0.5s ease';
    container.style.opacity = 1;
  }, 50);

  // Handle empty data
  if (!data || Object.keys(data).length === 0) {
    container.innerHTML = '<div class="error">No data available</div>';
    return;
  }

  // Process each product type
  Object.entries(data).forEach(([productType, productGroups]) => {
    const typeSection = createTypeSection(productType, productGroups);
    container.appendChild(typeSection);
  });

  // Setup all delete button handlers
  setupDeleteButtons();
}

// Helper function to create product type sections
function createTypeSection(productType, productGroups) {
  const typeSection = document.createElement('div');
  typeSection.className = 'product-type';
  typeSection.style.transition = 'all 0.3s ease';

  const typeHeader = createTypeHeader(productType);
  const groupContainer = createGroupContainer(productType, productGroups);

  typeSection.appendChild(typeHeader);
  typeSection.appendChild(groupContainer);
  
  return typeSection;
}

function createTypeHeader(productType) {
  const typeHeader = document.createElement('h2');
  typeHeader.innerHTML = `
    <span class="toggle-icon">
      <svg class="chevron" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
        <path d="M6 9l6 6 6-6"/>
      </svg>
    </span>
    ${productType}
    <button class="delete-btn delete-type-btn" data-type="${productType}" title="Delete this product type">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
      </svg>
    </button>
  `;
  typeHeader.classList.add('type-header');
  return typeHeader;
}

function createGroupContainer(productType, productGroups) {
  const groupContainer = document.createElement('div');
  groupContainer.className = 'group-container';
  groupContainer.style.transition = 'all 0.3s ease';

  // Handle collapse state
  const isCollapsed = localStorage.getItem(`collapse-${productType}`) === 'collapsed';
  groupContainer.style.display = isCollapsed ? 'none' : 'block';
  groupContainer.style.opacity = isCollapsed ? 0 : 1;
  
  if (isCollapsed) {
    groupContainer.previousElementSibling.querySelector('.chevron').style.transform = 'rotate(-90deg)';
  }

  // Process groups (convert array to object if needed)
  const groups = Array.isArray(productGroups) 
    ? { 'Products': productGroups } 
    : productGroups;

  // Create group sections
  Object.entries(groups).forEach(([group, items]) => {
    const groupSection = createGroupSection(productType, group, items);
    groupContainer.appendChild(groupSection);
  });

  return groupContainer;
}

function createGroupSection(productType, group, items) {
  const groupSection = document.createElement('div');
  groupSection.className = 'product-group';
  groupSection.style.transition = 'all 0.3s ease';

  const groupHeader = createGroupHeader(productType, group);
  const groupBody = createGroupBody(items);

  groupSection.appendChild(groupHeader);
  groupSection.appendChild(groupBody);
  
  return groupSection;
}

function createGroupHeader(productType, group) {
  const groupHeader = document.createElement('div');
  groupHeader.className = 'group-header';
  groupHeader.innerHTML = `
    <strong>${group}</strong>
    <button class="delete-btn delete-group-btn" data-type="${productType}" data-group="${group}" title="Delete this group">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
      </svg>
    </button>
  `;
  groupHeader.style.transition = 'all 0.2s ease';
  return groupHeader;
}

function createGroupBody(items) {
  const groupBody = document.createElement('div');
  groupBody.className = 'group-body';
  groupBody.style.transition = 'all 0.3s ease';

  const table = document.createElement('table');
  table.style.transition = 'all 0.3s ease';
  
  // Create table header
  const thead = document.createElement('thead');
  thead.innerHTML = `
    <tr>
      <th>Product</th>
      <th style="text-align: end;">Stock Balance</th>
      <th>Stock Level</th>
      <th style="text-align: end;">Unit Price</th>
      <th style="text-align: end;">Amount</th>
      <th>Actions</th>
    </tr>
  `;

  // Create table body with items
  const tbody = document.createElement('tbody');
  items.forEach((item, index) => {
    tbody.appendChild(createProductRow(item, index));
  });

  table.appendChild(thead);
  table.appendChild(tbody);
  groupBody.appendChild(table);
  
  return groupBody;
}

function createProductRow(item, index) {
  const level = getStockLevel(item.stock_balance);
  const row = document.createElement('tr');
  
  row.classList.add('clickable-row');
  row.dataset.href = `usage_log.php?product_id=${item.id}`;
  row.style.transition = 'all 0.2s ease';
  row.style.animation = `fadeInRow 0.3s ease ${index * 0.05}s forwards`;
  row.style.opacity = 0;

  row.innerHTML = `
    <td><a href="usage_log.php?product_id=${item.id}" class="product-link">${item.product}</a></td>
    <td style="text-align: end;">${formatNumber(item.stock_balance)}</td>
    <td>
      <span class="stock-level-badge" style="background-color:${level.bg}; color:${level.text}; border-left: 4px solid ${level.color}">
        ${level.label}
      </span>
    </td>
    <td style="text-align: end;">${formatNumber(item.unit_price)}</td>
    <td style="text-align: end;">${formatNumber(item.amount)}</td>
    <td>
      <button class="delete-btn delete-product-btn" data-id="${item.id}" title="Delete this product">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        </svg>
      </button>
    </td>
  `;

  return row;
}

// Delete Functionality
function setupDeleteButtons() {
  // Product delete
  document.querySelectorAll('.delete-product-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const productId = this.dataset.id;
      const productName = this.closest('tr').querySelector('.product-link').textContent;
      showDeleteConfirmation({
        scope: 'product',
        value: productId,
        message: `Delete product "${productName}"?`,
        type: 'product',
        group: this.closest('.product-group')?.querySelector('.group-header strong')?.textContent
      });
    });
  });

  // Group delete
  document.querySelectorAll('.delete-group-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const productType = this.dataset.type;
      const group = this.dataset.group;
      showDeleteConfirmation({
        scope: 'group',
        value: group,
        message: `Delete the entire "${group}" group from "${productType}"?`,
        type: productType
      });
    });
  });

  // Type delete
  document.querySelectorAll('.delete-type-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const productType = this.dataset.type;
      showDeleteConfirmation({
        scope: 'type', 
        value: productType,
        message: `Delete all "${productType}" products?`
      });
    });
  });
}

function showDeleteConfirmation({scope, value, message, type, group}) {
  const modal = document.createElement('div');
  modal.className = 'delete-confirmation-modal';
  modal.innerHTML = `
    <div class="delete-confirmation-content">
      <p>${message}</p>
      <p class="warning-text">This action cannot be undone!</p>
      <div class="confirmation-buttons">
        <button class="btn confirm-delete-btn">Delete</button>
        <button class="btn cancel-delete-btn">Cancel</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

  modal.querySelector('.confirm-delete-btn').addEventListener('click', () => {
    deleteItem(scope, value);
    modal.remove();
  });

  modal.querySelector('.cancel-delete-btn').addEventListener('click', () => {
    modal.remove();
  });

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

function deleteItem(scope, value) {
  showLoading(true);
  
  fetch('delete_item.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `scope=${scope}&value=${encodeURIComponent(value)}`
  })
  .then(handleResponse)
  .then(data => {
    if (data.status === 'success') {
      showToast('Deleted successfully');
      refreshData();
    } else {
      throw new Error(data.message || 'Unknown error');
    }
  })
  .catch(handleError)
  .finally(() => showLoading(false));
}

// Utility Functions
function showLoading(show) {
  const loader = document.getElementById('loading-overlay') || createLoader();
  loader.style.display = show ? 'flex' : 'none';
}

function createLoader() {
  const loader = document.createElement('div');
  loader.id = 'loading-overlay';
  loader.innerHTML = `
    <div class="loader-content">Processing...</div>
  `;
  document.body.appendChild(loader);
  return loader;
}

function showToast(message) {
  const toast = document.createElement('div');
  toast.className = 'toast-message';
  toast.textContent = message;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.classList.add('fade-out');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function handleResponse(response) {
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  return response.json();
}

function handleError(error) {
  console.error('Error:', error);
  showToast(`Error: ${error.message}`);
}

function refreshData() {
  fetch('get_data.php')
    .then(handleResponse)
    .then(renderStockData)
    .catch(handleError);
}

// Event Listeners
function setupEventListeners() {
  // Toggle product type sections
  document.addEventListener('click', (e) => {
    const typeHeader = e.target.closest('.type-header');
    if (typeHeader && !e.target.closest('.delete-btn')) {
      const groupContainer = typeHeader.nextElementSibling;
      const chevron = typeHeader.querySelector('.chevron');
      const isHidden = groupContainer.style.display === 'none';
      
      chevron.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
      chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
      
      if (isHidden) {
        groupContainer.style.display = 'block';
        fadeIn(groupContainer);
      } else {
        fadeOut(groupContainer);
      }
      
      const productType = typeHeader.textContent.trim();
      const state = isHidden ? 'expanded' : 'collapsed';
      localStorage.setItem(`collapse-${productType}`, state);
    }
  });

  // Row click handling
  document.addEventListener('click', (e) => {
    const row = e.target.closest('.clickable-row');
    if (row && row.dataset.href && !e.target.closest('.delete-btn')) {
      e.preventDefault();
      row.style.transform = 'scale(0.98)';
      row.style.opacity = '0.8';
      setTimeout(() => {
        window.location.href = row.dataset.href;
      }, 200);
    }
  });

  // Scroll position handling
  window.addEventListener('beforeunload', () => {
    localStorage.setItem('scrollY', window.scrollY);
  });

  window.addEventListener('load', () => {
    document.body.style.opacity = 0;
    setTimeout(() => {
      document.body.style.transition = 'opacity 0.5s ease';
      document.body.style.opacity = 1;
      
      const scrollY = localStorage.getItem('scrollY');
      if (scrollY !== null) {
        window.scrollTo({
          top: parseInt(scrollY, 10),
          behavior: 'smooth'
        });
      }
    }, 50);
  });
}

// Initialize Styles
function initializeStyles() {
  const style = document.createElement('style');
  style.textContent = `
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeInRow {
      from { opacity: 0; transform: translateX(-50px); }
      to { opacity: 1; transform: translateX(0); }
    }
    
    .clickable-row {
      transition: all 0.2s ease;
    }
    
    .clickable-row:hover {
      transform: translateX(5px);
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .product-link {
      transition: color 0.2s ease;
    }
    
    .stock-level-badge {
      transition: all 0.2s ease;
    }
    
    .group-header {
      transition: all 0.2s ease;
    }
    
    .group-header:hover {
      transform: translateY(-3px);
      text-shadow: 0px 3px 3px rgba(0,0,0,0.69);
      cursor: default;
    }
    
    .type-header {
      transition: all 0.2s ease;
    }
    
    .type-header:hover {
      transform: translateY(-3px);
      text-shadow: 0px 0px 20px rgba(255, 251, 0, 0.8);
    }
    
    .delete-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 5px;
      border-radius: 4px;
      transition: all 0.2s ease;
      margin-left: 10px;
      opacity: 0.7;
    }
    
    .delete-btn:hover {
      opacity: 1;
      background-color: rgba(0,0,0,0.1);
    }
    
    .delete-btn svg {
      display: block;
    }
    
    .delete-confirmation-modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      animation: fadeIn 0.3s ease;
    }
    
    .delete-confirmation-content {
      background: white;
      padding: 25px;
      border-radius: 10px;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    }
    
    .delete-confirmation-content p {
      margin-bottom: 15px;
      font-size: 1.1rem;
      color: var(--primary);
    }
    
    .warning-text {
      color: var(--danger);
      font-weight: bold;
    }
    
    .confirmation-buttons {
      display: flex;
      gap: 15px;
      justify-content: flex-end;
    }
    
    .confirm-delete-btn {
      background-color: var(--danger) !important;
    }
    
    #loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.7);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 2000;
      color: white;
      font-size: 1.2rem;
    }
    
    .toast-message {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--primary);
      color: white;
      padding: 12px 24px;
      border-radius: 4px;
      z-index: 2000;
      animation: fadeIn 0.3s ease;
    }
    
    .toast-message.fade-out {
      animation: fadeOut 0.3s ease forwards;
    }
    
    @keyframes fadeOut {
      to { opacity: 0; transform: translateX(-50%) translateY(20px); }
    }
  `;
  document.head.appendChild(style);
}

// Initialization
function init() {
  initializeStyles();
  setupEventListeners();
  
  // Load initial data
  fetch('get_data.php')
    .then(handleResponse)
    .then(data => {
      if (!data) throw new Error("Empty response from server");
      setTimeout(() => renderStockData(data), 300);
    })
    .catch(handleError);
}

// Start the application
document.addEventListener('DOMContentLoaded', init);