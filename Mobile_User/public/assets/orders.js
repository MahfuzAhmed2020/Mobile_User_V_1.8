const ordersElement = document.getElementById('orders');
let orders = [];
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const addressText = address => [address.address_line, address.city, address.state, address.zip, address.country].filter(Boolean).join(', ');
const money = value => '$' + Number(value).toFixed(2);
function notice(message, error = false) {
    const element = document.getElementById('notice');
    element.textContent = message;
    element.className = 'notice' + (error ? ' error' : '');
    element.hidden = false;
}
async function api(method, payload) {
    const response = await fetch('../api/orders_api.php', {
        method, credentials: 'same-origin',
        headers: {'Content-Type':'application/json'},
        ...(payload ? {body: JSON.stringify(payload)} : {})
    });
    if (response.status === 401) { window.location.href = 'login.php?redirect=' + encodeURIComponent(location.pathname + location.search); throw new Error('Please sign in again.'); }
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Unable to complete your request.');
    return result;
}
function renderOrder(order) {
    const id = Number(order.id);
    const addresses = order.addresses_set_b || [];
    const editable = order.can_update_address && addresses.length > 0;
    const options = addresses.map(address => '<option value="' + Number(address.id) + '"' + (Number(order.whitelist_address_id) === Number(address.id) ? ' selected' : '') + '>' + escapeHtml(addressText(address)) + '</option>').join('');
    const date = order.created_at ? escapeHtml(order.created_at.slice(0,10)) : '';
    const items = order.items.map(item => '<li><span>' + escapeHtml(item.name) + ' <small>× ' + Number(item.quantity) + ' · ' + money(item.price) + ' each</small></span><strong>' + money(Number(item.price) * Number(item.quantity)) + '</strong></li>').join('');
    return '<article class="order" id="order-' + id + '">' +
        '<header class="order-header"><div><h2>Order #' + id + '</h2><p class="muted">Placed on ' + date + '</p></div><div class="order-meta"><span class="status">' + escapeHtml(order.status || 'Processing') + '</span><div class="order-total">' + money(order.total) + '</div></div></header>' +
        '<div class="order-content"><p class="tracking">Tracking number <code>' + escapeHtml(order.tracking_number) + '</code></p>' +
        '<section class="delivery-section" aria-labelledby="delivery-title-' + id + '"><div class="delivery-header"><div><h3 id="delivery-title-' + id + '">Delivery address</h3><p class="address">' + escapeHtml(order.delivery_address) + '</p></div>' +
        (editable ? '<button class="button secondary" data-action="edit" data-order="' + id + '" aria-expanded="false" aria-controls="editor-' + id + '">Change address</button>' : '') +
        '</div><p class="delivery-help">' + (editable ? 'You can update the delivery address while this order is processing.' : order.can_update_address ? 'No alternative saved addresses are available for this account.' : 'Address changes are unavailable once an order has left processing.') + '</p>' +
        (editable ? '<form class="address-editor" id="editor-' + id + '" data-order="' + id + '" hidden><h4>Update delivery address</h4><p class="muted">Choose a saved address, then review the delivery details before saving.</p><label for="address-' + id + '">Saved delivery address</label><select id="address-' + id + '" aria-describedby="address-error-' + id + '" required><option value="">Select a delivery address</option>' + options + '</select><div class="preview" id="preview-' + id + '" hidden><strong>DELIVER TO</strong><p></p></div><p class="form-error" id="address-error-' + id + '" role="alert" hidden></p><div class="actions"><button class="button" type="submit" disabled>Save changes</button><button class="button secondary" type="button" data-action="close" data-order="' + id + '">Cancel</button></div></form>' : '') +
        '</section><h3 class="items-title">Items in this order</h3><ul class="items">' + items + '</ul><footer class="order-footer"><button class="button danger" data-action="cancel-order" data-order="' + id + '">Cancel order</button></footer></div></article>';
}
async function fetchOrders() {
    try {
        const result = await api('GET');
        orders = result.data;
        const tracking = new URLSearchParams(location.search).get('tracking');
        const visible = tracking ? orders.filter(order => order.tracking_number === tracking) : orders;
        document.getElementById('all-orders').hidden = !tracking;
        ordersElement.innerHTML = visible.length ? visible.map(renderOrder).join('') : '<section class="empty"><h2>' + (tracking ? 'Order not found' : 'No orders yet') + '</h2><p>' + (tracking ? 'This tracking number does not match an active order in your account.' : 'Your purchases and delivery details will appear here after checkout.') + '</p><a class="button" href="profile.php">Continue shopping</a></section>';
    } catch (error) { notice(error.message, true); ordersElement.textContent = 'Orders could not be loaded.'; }
}
function setEditor(id, open) {
    const form = document.getElementById('editor-' + id);
    form.hidden = !open;
    const toggle = document.querySelector('[data-action="edit"][data-order="' + id + '"]');
    toggle.setAttribute('aria-expanded', String(open));
    if (open) { updatePreview(id); form.querySelector('select').focus(); }
    else { form.reset(); toggle.focus(); }
}
function updatePreview(id) {
    const order = orders.find(item => Number(item.id) === id);
    const form = document.getElementById('editor-' + id);
    const selected = Number(form.querySelector('select').value);
    const address = order.addresses_set_b.find(item => Number(item.id) === selected);
    const preview = document.getElementById('preview-' + id);
    preview.hidden = !address;
    if (address) preview.querySelector('p').textContent = addressText(address);
    form.querySelector('[type="submit"]').disabled = !address || selected === Number(order.whitelist_address_id);
    document.getElementById('address-error-' + id).hidden = true;
}
ordersElement.addEventListener('change', event => {
    if (event.target.matches('select')) updatePreview(Number(event.target.closest('form').dataset.order));
});
ordersElement.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const id = Number(button.dataset.order);
    if (button.dataset.action === 'edit') setEditor(id, document.getElementById('editor-' + id).hidden);
    if (button.dataset.action === 'close') setEditor(id, false);
    if (button.dataset.action === 'cancel-order') {
        const confirmed = window.StoreUI ? await StoreUI.confirm({title:'Cancel order #' + id + '?',message:'This will cancel your order and remove it from your active orders.',label:'Cancel order',cancelLabel:'Keep order',danger:true}) : confirm('Cancel this order?');
        if (!confirmed) return;
        button.disabled = true;
        try { await api('DELETE', {order_id:id}); await fetchOrders(); notice('Order #' + id + ' has been cancelled.'); }
        catch (error) { notice(error.message,true); button.disabled = false; }
    }
});
ordersElement.addEventListener('submit', async event => {
    if (!event.target.matches('form.address-editor')) return;
    event.preventDefault();
    const form = event.target;
    const id = Number(form.dataset.order);
    const button = form.querySelector('[type="submit"]');
    const cancel = form.querySelector('[data-action="close"]');
    const select = form.querySelector('select');
    const selectedId = Number(select.value);
    button.disabled = true; cancel.disabled = true; select.disabled = true; button.textContent = 'Saving…';
    try {
        const result = await api('PATCH', {order_id:id, whitelist_address_id:selectedId});
        await fetchOrders();
        const message = result.changed === false ? 'The delivery address is already up to date.' : 'Delivery address updated and saved for order #' + id + '.' + (result.email_sent ? ' A confirmation email has been sent.' : ' Your change is saved; an email confirmation could not be sent.');
        notice(message);
        if (window.StoreUI) StoreUI.toast(message);
        document.getElementById('notice').scrollIntoView({block:'nearest',behavior:'smooth'});
    } catch (error) {
        const message = document.getElementById('address-error-' + id);
        message.textContent = error.message; message.hidden = false;
        button.disabled = false; cancel.disabled = false; select.disabled = false; button.textContent = 'Save changes';
    }
});
if (window.StoreUI) StoreUI.refreshCart();
fetchOrders();
