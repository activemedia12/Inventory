function updateAvailableStock() {
  const selected = document.querySelector('#product_id').selectedOptions[0];
  const stock = selected ? parseInt(selected.getAttribute('data-stock')) || 0 : 0;

  document.getElementById('stock-info').textContent = `Available Stock: ${stock.toLocaleString()} sheets`;
  document.getElementById('used_sheets').setAttribute('max', stock);
  validateStock();
}

function validateStock() {
  const used = parseInt(document.getElementById('used_sheets')?.value) || 0;
  const selected = document.querySelector('#product_id')?.selectedOptions[0];
  const stock = selected ? parseInt(selected.getAttribute('data-stock')) || 0 : 0;

  const submitBtn = document.querySelector('button[type="submit"]');
  const stockInfo = document.getElementById('stock-info');

  if (used > stock) {
    stockInfo.textContent = `Insufficient stock! Available: ${stock.toLocaleString()} sheets`;
    stockInfo.style.color = 'red';
    submitBtn.disabled = true;
  } else {
    stockInfo.textContent = `Available Stock: ${stock.toLocaleString()} sheets`;
    stockInfo.style.color = 'black';
    submitBtn.disabled = false;
  }
}

function calculateReamsLive() {
  const quantity = parseFloat(document.querySelector('[name="quantity"]').value) || 0;
  const sets = parseFloat(document.querySelector('[name="number_of_sets"]').value) || 0;
  const copies = parseFloat(document.querySelector('[name="copies_per_set"]').value) || 0;
  const size = document.querySelector('[name="product_size"]').value;

  const cutMap = {
    'whole': 1,
    '1/2': 2,
    '1/3': 3,
    '1/4': 4,
    '1/6': 6,
    '1/8': 8
  };
  const cutSize = cutMap[size] || 1;

  if (!quantity || !sets || !cutSize) {
    document.getElementById('reams-estimate').textContent = '';
    return;
  }

  let totalSheets = quantity * sets;
  let cutSheets = totalSheets / cutSize;
  let reams = cutSheets / 500;
  let reamsPerProduct = copies > 0 ? (reams / copies) : reams;

  const output = `Estimated Reams per Product: ${reamsPerProduct.toFixed(2)} reams`;
  document.getElementById('reams-estimate').textContent = output;
}
