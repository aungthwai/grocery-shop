(function () {
    'use strict';

    const config = window.GrocerEaseSaleData || {};
    const products = Array.isArray(config.products) ? config.products : [];
    const customers = Array.isArray(config.customers) ? config.customers : [];

    let customerMode = 'retail';
    let selectedPayment = 'Cash';
    let saleItems = [];
    let saveInProgress = false;

    const retailModeButton = document.getElementById('retailModeButton');
    const wholesaleModeButton = document.getElementById('wholesaleModeButton');
    const wholesalePicker = document.getElementById('wholesalePicker');
    const customerSelect = document.getElementById('customer_id');
    const customerMeta = document.getElementById('customerMeta');

    const productSearch = document.getElementById('product_search');
    const selectedProductId = document.getElementById('selected_product_id');
    const selectedProductHint = document.getElementById('selectedProductHint');
    const productQuantity = document.getElementById('product_quantity');
    const addSaleProductButton = document.getElementById('addSaleProductButton');
    const saleItemsTableBody = document.getElementById('saleItemsTableBody');

    const paymentButtons = Array.from(
        document.querySelectorAll('.sale-payment-button')
    );

    const paymentNote = document.getElementById('paymentNote');
    const saveDueButton = document.getElementById('saveDueButton');
    const confirmSaleButton = document.getElementById('confirmSaleButton');
    const saleTotal = document.getElementById('saleTotal');
    const saleMessage = document.getElementById('saleMessage');


    /*
    |--------------------------------------------------------------------------
    | FORMAT CURRENCY
    |--------------------------------------------------------------------------
    */

    function formatCurrency(amount) {
        const value = Number(amount);

        return '৳' + (
            Number.isFinite(value)
                ? value.toFixed(2)
                : '0.00'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MESSAGE HELPERS
    |--------------------------------------------------------------------------
    */

    function showMessage(message, type) {

        saleMessage.textContent = message;

        saleMessage.className =
            'sale-message visible ' +
            (type === 'success' ? 'success' : 'error');

        saleMessage.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }


    function clearMessage() {

        saleMessage.textContent = '';
        saleMessage.className = 'sale-message';

    }


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE SEARCH VALUE
    |--------------------------------------------------------------------------
    */

    function normalized(value) {

        return String(value || '')
            .trim()
            .toLowerCase();

    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT DISPLAY VALUE
    |--------------------------------------------------------------------------
    */

    function productDisplayValue(product) {

        const barcode =
            String(product.barcode || '').trim();

        return product.product_name +
            (barcode ? ' - ' + barcode : '');

    }


    /*
    |--------------------------------------------------------------------------
    | FIND PRODUCT
    |--------------------------------------------------------------------------
    */

    function findProduct(value) {

        const search = normalized(value);

        if (!search) {
            return null;
        }

        return products.find(function (product) {

            return (
                normalized(product.product_name) === search ||
                normalized(product.barcode) === search ||
                normalized(productDisplayValue(product)) === search
            );

        }) || null;

    }


    /*
    |--------------------------------------------------------------------------
    | FIND CUSTOMER
    |--------------------------------------------------------------------------
    */

    function findCustomer(customerId) {

        const id = Number(customerId);

        return customers.find(function (customer) {

            return Number(customer.customer_id) === id;

        }) || null;

    }


    /*
    |--------------------------------------------------------------------------
    | SELECTED PRODUCT INFORMATION
    |--------------------------------------------------------------------------
    */

    function updateSelectedProduct() {

        const product =
            findProduct(productSearch.value);

        selectedProductHint.classList.remove(
            'selected',
            'error'
        );

        if (!product) {

            selectedProductId.value = '';

            selectedProductHint.textContent =
                productSearch.value.trim()
                    ? 'Choose a product from the available search results.'
                    : 'Search by product name or barcode.';

            if (productSearch.value.trim()) {

                selectedProductHint.classList.add('error');

            }

            return null;

        }


        selectedProductId.value =
            String(product.product_id);


        selectedProductHint.textContent =
            'Stock: ' +
            product.stock +
            ' ' +
            product.unit +
            ' • Unit price: ' +
            formatCurrency(product.selling_price);


        selectedProductHint.classList.add('selected');

        return product;

    }


    /*
    |--------------------------------------------------------------------------
    | WHOLESALE CUSTOMER INFORMATION
    |--------------------------------------------------------------------------
    */

    function updateCustomerMeta() {

        if (customerMode !== 'wholesale') {

            customerMeta.textContent = '';
            return;

        }


        const customer =
            findCustomer(customerSelect.value);


        if (!customer) {

            customerMeta.textContent =
                'Select an active wholesale customer for this sale.';

            return;

        }


        const parts = [];


        if (customer.phone) {

            parts.push(customer.phone);

        }


        parts.push(
            'Current due: ' +
            formatCurrency(customer.total_due)
        );


        customerMeta.textContent =
            parts.join(' • ');

    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE SALE TOTAL
    |--------------------------------------------------------------------------
    */

    function calculateTotal() {

        return saleItems.reduce(
            function (total, item) {

                return total +
                    (
                        Number(item.selling_price) *
                        Number(item.quantity)
                    );

            },
            0
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(
            value == null ? '' : value
        )
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER SALE ITEMS
    |--------------------------------------------------------------------------
    */

    function renderSaleItems() {

        if (!saleItems.length) {

            saleItemsTableBody.innerHTML =
                '<tr class="sale-empty-row">' +
                    '<td colspan="6">' +
                        '<span>No products added yet.</span>' +
                        'Search for a product above to start the sale.' +
                    '</td>' +
                '</tr>';


            saleTotal.textContent =
                formatCurrency(0);

            return;

        }


        saleItemsTableBody.innerHTML =
            saleItems.map(function (item) {

                const barcode =
                    item.barcode
                        ? '<span class="sale-product-subtext">' +
                            escapeHtml(item.barcode) +
                          '</span>'
                        : '';


                return (
                    '<tr data-product-id="' +
                        item.product_id +
                    '">' +

                        '<td>' +
                            '<span class="sale-product-name">' +
                                escapeHtml(item.product_name) +
                            '</span>' +
                            barcode +
                        '</td>' +

                        '<td>' +
                            '<input ' +
                                'class="sale-line-quantity" ' +
                                'type="number" ' +
                                'min="1" ' +
                                'max="' + item.stock + '" ' +
                                'step="1" ' +
                                'value="' + item.quantity + '" ' +
                                'data-action="quantity" ' +
                                'aria-label="Quantity for ' +
                                    escapeHtml(item.product_name) +
                                '">' +
                        '</td>' +

                        '<td>' +
                            escapeHtml(item.unit) +
                        '</td>' +

                        '<td>' +
                            formatCurrency(item.selling_price) +
                        '</td>' +

                        '<td>' +
                            formatCurrency(
                                Number(item.selling_price) *
                                Number(item.quantity)
                            ) +
                        '</td>' +

                        '<td>' +
                            '<button ' +
                                'type="button" ' +
                                'class="sale-delete-button" ' +
                                'data-action="remove" ' +
                                'title="Remove product" ' +
                                'aria-label="Remove ' +
                                    escapeHtml(item.product_name) +
                                '">' +
                                '⌫' +
                            '</button>' +
                        '</td>' +

                    '</tr>'
                );

            }).join('');


        saleTotal.textContent =
            formatCurrency(calculateTotal());

    }


    /*
    |--------------------------------------------------------------------------
    | ADD PRODUCT
    |--------------------------------------------------------------------------
    */

    function addProduct() {

        clearMessage();


        const product =
            updateSelectedProduct();


        if (!product) {

            showMessage(
                'Please select a valid product.',
                'error'
            );

            productSearch.focus();

            return;

        }


        const quantity =
            Number(productQuantity.value);


        if (
            !Number.isInteger(quantity) ||
            quantity <= 0
        ) {

            showMessage(
                'Quantity must be a positive whole number.',
                'error'
            );

            productQuantity.focus();

            return;

        }


        if (
            quantity >
            Number(product.stock)
        ) {

            showMessage(
                product.product_name +
                ' only has ' +
                product.stock +
                ' ' +
                product.unit +
                ' in stock.',
                'error'
            );

            productQuantity.focus();

            return;

        }


        const alreadyExists =
            saleItems.some(function (item) {

                return (
                    Number(item.product_id) ===
                    Number(product.product_id)
                );

            });


        if (alreadyExists) {

            showMessage(
                'That product is already in the sale. Change its quantity in the table instead.',
                'error'
            );

            return;

        }


        saleItems.push({

            product_id:
                Number(product.product_id),

            product_name:
                String(product.product_name),

            barcode:
                String(product.barcode || ''),

            unit:
                String(product.unit || 'pcs'),

            selling_price:
                Number(product.selling_price),

            stock:
                Number(product.stock),

            quantity:
                quantity

        });


        productSearch.value = '';

        selectedProductId.value = '';

        productQuantity.value = '1';


        selectedProductHint.textContent =
            'Search by product name or barcode.';


        selectedProductHint.classList.remove(
            'selected',
            'error'
        );


        renderSaleItems();

    }


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER MODE
    |--------------------------------------------------------------------------
    */

    function setCustomerMode(mode) {

        customerMode =
            mode === 'wholesale'
                ? 'wholesale'
                : 'retail';


        retailModeButton.classList.toggle(
            'active',
            customerMode === 'retail'
        );


        wholesaleModeButton.classList.toggle(
            'active',
            customerMode === 'wholesale'
        );


        wholesalePicker.hidden =
            customerMode !== 'wholesale';


        if (customerMode === 'retail') {

            customerSelect.value = '';

            saveDueButton.disabled = true;


            const dueButton =
                paymentButtons.find(
                    function (button) {

                        return (
                            button.dataset.payment ===
                            'Due'
                        );

                    }
                );


            if (dueButton) {

                dueButton.disabled = true;

            }


            if (selectedPayment === 'Due') {

                selectPayment('Cash');

            }


            paymentNote.textContent =
                'Walk-in retail sales must be paid at checkout.';

        } else {

            saveDueButton.disabled = false;


            const dueButton =
                paymentButtons.find(
                    function (button) {

                        return (
                            button.dataset.payment ===
                            'Due'
                        );

                    }
                );


            if (dueButton) {

                dueButton.disabled = false;

            }


            paymentNote.textContent =
                'Wholesale sales can be paid now or saved as customer due.';

        }


        updateCustomerMeta();

        clearMessage();

    }


    /*
    |--------------------------------------------------------------------------
    | SELECT PAYMENT
    |--------------------------------------------------------------------------
    */

    function selectPayment(payment) {

        if (
            payment === 'Due' &&
            customerMode !== 'wholesale'
        ) {

            return;

        }


        selectedPayment = payment;


        paymentButtons.forEach(
            function (button) {

                button.classList.toggle(
                    'active',
                    button.dataset.payment === payment
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE BEFORE SAVE
    |--------------------------------------------------------------------------
    */

    function validateBeforeSave(saveMode) {

        if (!saleItems.length) {

            return (
                'Please add at least one product before saving the sale.'
            );

        }


        if (
            customerMode === 'wholesale' &&
            !findCustomer(customerSelect.value)
        ) {

            return (
                'Please select a wholesale customer.'
            );

        }


        if (
            saveMode === 'due' &&
            customerMode !== 'wholesale'
        ) {

            return (
                'Only wholesale customer sales can be saved as due.'
            );

        }


        if (
            saveMode === 'paid' &&
            selectedPayment === 'Due'
        ) {

            return (
                'Use “Save as due” for a due sale, or choose Cash, Bkash, or Card.'
            );

        }


        return '';

    }


    /*
    |--------------------------------------------------------------------------
    | SAVE SALE
    |--------------------------------------------------------------------------
    */

    async function saveSale(
        saveMode,
        generateInvoice
    ) {

        if (saveInProgress) {

            return;

        }


        clearMessage();


        const validationError =
            validateBeforeSave(saveMode);


        if (validationError) {

            showMessage(
                validationError,
                'error'
            );

            return;

        }


        let invoiceWindow = null;


        if (generateInvoice) {

            invoiceWindow =
                window.open(
                    '',
                    '_blank',
                    'width=860,height=700'
                );


            if (invoiceWindow) {

                invoiceWindow.document.write(
                    '<!doctype html>' +
                    '<html>' +
                    '<head>' +
                    '<meta charset="utf-8">' +
                    '<title>Preparing invoice...</title>' +
                    '</head>' +
                    '<body style="font-family:Arial,sans-serif;padding:32px;color:#334155">' +
                    'Preparing invoice...' +
                    '</body>' +
                    '</html>'
                );


                invoiceWindow.document.close();

            }

        }


        saveInProgress = true;

        confirmSaleButton.disabled = true;

        saveDueButton.disabled = true;


        const body =
            new URLSearchParams();


        body.set(
            'save_sale',
            '1'
        );


        body.set(
            'csrf_token',
            String(config.csrfToken || '')
        );


        body.set(
            'customer_mode',
            customerMode
        );


        body.set(
            'customer_id',
            customerMode === 'wholesale'
                ? customerSelect.value
                : ''
        );


        body.set(
            'payment_method',
            saveMode === 'due'
                ? 'Due'
                : selectedPayment
        );


        body.set(
            'save_mode',
            saveMode
        );


        body.set(
            'sale_items',
            JSON.stringify(
                saleItems.map(
                    function (item) {

                        return {

                            product_id:
                                item.product_id,

                            quantity:
                                item.quantity

                        };

                    }
                )
            )
        );


        try {

            const response =
                await fetch(
                    String(
                        config.endpoint ||
                        window.location.href
                    ),
                    {

                        method: 'POST',

                        headers: {

                            'Content-Type':
                                'application/x-www-form-urlencoded; charset=UTF-8'

                        },

                        body:
                            body.toString(),

                        credentials:
                            'same-origin'

                    }
                );


            const data =
                await response.json();


            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data &&
                    data.message
                        ? data.message
                        : 'Unable to save the sale.'
                );

            }


            showMessage(
                data.message,
                'success'
            );


            if (generateInvoice) {

                openInvoice(
                    data,
                    invoiceWindow
                );

            }


            resetSaleAfterSave(data);

        } catch (error) {

            if (
                invoiceWindow &&
                !invoiceWindow.closed
            ) {

                invoiceWindow.close();

            }


            showMessage(
                error &&
                error.message
                    ? error.message
                    : 'Unable to save the sale.',
                'error'
            );

        } finally {

            saveInProgress = false;


            confirmSaleButton.disabled =
                false;


            saveDueButton.disabled =
                customerMode !== 'wholesale';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RESET AFTER SAVE
    |--------------------------------------------------------------------------
    */

    function resetSaleAfterSave(data) {

        const savedItems =
            saleItems.slice();


        savedItems.forEach(
            function (savedItem) {

                const product =
                    products.find(
                        function (candidate) {

                            return (
                                Number(candidate.product_id) ===
                                Number(savedItem.product_id)
                            );

                        }
                    );


                if (product) {

                    product.stock =
                        Math.max(
                            0,
                            Number(product.stock) -
                            Number(savedItem.quantity)
                        );

                }

            }
        );


        if (
            customerMode === 'wholesale' &&
            Number(
                data &&
                data.due_amount
            ) > 0
        ) {

            const customer =
                findCustomer(
                    customerSelect.value
                );


            if (customer) {

                customer.total_due =
                    Number(customer.total_due) +
                    Number(data.due_amount);

            }

        }


        saleItems = [];


        renderSaleItems();


        productSearch.value = '';

        productQuantity.value = '1';

        selectedProductId.value = '';


        selectedProductHint.textContent =
            'Search by product name or barcode.';


        selectedProductHint.classList.remove(
            'selected',
            'error'
        );


        selectPayment('Cash');

        updateCustomerMeta();

    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE INVOICE
    |--------------------------------------------------------------------------
    */

    function openInvoice(
        data,
        invoiceWindow
    ) {

        if (
            !invoiceWindow ||
            invoiceWindow.closed
        ) {

            showMessage(
                'Sale saved, but the invoice window was blocked by the browser. Allow pop-ups to print it.',
                'error'
            );

            return;

        }


        const shop =
            data.shop || {};


        const items =
            Array.isArray(data.items)
                ? data.items
                : [];


        const rows =
            items.map(
                function (item, index) {

                    return (
                        '<tr>' +

                            '<td>' +
                                (index + 1) +
                            '</td>' +

                            '<td>' +
                                escapeHtml(
                                    item.product_name
                                ) +
                            '</td>' +

                            '<td>' +
                                escapeHtml(
                                    item.quantity
                                ) +
                                ' ' +
                                escapeHtml(
                                    item.unit
                                ) +
                            '</td>' +

                            '<td>' +
                                formatCurrency(
                                    item.selling_price
                                ) +
                            '</td>' +

                            '<td>' +
                                formatCurrency(
                                    item.subtotal
                                ) +
                            '</td>' +

                        '</tr>'
                    );

                }
            ).join('');


        const contactBits = [

            shop.address,
            shop.phone,
            shop.email

        ]
            .filter(Boolean)
            .map(escapeHtml)
            .join(' • ');


        invoiceWindow.document.open();


        invoiceWindow.document.write(

            '<!doctype html>' +

            '<html>' +

            '<head>' +

                '<meta charset="utf-8">' +

                '<title>' +
                    escapeHtml(
                        data.invoice_no
                    ) +
                '</title>' +


                '<style>' +

                    'body{' +
                        'font-family:Arial,sans-serif;' +
                        'color:#111827;' +
                        'margin:0;' +
                        'padding:36px;' +
                        'background:#fff;' +
                    '}' +

                    '.sheet{' +
                        'max-width:760px;' +
                        'margin:0 auto;' +
                    '}' +

                    '.head{' +
                        'display:flex;' +
                        'justify-content:space-between;' +
                        'gap:20px;' +
                        'border-bottom:2px solid #111827;' +
                        'padding-bottom:18px;' +
                    '}' +

                    'h1{' +
                        'margin:0;' +
                        'font-size:26px;' +
                    '}' +

                    '.muted{' +
                        'color:#64748b;' +
                        'font-size:12px;' +
                        'line-height:1.6;' +
                    '}' +

                    '.meta{' +
                        'text-align:right;' +
                        'font-size:12px;' +
                        'line-height:1.65;' +
                    '}' +

                    '.customer{' +
                        'margin:22px 0;' +
                        'padding:14px 16px;' +
                        'background:#f8fafc;' +
                        'border-radius:8px;' +
                        'font-size:13px;' +
                    '}' +

                    'table{' +
                        'width:100%;' +
                        'border-collapse:collapse;' +
                        'margin-top:18px;' +
                    '}' +

                    'th,td{' +
                        'padding:11px 9px;' +
                        'border-bottom:1px solid #e5e7eb;' +
                        'text-align:left;' +
                        'font-size:12px;' +
                    '}' +

                    'th{' +
                        'background:#f8fafc;' +
                    '}' +

                    'th:last-child,' +
                    'td:last-child{' +
                        'text-align:right;' +
                    '}' +

                    '.totals{' +
                        'margin-left:auto;' +
                        'width:300px;' +
                        'margin-top:22px;' +
                    '}' +

                    '.row{' +
                        'display:flex;' +
                        'justify-content:space-between;' +
                        'padding:6px 0;' +
                        'font-size:13px;' +
                    '}' +

                    '.grand{' +
                        'font-size:20px;' +
                        'font-weight:700;' +
                        'border-top:2px solid #111827;' +
                        'padding-top:10px;' +
                        'margin-top:6px;' +
                    '}' +

                    '.footer{' +
                        'margin-top:40px;' +
                        'text-align:center;' +
                        'color:#64748b;' +
                        'font-size:11px;' +
                    '}' +

                    '@media print{' +

                        'body{' +
                            'padding:0;' +
                        '}' +

                        '.no-print{' +
                            'display:none;' +
                        '}' +

                    '}' +

                '</style>' +

            '</head>' +

            '<body>' +

                '<div class="sheet">' +

                    '<div class="head">' +

                        '<div>' +

                            '<h1>' +
                                escapeHtml(
                                    shop.shop_name ||
                                    'GrocerEase'
                                ) +
                            '</h1>' +

                            '<div class="muted">' +
                                contactBits +
                            '</div>' +

                        '</div>' +


                        '<div class="meta">' +

                            '<strong>' +
                                'SALE INVOICE' +
                            '</strong>' +

                            '<br>' +

                            'Invoice: ' +
                            escapeHtml(
                                data.invoice_no
                            ) +

                            '<br>' +

                            'Date: ' +
                            escapeHtml(
                                data.sale_date
                            ) +

                        '</div>' +

                    '</div>' +


                    '<div class="customer">' +

                        '<strong>' +
                            'Customer:' +
                        '</strong> ' +

                        escapeHtml(
                            data.customer_name ||
                            'Walk-in Customer'
                        ) +

                        (
                            data.customer_phone
                                ? '<br>' +
                                  '<span class="muted">' +
                                  escapeHtml(
                                      data.customer_phone
                                  ) +
                                  '</span>'
                                : ''
                        ) +

                    '</div>' +


                    '<table>' +

                        '<thead>' +

                            '<tr>' +

                                '<th>#</th>' +

                                '<th>Product</th>' +

                                '<th>Qty</th>' +

                                '<th>Unit Price</th>' +

                                '<th>Total</th>' +

                            '</tr>' +

                        '</thead>' +

                        '<tbody>' +

                            rows +

                        '</tbody>' +

                    '</table>' +


                    '<div class="totals">' +

                        '<div class="row">' +

                            '<span>Payment</span>' +

                            '<strong>' +
                                escapeHtml(
                                    data.payment_label
                                ) +
                            '</strong>' +

                        '</div>' +


                        '<div class="row">' +

                            '<span>Paid</span>' +

                            '<strong>' +
                                formatCurrency(
                                    data.paid_amount
                                ) +
                            '</strong>' +

                        '</div>' +


                        '<div class="row">' +

                            '<span>Due</span>' +

                            '<strong>' +
                                formatCurrency(
                                    data.due_amount
                                ) +
                            '</strong>' +

                        '</div>' +


                        '<div class="row grand">' +

                            '<span>Total</span>' +

                            '<span>' +
                                formatCurrency(
                                    data.total_amount
                                ) +
                            '</span>' +

                        '</div>' +

                    '</div>' +


                    '<div class="footer">' +

                        'Thank you for shopping with us.' +

                    '</div>' +


                    '<script>' +
                        'window.onload=function(){' +
                            'window.print();' +
                        '};' +
                    '<\/script>' +

                '</div>' +

            '</body>' +

            '</html>'

        );


        invoiceWindow.document.close();

    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCT SEARCH EVENTS
    |--------------------------------------------------------------------------
    */

    productSearch.addEventListener(
        'change',
        updateSelectedProduct
    );


    productSearch.addEventListener(
        'blur',
        updateSelectedProduct
    );


    productSearch.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Enter') {

                event.preventDefault();

                addProduct();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | QUANTITY ENTER KEY
    |--------------------------------------------------------------------------
    */

    productQuantity.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Enter') {

                event.preventDefault();

                addProduct();

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | ADD PRODUCT BUTTON
    |--------------------------------------------------------------------------
    */

    addSaleProductButton.addEventListener(
        'click',
        addProduct
    );


    /*
    |--------------------------------------------------------------------------
    | REMOVE PRODUCT
    |--------------------------------------------------------------------------
    */

    saleItemsTableBody.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '[data-action="remove"]'
                );


            if (!button) {

                return;

            }


            const row =
                button.closest(
                    'tr[data-product-id]'
                );


            if (!row) {

                return;

            }


            const productId =
                Number(
                    row.dataset.productId
                );


            saleItems =
                saleItems.filter(
                    function (item) {

                        return (
                            Number(
                                item.product_id
                            ) !== productId
                        );

                    }
                );


            renderSaleItems();

            clearMessage();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | CHANGE LINE QUANTITY
    |--------------------------------------------------------------------------
    */

    saleItemsTableBody.addEventListener(
        'change',
        function (event) {

            const input =
                event.target.closest(
                    '[data-action="quantity"]'
                );


            if (!input) {

                return;

            }


            const row =
                input.closest(
                    'tr[data-product-id]'
                );


            if (!row) {

                return;

            }


            const productId =
                Number(
                    row.dataset.productId
                );


            const item =
                saleItems.find(
                    function (line) {

                        return (
                            Number(
                                line.product_id
                            ) === productId
                        );

                    }
                );


            if (!item) {

                return;

            }


            const quantity =
                Number(input.value);


            if (
                !Number.isInteger(quantity) ||
                quantity <= 0
            ) {

                input.value =
                    String(item.quantity);


                showMessage(
                    'Quantity must be a positive whole number.',
                    'error'
                );


                return;

            }


            if (
                quantity >
                Number(item.stock)
            ) {

                input.value =
                    String(item.quantity);


                showMessage(
                    item.product_name +
                    ' only has ' +
                    item.stock +
                    ' ' +
                    item.unit +
                    ' in stock.',
                    'error'
                );


                return;

            }


            item.quantity =
                quantity;


            clearMessage();

            renderSaleItems();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER MODE BUTTONS
    |--------------------------------------------------------------------------
    */

    retailModeButton.addEventListener(
        'click',
        function () {

            setCustomerMode('retail');

        }
    );


    wholesaleModeButton.addEventListener(
        'click',
        function () {

            setCustomerMode('wholesale');

        }
    );


    customerSelect.addEventListener(
        'change',
        updateCustomerMeta
    );


    /*
    |--------------------------------------------------------------------------
    | PAYMENT BUTTONS
    |--------------------------------------------------------------------------
    */

    paymentButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                function () {

                    if (button.disabled) {

                        return;

                    }


                    selectPayment(
                        button.dataset.payment ||
                        'Cash'
                    );

                }
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | SAVE AS DUE
    |--------------------------------------------------------------------------
    */

    saveDueButton.addEventListener(
        'click',
        function () {

            selectPayment('Due');

            saveSale(
                'due',
                false
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | CONFIRM SALE + INVOICE
    |--------------------------------------------------------------------------
    */

    confirmSaleButton.addEventListener(
        'click',
        function () {

            if (
                selectedPayment === 'Due'
            ) {

                saveSale(
                    'due',
                    true
                );

            } else {

                saveSale(
                    'paid',
                    true
                );

            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | INITIAL PAGE STATE
    |--------------------------------------------------------------------------
    */

    renderSaleItems();

    setCustomerMode('retail');

})();