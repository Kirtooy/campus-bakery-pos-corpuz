(() => {
    'use strict';

    const app = window.CAMPUS_BAKERY;
    const products = new Map(app.products.map((product) => [product.id, product]));
    const cart = new Map();

    const productGrid = document.querySelector('#product-grid');
    const productCount = document.querySelector('#product-count');
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
    const transactionsDialog = document.querySelector('#transactions-dialog');
    const transactionsList = document.querySelector('#transactions-list');
    const productsDialog = document.querySelector('#products-dialog');
    const productAdminList = document.querySelector('#product-admin-list');
    const productForm = document.querySelector('#product-form');
    const productFormTitle = document.querySelector('#product-form-title');
    const productFormError = document.querySelector('#product-form-error');
    const cancelProductEdit = document.querySelector('#cancel-product-edit');
    const saveProduct = document.querySelector('#save-product');

    function peso(cents) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
        }).format(cents / 100).replace('PHP', '₱');
    }

    function initials(name) {
        return name.trim().split(/\s+/).slice(0, 2).map((word) => word[0] || '').join('').toUpperCase() || 'CB';
    }

    function renderCatalog() {
        productGrid.replaceChildren();
        products.forEach((product) => {
            const card = document.createElement('article');
            card.className = 'product-card';

            const visual = document.createElement('div');
            visual.className = 'product-visual';
            visual.setAttribute('aria-hidden', 'true');
            const visualText = document.createElement('span');
            visualText.textContent = initials(product.name);
            visual.append(visualText);

            const copy = document.createElement('div');
            copy.className = 'product-copy';
            const name = document.createElement('h2');
            name.textContent = product.name;
            const description = document.createElement('p');
            description.textContent = product.description;
            copy.append(name, description);

            const action = document.createElement('div');
            action.className = 'product-action';
            const price = document.createElement('strong');
            price.textContent = peso(product.price_cents);
            const add = document.createElement('button');
            add.className = 'add-button';
            add.type = 'button';
            add.dataset.addProduct = product.id;
            add.setAttribute('aria-label', `Add ${product.name} to order`);
            add.textContent = '+ Add';
            action.append(price, add);

            card.append(visual, copy, action);
            productGrid.append(card);
        });

        productCount.textContent = `${products.size} ${products.size === 1 ? 'product' : 'products'}`;
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

    productGrid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-add-product]');
        if (button) addProduct(button.dataset.addProduct);
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

    function applyProducts(updatedProducts) {
        const validIds = new Set(updatedProducts.map((product) => product.id));
        products.clear();
        updatedProducts.forEach((product) => products.set(product.id, product));
        Array.from(cart.keys()).forEach((id) => {
            if (!validIds.has(id)) cart.delete(id);
        });
        renderCatalog();
        renderCart();
        renderProductAdmin();
    }

    function showDialogFeedback(targetId, message = '', type = 'success') {
        const target = document.querySelector(`#${targetId}`);
        target.textContent = message;
        target.className = message ? `dialog-feedback dialog-feedback--${type}` : 'dialog-feedback';
    }

    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => document.querySelector(`#${button.dataset.closeDialog}`).close());
    });

    document.querySelector('#view-transactions').addEventListener('click', async () => {
        transactionsList.replaceChildren();
        showDialogFeedback('transactions-feedback', 'Loading transactions…', 'neutral');
        transactionsDialog.showModal();

        try {
            const response = await fetch('api/transactions.php', {headers: {'Accept': 'application/json'}});
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.message || 'Transactions could not be loaded.');
            showDialogFeedback('transactions-feedback');
            renderTransactions(result.transactions);
        } catch (error) {
            showDialogFeedback('transactions-feedback', error.message || 'Transactions could not be loaded.', 'error');
        }
    });

    function renderTransactions(transactions) {
        transactionsList.replaceChildren();
        if (transactions.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'manager-empty';
            empty.innerHTML = '<strong>No completed sales yet</strong><span>Transactions will appear here after payment.</span>';
            transactionsList.append(empty);
            return;
        }

        transactions.forEach((transaction) => {
            const card = document.createElement('article');
            card.className = 'transaction-card';
            const top = document.createElement('div');
            top.className = 'transaction-top';
            const identity = document.createElement('div');
            const reference = document.createElement('strong');
            reference.textContent = transaction.reference;
            const date = document.createElement('time');
            date.dateTime = transaction.created_at;
            date.textContent = new Intl.DateTimeFormat('en-PH', {
                dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Manila',
            }).format(new Date(transaction.created_at));
            identity.append(reference, date);
            const total = document.createElement('b');
            total.textContent = peso(transaction.total_cents);
            top.append(identity, total);

            const items = document.createElement('p');
            items.className = 'transaction-items';
            items.textContent = transaction.items.map((item) => `${item.quantity} × ${item.name}`).join(', ');

            const payment = document.createElement('div');
            payment.className = 'transaction-payment';
            payment.innerHTML = `<span>Cash ${peso(transaction.paid_cents)}</span><span>Change ${peso(transaction.change_cents)}</span>`;
            card.append(top, items, payment);
            transactionsList.append(card);
        });
    }

    document.querySelector('#manage-products').addEventListener('click', () => {
        resetProductForm();
        renderProductAdmin();
        showDialogFeedback('products-feedback');
        productsDialog.showModal();
    });

    function renderProductAdmin() {
        productAdminList.replaceChildren();
        document.querySelector('#manager-product-count').textContent = `${products.size} items`;
        products.forEach((product) => {
            const row = document.createElement('article');
            row.className = 'product-admin-row';
            const copy = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = product.name;
            const price = document.createElement('span');
            price.textContent = peso(product.price_cents);
            copy.append(name, price);

            const actions = document.createElement('div');
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'small-button';
            edit.dataset.editProduct = product.id;
            edit.textContent = 'Edit';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'small-button small-button--danger';
            remove.dataset.deleteProduct = product.id;
            remove.textContent = 'Remove';
            actions.append(edit, remove);
            row.append(copy, actions);
            productAdminList.append(row);
        });
    }

    function resetProductForm() {
        productForm.reset();
        document.querySelector('#product-id').value = '';
        productFormTitle.textContent = 'Add product';
        saveProduct.textContent = 'Add product';
        cancelProductEdit.hidden = true;
        productFormError.textContent = '';
    }

    productAdminList.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-edit-product]');
        if (editButton) {
            const product = products.get(editButton.dataset.editProduct);
            document.querySelector('#product-id').value = product.id;
            document.querySelector('#product-name').value = product.name;
            document.querySelector('#product-description').value = product.description;
            document.querySelector('#product-price').value = (product.price_cents / 100).toFixed(2);
            productFormTitle.textContent = 'Edit product';
            saveProduct.textContent = 'Save changes';
            cancelProductEdit.hidden = false;
            productFormError.textContent = '';
            document.querySelector('#product-name').focus();
            return;
        }

        const deleteButton = event.target.closest('[data-delete-product]');
        if (!deleteButton) return;
        const product = products.get(deleteButton.dataset.deleteProduct);
        if (!window.confirm(`Remove ${product.name} from the catalog?`)) return;

        await changeProduct({action: 'delete', id: product.id});
    });

    cancelProductEdit.addEventListener('click', resetProductForm);

    productForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        productFormError.textContent = '';
        if (!productForm.checkValidity()) {
            productFormError.textContent = 'Complete all product fields with valid values.';
            productForm.reportValidity();
            return;
        }

        const id = document.querySelector('#product-id').value;
        await changeProduct({
            action: id ? 'update' : 'add',
            id,
            name: document.querySelector('#product-name').value,
            description: document.querySelector('#product-description').value,
            price: document.querySelector('#product-price').value,
        });
    });

    async function changeProduct(payload) {
        saveProduct.disabled = true;
        try {
            const response = await fetch('api/products.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({...payload, csrf_token: app.csrfToken}),
            });
            const result = await response.json();
            if (!response.ok || !result.ok) {
                productFormError.textContent = result.message || 'The product could not be updated.';
                return false;
            }
            applyProducts(result.products);
            resetProductForm();
            showDialogFeedback('products-feedback', result.message);
            showFeedback(result.message);
            return true;
        } catch (error) {
            productFormError.textContent = 'The product could not be updated. Check the server and try again.';
            return false;
        } finally {
            saveProduct.disabled = false;
        }
    }

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
    renderCatalog();
    renderProductAdmin();
    renderCart();
})();
