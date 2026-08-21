<?php
/*
|--------------------------------------------------------------------------
| WHOLESALE PAYMENT MODAL
|--------------------------------------------------------------------------
| Expected variables:
| - $collectCustomers
|--------------------------------------------------------------------------
*/
?>

<div
    class="wholesale-payment-modal"
    id="wholesalePaymentModal"
    hidden
    aria-hidden="true"
>
    <div
        class="wholesale-payment-modal-backdrop"
        data-close-payment-modal
    ></div>

    <div
        class="wholesale-payment-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="wholesalePaymentModalTitle"
    >

        <div class="wholesale-payment-dialog-header">

            <div>
                <h2 id="wholesalePaymentModalTitle">
                    Collect Payment
                </h2>

                <p>
                    Record a wholesale due payment.
                </p>
            </div>

            <button
                type="button"
                class="wholesale-payment-close"
                data-close-payment-modal
                aria-label="Close payment form"
            >
                ×
            </button>

        </div>


        <form
            id="wholesalePaymentForm"
            class="wholesale-payment-form"
            novalidate
        >

            <div class="wholesale-payment-field">

                <label for="wholesalePaymentCustomer">
                    Customer
                </label>

                <select
                    id="wholesalePaymentCustomer"
                    name="customer_id"
                    required
                >

                    <option value="">
                        Select customer
                    </option>

                    <?php foreach ($collectCustomers as $collectCustomer): ?>

                        <option
                            value="<?php echo (int) $collectCustomer['customer_id']; ?>"
                            data-due="<?php echo number_format(
                                (float) $collectCustomer['total_due'],
                                2,
                                '.',
                                ''
                            ); ?>"
                        >
                            <?php echo htmlspecialchars(
                                (string) $collectCustomer['customer_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>

                            <?php if (!empty($collectCustomer['phone'])): ?>
                                -
                                <?php echo htmlspecialchars(
                                    (string) $collectCustomer['phone'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            <?php endif; ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="wholesale-payment-due-box">

                <span>
                    Current Outstanding Due
                </span>

                <strong id="wholesalePaymentCurrentDue">
                    ৳0.00
                </strong>

            </div>


            <div class="wholesale-payment-grid">

                <div class="wholesale-payment-field">

                    <label for="wholesalePaymentAmount">
                        Payment Amount (৳)
                    </label>

                    <input
                        type="number"
                        id="wholesalePaymentAmount"
                        name="amount"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="0.00"
                        required
                    >

                </div>


                <div class="wholesale-payment-field">

                    <label for="wholesalePaymentDate">
                        Payment Date
                    </label>

                    <input
                        type="date"
                        id="wholesalePaymentDate"
                        name="payment_date"
                        value="<?php echo date('Y-m-d'); ?>"
                        max="<?php echo date('Y-m-d'); ?>"
                        required
                    >

                </div>

            </div>


            <div class="wholesale-payment-field">

                <label for="wholesalePaymentMethod">
                    Payment Method
                </label>

                <select
                    id="wholesalePaymentMethod"
                    name="payment_method"
                    required
                >
                    <option value="Cash">
                        Cash
                    </option>

                    <option value="Mobile Banking">
                        bKash / Mobile Banking
                    </option>

                    <option value="Card">
                        Card
                    </option>
                </select>

            </div>


            <div class="wholesale-payment-field">

                <label for="wholesalePaymentNotes">
                    Notes
                    <span>(Optional)</span>
                </label>

                <textarea
                    id="wholesalePaymentNotes"
                    name="notes"
                    rows="3"
                    maxlength="1000"
                    placeholder="Add a short payment note..."
                ></textarea>

            </div>


            <div
                class="wholesale-payment-message"
                id="wholesalePaymentMessage"
                role="status"
                aria-live="polite"
            ></div>


            <div class="wholesale-payment-actions">

                <button
                    type="button"
                    class="wholesale-payment-cancel"
                    data-close-payment-modal
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="wholesale-payment-submit"
                    id="wholesalePaymentSubmit"
                >
                    Record Payment
                </button>

            </div>

        </form>

    </div>
</div>
