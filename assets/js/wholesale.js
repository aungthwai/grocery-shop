(function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | SHARED PAYMENT CONTROLLER
    |--------------------------------------------------------------------------
    | Used by both Wholesale Due and Customer Due History.
    |--------------------------------------------------------------------------
    */

    const config =
        window.GrocerEaseWholesaleData || {};

    const customers =
        Array.isArray(config.customers)
            ? config.customers
            : [];

    const modal =
        document.getElementById(
            'wholesalePaymentModal'
        );

    const form =
        document.getElementById(
            'wholesalePaymentForm'
        );

    if (!modal || !form) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | PREVENT DOUBLE INITIALIZATION
    |--------------------------------------------------------------------------
    | Some older pages may load this script more than once. Only one set of
    | payment listeners is allowed.
    |--------------------------------------------------------------------------
    */

    if (window.GrocerEaseWholesalePaymentsInitialized) {
        return;
    }

    window.GrocerEaseWholesalePaymentsInitialized = true;

    const customerSelect =
        document.getElementById(
            'wholesalePaymentCustomer'
        );

    const currentDue =
        document.getElementById(
            'wholesalePaymentCurrentDue'
        );

    const amountInput =
        document.getElementById(
            'wholesalePaymentAmount'
        );

    const paymentDate =
        document.getElementById(
            'wholesalePaymentDate'
        );

    const paymentMethod =
        document.getElementById(
            'wholesalePaymentMethod'
        );

    const notesInput =
        document.getElementById(
            'wholesalePaymentNotes'
        );

    const message =
        document.getElementById(
            'wholesalePaymentMessage'
        );

    const submitButton =
        document.getElementById(
            'wholesalePaymentSubmit'
        );

    const openButtons =
        Array.from(
            document.querySelectorAll(
                '.js-open-payment-modal'
            )
        );

    const closeButtons =
        Array.from(
            modal.querySelectorAll(
                '[data-close-payment-modal]'
            )
        );

    let saving = false;


    function money(value) {

        const amount =
            Number(value);

        return '৳' +
            (
                Number.isFinite(amount)
                    ? amount.toFixed(2)
                    : '0.00'
            );
    }


    function getCustomer(customerId) {

        const id =
            Number(customerId);

        return customers.find(
            function (customer) {

                return (
                    Number(
                        customer.customer_id
                    ) === id
                );

            }
        ) || null;
    }


    function clearMessage() {

        message.textContent = '';

        message.className =
            'wholesale-payment-message';
    }


    function showMessage(
        text,
        type
    ) {

        message.textContent = text;

        message.className =
            'wholesale-payment-message visible ' +
            (
                type === 'success'
                    ? 'success'
                    : 'error'
            );
    }


    function updateCustomerDue() {

        const customer =
            getCustomer(
                customerSelect.value
            );

        if (!customer) {

            currentDue.textContent =
                money(0);

            amountInput.removeAttribute(
                'max'
            );

            return;
        }

        const due =
            Number(
                customer.total_due
            );

        currentDue.textContent =
            money(due);

        amountInput.max =
            Number.isFinite(due)
                ? due.toFixed(2)
                : '0.00';
    }


    function resetPaymentForm() {

        form.reset();

        clearMessage();

        currentDue.textContent =
            money(0);

        amountInput.removeAttribute(
            'max'
        );

        if (paymentDate) {

            const today =
                paymentDate.max;

            if (today) {
                paymentDate.value =
                    today;
            }
        }

        saving = false;

        submitButton.disabled =
            false;

        submitButton.textContent =
            'Record Payment';
    }


    function openModal(customerId) {

        if (saving) {
            return;
        }

        resetPaymentForm();

        if (customerId) {

            customerSelect.value =
                String(customerId);

        }

        updateCustomerDue();

        modal.hidden = false;

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'wholesale-modal-open'
        );

        if (customerSelect.value) {

            amountInput.focus();

        } else {

            customerSelect.focus();

        }
    }


    function closeModal() {

        if (saving) {
            return;
        }

        modal.hidden = true;

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'wholesale-modal-open'
        );

        clearMessage();
    }


    openButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                function (event) {

                    event.preventDefault();

                    if (
                        button.disabled ||
                        button.hasAttribute(
                            'disabled'
                        )
                    ) {
                        return;
                    }

                    openModal(
                        button.dataset.customerId ||
                        ''
                    );

                }
            );

        }
    );


    closeButtons.forEach(
        function (button) {

            button.addEventListener(
                'click',
                closeModal
            );

        }
    );


    customerSelect.addEventListener(
        'change',
        function () {

            updateCustomerDue();

            clearMessage();

        }
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                !modal.hidden
            ) {
                closeModal();
            }

        }
    );


    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            if (saving) {
                return;
            }

            clearMessage();


            const customer =
                getCustomer(
                    customerSelect.value
                );

            if (!customer) {

                showMessage(
                    'Please select a wholesale customer.',
                    'error'
                );

                customerSelect.focus();

                return;
            }


            const amount =
                Number(
                    amountInput.value
                );

            const due =
                Number(
                    customer.total_due
                );

            if (
                !Number.isFinite(amount) ||
                amount <= 0
            ) {

                showMessage(
                    'Payment amount must be greater than zero.',
                    'error'
                );

                amountInput.focus();

                return;
            }


            if (
                Number.isFinite(due) &&
                amount > due + 0.001
            ) {

                showMessage(
                    'Payment cannot be greater than ' +
                    money(due) +
                    '.',
                    'error'
                );

                amountInput.focus();

                return;
            }


            if (!paymentDate.value) {

                showMessage(
                    'Please choose a payment date.',
                    'error'
                );

                paymentDate.focus();

                return;
            }


            saving = true;

            submitButton.disabled =
                true;

            submitButton.textContent =
                'Saving...';


            const body =
                new URLSearchParams();

            body.set(
                'csrf_token',
                String(
                    config.csrfToken || ''
                )
            );

            body.set(
                'customer_id',
                customerSelect.value
            );

            body.set(
                'amount',
                amount.toFixed(2)
            );

            body.set(
                'payment_date',
                paymentDate.value
            );

            body.set(
                'payment_method',
                paymentMethod.value
            );

            body.set(
                'notes',
                notesInput.value.trim()
            );


            try {

                const response =
                    await fetch(
                        String(
                            config.endpoint ||
                            'collect_payment.php'
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


                let data = null;

                try {
                    data =
                        await response.json();
                } catch (jsonError) {
                    throw new Error(
                        'The server returned an invalid response.'
                    );
                }


                if (
                    !response.ok ||
                    !data ||
                    !data.success
                ) {

                    throw new Error(
                        data &&
                        data.message
                            ? data.message
                            : 'Unable to record the payment.'
                    );
                }


                showMessage(
                    data.message,
                    'success'
                );


                customer.total_due =
                    Number(
                        data.remaining_due
                    );


                currentDue.textContent =
                    money(
                        data.remaining_due
                    );


                window.setTimeout(
                    function () {

                        window.location.reload();

                    },
                    800
                );


            } catch (error) {

                saving = false;

                submitButton.disabled =
                    false;

                submitButton.textContent =
                    'Record Payment';


                showMessage(
                    error &&
                    error.message
                        ? error.message
                        : 'Unable to record the payment.',
                    'error'
                );

            }

        }
    );

    console.log(
        'GrocerEase Wholesale payment controller loaded.'
    );

})();