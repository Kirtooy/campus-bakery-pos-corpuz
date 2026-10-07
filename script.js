(() => {
    'use strict';

    const app = window.CAMPUS_BAKERY;
    const products = new Map(app.products.map((product) => [product.id, product]));
    const cart = new Map();

    const cartItems = document.querySelector('#cart-items');
    const cartEmpty = document.querySelector('#cart-empty');
    const cartTotal = document.querySelector('#cart-total');
    const clearOrder = document.querySelector('#clear-order');
    const paymentForm = document.querySelector('#payment-form');
    const paymentInput = document.querySelector('#cash-payment');
    const paymentError = document.querySelector('#payment-error');
    const payButton = document.querySelector('#pay-button');
    const feedback = document.querySelector('#feedback');
    const receiptDialog = document.querySelector('#receipt-dialog');

    function peso(cents) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
        }).format(cents / 100).replace('PHP', '₱');
    }

    function cartTotalCents() {
        let total = 0;
        cart.forEach((quantity, id) => {
            total += products.get(id).price_cents * quantity;
        });
        return total;
    }

    function showFeedback(message, type = 'success') {
        feedback.textContent = message;
        feedback.className = `feedback feedback--${type}`;
        window.clearTimeout(showFeedback.timer);
        showFeedback.timer = window.setTimeout(() => {
            feedback.textContent = '';
            feedback.className = 'feedback';
        }, 2400);
    }

    function setPaymentError(message = '') {
        paymentError.textContent = message;
        paymentInput.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (message) {
            paymentInput.focus();
        }
    }

    function makeButton(label, className, action, id, disabled = false) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.dataset.action = action;
        button.dataset.productId = id;
        button.setAttribute('aria-label', label);
        button.disabled = disabled;
        button.textContent = action === 'remove' ? 'Remove' : action === 'decrease' ? '−' : '+';
        return button;
    }

    function renderCart() {
        cartItems.replaceChildren();
        const isEmpty = cart.size === 0;

        cartEmpty.hidden = !isEmpty;
        cartItems.hidden = isEmpty;
        paymentInput.disabled = isEmpty;
        payButton.disabled = isEmpty;
        clearOrder.disabled = isEmpty;

        cart.forEach((quantity, id) => {
            const product = products.get(id);
            const line = document.createElement('article');
            line.className = 'cart-line';

            const details = document.createElement('div');
            details.className = 'cart-line-details';
            const name = document.createElement('h3');
            name.textContent = product.name;
            const unit = document.createElement('p');
            unit.textContent = `${peso(product.price_cents)} each`;
            details.append(name, unit);

            const subtotal = document.createElement('strong');
            subtotal.className = 'cart-line-subtotal';
            subtotal.textContent = peso(product.price_cents * quantity);

            const controls = document.createElement('div');
            controls.className = 'cart-line-controls';
            const stepper = document.createElement('div');
            stepper.className = 'quantity-stepper';
            const decrease = makeButton(`Decrease ${product.name} quantity`, 'quantity-button', 'decrease', id, quantity === 1);
            const amount = document.createElement('span');
            amount.textContent = String(quantity);
            amount.setAttribute('aria-label', `Quantity ${quantity}`);
            const increase = makeButton(`Increase ${product.name} quantity`, 'quantity-button', 'increase', id, quantity >= 999);
            stepper.append(decrease, amount, increase);
            const remove = makeButton(`Remove ${product.name}`, 'remove-button', 'remove', id);
            controls.append(stepper, remove);

            line.append(details, subtotal, controls);
            cartItems.append(line);
        });

        cartTotal.textContent = peso(cartTotalCents());
        if (isEmpty) {
            paymentInput.value = '';
            setPaymentError();
        }
    }

    function addProduct(id) {
        const product = products.get(id);
        if (!product) return;
        cart.set(id, Math.min((cart.get(id) || 0) + 1, 999));
        renderCart();
        showFeedback(`${product.name} added to the order.`);
    }

    document.querySelectorAll('[data-add-product]').forEach((button) => {
        button.addEventListener('click', () => addProduct(button.dataset.addProduct));
    });

    cartItems.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-action]');
        if (!button) return;

        const id = button.dataset.productId;
        const quantity = cart.get(id);
        if (!quantity) return;

        if (button.dataset.action === 'increase') {
            cart.set(id, Math.min(quantity + 1, 999));
        } else if (button.dataset.action === 'decrease' && quantity > 1) {
            cart.set(id, quantity - 1);
        } else if (button.dataset.action === 'remove') {
            cart.delete(id);
            showFeedback(`${products.get(id).name} removed.`, 'neutral');
        }

        setPaymentError();
        renderCart();
    });

    clearOrder.addEventListener('click', () => {
        cart.clear();
        renderCart();
        showFeedback('The order was cleared.', 'neutral');
    });

    paymentInput.addEventListener('input', () => setPaymentError());

    paymentForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setPaymentError();

        if (paymentInput.value.trim() === '' || Number(paymentInput.value) < 0 || !paymentInput.validity.valid) {
            setPaymentError('Please enter a valid payment amount.');
            return;
        }

        payButton.disabled = true;
        payButton.textContent = 'Processing…';

        try {
            const response = await fetch('api/checkout.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf_token: app.csrfToken,
                    cart: Array.from(cart, ([id, quantity]) => ({id, quantity})),
                    payment: paymentInput.value,
                }),
            });
            const result = await response.json();

            if (!response.ok || !result.ok) {
                setPaymentError(result.message || 'Payment could not be completed.');
                return;
            }

            showReceipt(result.transaction);
        } catch (error) {
            setPaymentError('Payment could not be completed. Check the server and try again.');
        } finally {
            payButton.disabled = cart.size === 0;
            payButton.textContent = 'Complete payment';
        }
    });

    function showReceipt(transaction) {
        document.querySelector('#receipt-reference').textContent = `Transaction ${transaction.reference}`;
        const receiptItems = document.querySelector('#receipt-items');
        receiptItems.replaceChildren();

        transaction.items.forEach((item) => {
            const row = document.createElement('div');
            const description = document.createElement('span');
            description.textContent = `${item.quantity} × ${item.name}`;
            const amount = document.createElement('strong');
            amount.textContent = peso(item.subtotal_cents);
            row.append(description, amount);
            receiptItems.append(row);
        });

        document.querySelector('#receipt-total').textContent = peso(transaction.total_cents);
        document.querySelector('#receipt-paid').textContent = peso(transaction.paid_cents);
        document.querySelector('#receipt-change').textContent = peso(transaction.change_cents);
        receiptDialog.showModal();
    }

    document.querySelector('#new-transaction').addEventListener('click', () => {
        cart.clear();
        paymentInput.value = '';
        renderCart();
        receiptDialog.close();
        showFeedback('New transaction ready.');
        document.querySelector('[data-add-product]').focus();
    });

    receiptDialog.addEventListener('cancel', (event) => event.preventDefault());
    renderCart();
})();
