    function printJobOrder(button) {
      const row = button.closest('tr');
      const order = JSON.parse(row.getAttribute('data-order'));

      const printWindow = window.open('', '', 'width=1000,height=1400');
      if (!printWindow) return;

      const paperSize = order.paper_size === 'custom' ? order.custom_paper_size : order.paper_size;
      const bindingType = order.binding_type === 'Custom' ? order.custom_binding : order.binding_type;
      const paperSequence = (order.paper_sequence || '').split(',').map(p => p.trim());
      const jobOrderDate = order.job_order_date
      ? new Date(order.job_order_date).toLocaleDateString('en-PH', {
          year: 'numeric', month: 'long', day: 'numeric'
          })
      : '';

      const html = `
        <html>
        <head>
          <title>Print Job Order</title>
          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
          <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
          <style>
            @media print {
              body {
                margin: 0;
                -webkit-print-color-adjust: exact;
              }
            }
            body {
              font-family: 'Poppins';
              position: relative;
            }
            .form-template {
              width: 1000px;
              height: auto;
              position: relative;
            }
            .form-template img {
              width: 100%;
            }
            .field {
              position: absolute;
              font-size: 15px;
              font-weight: 600;
              white-space: nowrap;
            }
            .job-order-date { top: 165px; left: 780px; }
            .client-name    { top: 165px; left: 300px; }
            .address        { top: 203px; left: 239px; width: 300px; font-size:12px;}
            .tin            { top: 230px; left: 130px; }
            .contact-number { top: 230px; left: 500px; }
            .client-by      { top: 263px; left: 180px; }
            .contact-person { top: 263px; left: 520px; }

            .project-name   { top: 350px; left: 70px; }
            .product-type   { top: 408px; left: 70px; }
            .quantity       { top: 350px; left: 280px; }
            .cut-size       { top: 362px; left: 503px; }
            .serial-range   { top: 350px; left: 700px; }

            .paper-size     { top: 343px; left: 503px; }
            .copies-per-set { top: 408px; left: 280px; }
            .binding-type   { top: 408px; left: 503px; }

            .color-seq      { top: 410px; left: 655px; width: 250px; font-size: 12px; }
            .special-notes  { top: 470px; left: 70px; width: 800px; font-size: 13px; }
          </style>
        </head>
        <body>
          <div class="form-template">
            <img src="../assets/images/job-order.jpg" alt="Job Order Form Template" />
            <div class="field job-order-date">${jobOrderDate}</div>
            <div class="field client-name">${order.client_name || ''}</div>
            <div class="field address">${order.client_address || ''}</div>
            <div class="field tin">${order.tin || ''}</div>
            <div class="field contact-number">${order.contact_number || ''}</div>
            <div class="field client-by">${order.client_by || ''}</div>
            <div class="field contact-person">${order.contact_person || ''}</div>

            <div class="field project-name">${order.project_name || ''}</div>
            <div class="field quantity">${order.quantity || ''}</div>
            <div class="field product-type">${order.paper_type || ''}</div>
            <div class="field cut-size">Cut Size: ${order.product_size || ''}</div>
            <div class="field serial-range">${order.serial_range || ''}</div>

            <div class="field paper-size">${paperSize}</div>
            <div class="field copies-per-set">
                ${order.copies_per_set || ''}
                ${
                    order.copies_per_set == 1
                    ? ' - No Duplicate'
                    : order.copies_per_set == 2
                    ? ' - Duplicate'
                    : order.copies_per_set == 3
                    ? ' - Triplicate'
                    : order.copies_per_set == 4
                    ? ' - Quadruplicate'
                    : order.copies_per_set > 4
                    ? ` - ${order.copies_per_set} Copies`
                    : ''
                }
            </div>
            <div class="field binding-type">${bindingType}</div>

            <div class="field color-seq">${paperSequence.join(', ')}</div>
            <div class="field special-notes">${(order.special_instructions || '').replace(/\n/g, '<br>')}</div>
          </div>

          <script>
            // window.onload = function () {
            //   window.print();
            //   setTimeout(function() { window.close(); }, 500);
            // };
          </script>
        </body>
        </html>
      `;

      printWindow.document.open();
      printWindow.document.write(html);
      printWindow.document.close();
    }