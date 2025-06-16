function getStockLevel(balance) {
  if (balance <= 0) return { label: "No Stock", color: "#ef233c", bg: "#f8d7da", text: "#721c24" };
  if (balance <= 25) return { label: "Low", color: "#dc3545", bg: "#f8d7da", text: "#721c24" };
  if (balance <= 50) return { label: "Almost Low", color: "#ffc107", bg: "#fff3cd", text: "#856404" };
  if (balance <= 80) return { label: "High", color: "#28a745", bg: "#d4edda", text: "#155724" };
  return { label: "Very High", color: "#20c997", bg: "#d1f2eb", text: "#0c5460" };
}

function formatNumber(value) {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: value % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2
  }).format(value);
}

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

function renderStockData(data) {
  console.log("Rendering data:", data); 
  const container = document.getElementById('report-container');
  container.innerHTML = '';
  
  container.style.opacity = 0;
  setTimeout(() => {
    container.style.transition = 'opacity 0.5s ease';
    container.style.opacity = 1;
  }, 50);

  if (!data || Object.keys(data).length === 0) {
    container.innerHTML = '<div class="error">No data available</div>';
    return;
  }

  for (const productType in data) {
    const typeSection = document.createElement('div');
    typeSection.className = 'product-type';
    typeSection.style.transition = 'all 0.3s ease';

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
    typeSection.appendChild(typeHeader);

    const groupContainer = document.createElement('div');
    groupContainer.className = 'group-container';
    groupContainer.style.transition = 'all 0.3s ease';

    const collapseState = localStorage.getItem(`collapse-${productType}`);
    const isCollapsed = collapseState === 'collapsed';
    groupContainer.style.display = isCollapsed ? 'none' : 'block';
    groupContainer.style.opacity = isCollapsed ? 0 : 1;
    if (isCollapsed) {
      typeHeader.querySelector('.chevron').style.transform = 'rotate(-90deg)';
    }

    const productGroups = Array.isArray(data[productType])
      ? { 'Products': data[productType] }
      : data[productType];

    for (const group in productGroups) {
      const groupSection = document.createElement('div');
      groupSection.className = 'product-group';
      groupSection.style.transition = 'all 0.3s ease';

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
      const addButton = document.createElement('button');
      addButton.className = 'add-product-btn';
      addButton.dataset.section = group;
      addButton.dataset.type = productType;
      addButton.textContent = '+ Add Product';
      groupHeader.appendChild(addButton);


      const groupBody = document.createElement('div');
      groupBody.classList.add('group-body');
      groupBody.style.transition = 'all 0.3s ease';

      const table = document.createElement('table');
      table.style.transition = 'all 0.3s ease';
      const thead = document.createElement('thead');
      const tbody = document.createElement('tbody');

      thead.innerHTML = `
        <tr>
          <th>Product</th>
          <th style="text-align: center;">Stock Balance</th>
          <th>Stock Level</th>
          <th style="text-align: center;">Unit Price</th>
          <th style="text-align: center;">Amount</th>
          <th style="text-align: center;">Actions</th>
        </tr>
      `;

      productGroups[group].forEach((item, index) => {
        const level = getStockLevel(item.stock_balance);
        const row = document.createElement('tr');
        row.classList.add('clickable-row');
        row.dataset.href = `usage_log.php?product_id=${item.id}`;
        row.style.transition = 'all 0.2s ease';
        row.style.animation = `fadeInRow 0.3s ease ${index * 0.05}s forwards`;
        row.style.opacity = 0;

        row.innerHTML = `
          <td><a href="usage_log.php?product_id=${item.id}" class="product-link">${item.product}</a></td>
          <td style="text-align: center;">${formatNumber(item.stock_balance)}</td>
          <td>
            <span class="stock-level-badge" style="background-color:${level.bg}; color:${level.text}; border-left: 4px solid ${level.color}">
              ${level.label}
            </span>
          </td>
          <td style="text-align: center;">${formatNumber(item.unit_price)}</td>
          <td style="text-align: center;">${formatNumber(item.amount)}</td>
          <td>
            <button class="edit-btn" 
                data-id="${item.id}" 
                data-product="${item.product}" 
                data-section="${item.section}" 
                data-type="${productType}" 
                data-price="${item.unit_price}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 20h9" />
                  <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                </svg>
            </button>
            <button class="delete-btn delete-product-btn" data-id="${item.id}" title="Delete this product">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
              </svg>
            </button>
          </td>
        `;

        tbody.appendChild(row);
      });

      table.appendChild(thead);
      table.appendChild(tbody);
      groupBody.appendChild(table);

      groupSection.appendChild(groupHeader);
      groupSection.appendChild(groupBody);
      groupContainer.appendChild(groupSection);
    }

    typeHeader.addEventListener('click', (e) => {
      if (e.target.closest('.delete-btn')) return; 
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
      
      const state = isHidden ? 'expanded' : 'collapsed';
      localStorage.setItem(`collapse-${productType}`, state);
    });

    typeSection.appendChild(groupContainer);
    container.appendChild(typeSection);
  }

  setupDeleteButtons();
  setupEditButtons();
  setupAddProductButtons();
}

function setupEditButtons() {
  document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', (e) => {
      e.stopPropagation();
      
      const id = button.dataset.id;
      const currentProduct = button.dataset.product;
      const currentSection = button.dataset.section;
      const currentType = button.dataset.type;
      const currentPrice = button.dataset.price;

      // Create modal
      const modal = document.createElement('div');
      modal.className = 'edit-modal';
      modal.innerHTML = `
        <div class="edit-modal-content">
          <h3 style="text-align:center;">Edit Product</h3>
          <form id="editForm">
            <div class="form-group">
              <label for="editType">Product Type</label>
              <input type="text" id="editType" value="${currentType}" required>
            </div>
            <div class="form-group">
              <label for="editSection">Product Size</label>
              <input type="text" id="editSection" value="${currentSection}" required>
            </div>
            <div class="form-group">
              <label for="editProduct">Product Name</label>
              <input type="text" id="editProduct" value="${currentProduct}" required>
            </div>
            <div class="form-group">
              <label for="editPrice">Unit Price (Pesos)</label>
              <input type="number" id="editPrice" value="${parseFloat(currentPrice).toFixed(2)}" step="0.01" min="0">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn save-btn" style='background: var(--primary); transition: all 0.3s ease'>Save Changes</button>
              <button type="button" class="btn cancel-btn" style='background: var(--gray); transition: all 0.3s ease'>Cancel</button>
            </div>
          </form>
        </div>
      `;
      document.body.appendChild(modal);

      // Form submission
      const form = modal.querySelector('#editForm');
      modal.querySelector('.cancel-btn').addEventListener('click', () => {
        modal.remove();
      });
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const newProduct = modal.querySelector('#editProduct').value.trim();
        const newSection = modal.querySelector('#editSection').value.trim();
        const newType = modal.querySelector('#editType').value.trim();
        const newPrice = parseFloat(modal.querySelector('#editPrice').value);

        if (!newProduct || !newSection || !newType || isNaN(newPrice) || newPrice < 0) {
          alert('Please fill all fields with valid values');
          return;
        }

        const payload = new URLSearchParams({
          id,
          product: newProduct,
          section: newSection,
          type: newType,
          unit_price: newPrice.toFixed(2)
        });

        fetch('update_product.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: payload.toString()
        })
        .then(async res => {
          const text = await res.text();
          try {
            return JSON.parse(text);
          } catch (e) {
            throw new Error(text);
          }
        })
        .then(data => {
          if (data.status === 'success') {
            modal.remove();
            fetch('get_data.php')
              .then(res => res.json())
              .then(renderStockData)
              .catch(err => {
                console.error('Refresh error:', err);
                alert('Updated successfully but failed to refresh data. Please reload the page.');
              });
          } else {
            throw new Error(data.message || 'Update failed');
          }
        })
        .catch(err => {
          console.error("Update error:", err);
          // Clean up HTML tags from error message
          const cleanError = err.message.replace(/<[^>]*>?/gm, '').trim();
          alert("Update failed: " + cleanError);
          modal.remove();
        });
      });
    });
  });
}

function setupAddProductButtons() {
  let currentSection = '';
  let currentType = '';

  document.querySelectorAll('.add-product-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      currentSection = btn.dataset.section;
      currentType = btn.dataset.type;
      document.getElementById('addProductModal').style.display = 'flex';
    });
  });

  // Handle cancel
  document.querySelector('#addProductModal .cancel-btn').addEventListener('click', () => {
    document.getElementById('addProductModal').style.display = 'none';
  });

  // Handle form submit
  document.getElementById('addProductForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    formData.append('section', currentSection);
    formData.append('type', currentType);

    fetch('insert_product.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: formData
    })
    .then(res => res.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.status === 'success') {
          location.reload();
        } else {
          alert(data.message || 'Insert failed.');
        }
      } catch (err) {
        console.error('Insert error:', text);
        alert('Insert failed. Check console.');
      }
    });
  });
}

function setupDeleteButtons() {
  document.querySelectorAll('.delete-product-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const productId = btn.dataset.id;
      const productName = btn.closest('tr').querySelector('.product-link').textContent;
      showDeleteConfirmation('product', productId, `Delete product "${productName}"?`);
    });
  });

  document.querySelectorAll('.delete-group-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const productType = btn.dataset.type;
      const group = btn.dataset.group;
      showDeleteConfirmation('group', group, `Delete the entire "${group}" group from "${productType}"? This will delete all products in this group.`);
    });
  });

  document.querySelectorAll('.delete-type-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const productType = btn.dataset.type;
      showDeleteConfirmation('type', productType, `Delete all "${productType}" products? This will delete ALL products of this type.`);
    });
  });
}

function showDeleteConfirmation(scope, value, message) {
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
  fetch('delete_item.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `scope=${scope}&value=${encodeURIComponent(value)}`
  })
  .then(response => {
    if (!response.ok) throw new Error('Delete failed');
    return response.json();
  })
  .then(data => {
    if (data.status === 'success') {
      fetch('get_data.php')
        .then(res => res.json())
        .then(renderStockData)
        .catch(err => {
          console.error('Refresh error:', err);
          alert('Deleted successfully but failed to refresh data. Please reload the page.');
        });
    } else {
      throw new Error(data.message || 'Unknown error');
    }
  })
  .catch(error => {
    console.error('Delete error:', error);
    alert(`Delete failed: ${error.message}`);
  });
}

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
    backdrop-filter: blur(3px);
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
`;
document.head.appendChild(style);

fetch('get_data.php')
  .then(res => {
    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
    return res.json();
  })
  .then(data => {
    if (!data) throw new Error("Empty response from server");
    setTimeout(() => renderStockData(data), 300);
  })
  .catch(err => {
    console.error('Fetch error:', err);
    document.getElementById('report-container').innerHTML = `
      <div class="error">
        Failed to load data: ${err.message}
        <button onclick="location.reload()" class="btn" style="margin-top: 10px;">Retry</button>
      </div>
    `;
  });

document.addEventListener('click', function (e) {
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

// Save scroll position on refresh
window.addEventListener('beforeunload', () => {
  localStorage.setItem('scrollY', window.scrollY);
});

// Restore scroll position after reload
window.addEventListener('load', () => {
  const scrollY = localStorage.getItem('scrollY');
  if (scrollY !== null) {
    window.scrollTo({
      top: parseInt(scrollY, 10),
      behavior: 'smooth'
    });
  }
});
