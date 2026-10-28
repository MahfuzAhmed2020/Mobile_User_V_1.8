(() => {
    const {money,escape,request,toast,updateCart} = StoreUI;
    const page = document.body.dataset.page;
    let cart = null, products = [], addresses = [], submitting = false;
    const icons = {
        earbuds:'<path d="M22 27c-8-12-25-7-25 7 0 10 7 16 15 16h4V27Z" transform="translate(28 0)"/><path d="M50 45v26c0 8-10 8-10 0V49M78 27c8-12 25-7 25 7 0 10-7 16-15 16h-4V27Z" transform="translate(-6 0)"/><path d="M72 45v26c0 8 10 8 10 0V49"/><path d="M35 83h42c7 0 12 5 12 12H23c0-7 5-12 12-12Z"/>',
        speaker:'<rect x="27" y="18" width="60" height="80" rx="18"/><circle cx="57" cy="62" r="20"/><circle cx="57" cy="62" r="12"/><path d="M46 30h22M52 30v-4M62 30v-4"/>',
        phone:'<rect x="32" y="9" width="53" height="96" rx="12" transform="rotate(9 58 58)"/><rect x="39" y="19" width="20" height="23" rx="5" transform="rotate(9 49 30)"/><circle cx="44" cy="26" r="3"/><circle cx="53" cy="35" r="3"/><path d="M48 92h16"/>',
        cable:'<path d="M32 20v22c0 12 50 7 50 30 0 12-14 18-28 18H36"/><rect x="23" y="8" width="19" height="22" rx="4"/><path d="M29 8V3M36 8V3"/><rect x="16" y="80" width="24" height="16" rx="4"/><path d="M16 85H9v7h7"/>',
        watch:'<path d="M44 26l4-20h24l4 20M44 87l4 22h24l4-22"/><rect x="32" y="25" width="56" height="64" rx="16"/><rect x="40" y="33" width="40" height="48" rx="10"/><path d="M47 59h8l4-12 6 22 5-10h5"/>',
        powerbank:'<rect x="30" y="13" width="60" height="94" rx="12"/><path d="M44 21h12M66 21h10M64 43 48 65h12l-4 19 18-25H61Z"/><circle cx="43" cy="96" r="1"/><circle cx="53" cy="96" r="1"/><circle cx="63" cy="96" r="1"/><circle cx="73" cy="96" r="1"/>',
        chargingpad:'<ellipse cx="58" cy="63" rx="42" ry="25"/><path d="M16 63v7c0 14 19 25 42 25s42-11 42-25v-7"/><ellipse cx="58" cy="62" rx="32" ry="17"/><path d="m62 48-13 16h10l-4 12 15-17H59M99 66h8c9 0 9-23 0-23h-5"/>',
        wallcharger:'<rect x="33" y="38" width="57" height="60" rx="11"/><path d="M44 38V19h7v19M72 38V19h7v19"/><rect x="47" y="53" width="29" height="9" rx="4"/><path d="m61 72-6 8h6l-2 8 9-12h-6"/>',
        phonestand:'<rect x="36" y="11" width="48" height="74" rx="7"/><path d="M43 21h34v51H43ZM54 78h12M50 85l-8 17h43M69 85l11 17M30 104h62"/>',
        protector:'<rect x="26" y="23" width="48" height="83" rx="8"/><path d="M42 30h16M44 99h12"/><rect x="45" y="12" width="48" height="83" rx="7" fill="#ffffff" fill-opacity=".45"/><path d="m52 77 30-39M58 81l24-31M59 19h17M97 16h12M103 10v12M93 32h8M97 28v8"/>',
        mouse:'<rect x="33" y="15" width="55" height="91" rx="27"/><path d="M33 48h55M60 15v17"/><rect x="56" y="29" width="8" height="16" rx="4"/><path d="M42 81c0 10 7 17 13 19"/>',
        keyboard:'<rect x="9" y="32" width="102" height="58" rx="8"/><path d="M17 43h8v8h-8ZM32 43h8v8h-8ZM47 43h8v8h-8ZM62 43h8v8h-8ZM77 43h8v8h-8ZM92 43h10v8H92ZM17 58h8v8h-8ZM32 58h8v8h-8ZM47 58h8v8h-8ZM62 58h8v8h-8ZM77 58h8v8h-8ZM92 58h10v8H92ZM17 73h15v8H17ZM39 73h43v8H39ZM89 73h13v8H89M24 32v-8h15"/>',
        hub:'<rect x="18" y="44" width="83" height="36" rx="9"/><path d="M31 44V30c0-12 24-12 24-25M55 5h13v10H55ZM27 55h17v11H27ZM52 55h17v11H52ZM78 57h14v7H78ZM101 55h10v14h-10"/><circle cx="29" cy="73" r="1"/>',
        laptopstand:'<rect x="23" y="16" width="76" height="52" rx="5"/><path d="M30 23h62v37H30ZM23 68l-12 12h100L99 68M49 73h24M48 80l-9 22M75 80l9 22M30 104h63"/>',
        sleeve:'<rect x="15" y="27" width="90" height="65" rx="10"/><path d="M24 36h72M24 40h72"/><rect x="82" y="34" width="9" height="17" rx="3"/><path d="M23 50v30c0 3 2 5 5 5h63c3 0 6-2 6-5V50" stroke-dasharray="3 4"/><path d="M44 58h33v14H44Z"/>',
        bag:'<path d="M30 40h57l6 58H24l6-58Z"/><path d="M43 41V28a15 15 0 0 1 30 0v13"/>'
    };
    function art(name, small = false) {
        const lower = String(name).toLowerCase();
        const styles = [
            ['power bank','powerbank','power','Power & charging'],
            ['wireless charging','chargingpad','power','Power & charging'],
            ['wall charger','wallcharger','power','Power & charging'],
            ['phone stand','phonestand','stand','Desk essentials'],
            ['screen protector','protector','protection','Device protection'],
            ['mouse','mouse','desk','Workspace essentials'],
            ['keyboard','keyboard','desk','Workspace essentials'],
            ['multiport hub','hub','desk','Workspace essentials'],
            ['laptop stand','laptopstand','stand','Workspace essentials'],
            ['laptop sleeve','sleeve','protection','Device protection'],
            ['earbuds','earbuds','audio','Audio essentials'],
            ['speaker','speaker','audio','Audio essentials'],
            ['phone case','phone','case','Everyday accessories'],
            ['cable','cable','cable','Power & charging'],
            ['fitness','watch','fitness','Daily wellness'],
        ];
        const [,key,color,category] = styles.find(([term]) => lower.includes(term)) || ['', 'bag', 'audio', 'Everyday accessories'];
        return {category,html:'<div class="product-art art-' + color + (small ? ' cart-art' : '') + '" aria-hidden="true"><svg viewBox="0 0 120 120">' + icons[key] + '</svg></div>'};
    }
    window.StoreArt = art;
    function notice(message, error = false) {
        const element = document.getElementById('pageNotice');
        element.textContent = message; element.className = 'notice' + (error ? ' error' : ''); element.hidden = false;
    }
    function empty(target, title, message) {
        target.hidden = false;
        target.innerHTML = '<section class="empty"><div class="empty-icon" aria-hidden="true">⌑</div><h2>' + escape(title) + '</h2><p>' + escape(message) + '</p><a class="button" href="profile.php">Explore the collection →</a></section>';
    }
    function renderProducts() {
        const term = document.getElementById('productSearch').value.trim().toLowerCase();
        const sort = document.getElementById('productSort').value;
        const visible = products.filter(product => (product.name + ' ' + (product.description || '')).toLowerCase().includes(term));
        if (sort === 'price-asc') visible.sort((a,b) => Number(a.price)-Number(b.price));
        if (sort === 'price-desc') visible.sort((a,b) => Number(b.price)-Number(a.price));
        if (sort === 'name') visible.sort((a,b) => a.name.localeCompare(b.name));
        document.getElementById('productCount').textContent = visible.length + (visible.length === 1 ? ' essential' : ' essentials') + (term ? ' found' : ' in the collection');
        const target = document.getElementById('products');
        target.innerHTML = visible.length ? visible.map(product => {
            const visual = art(product.name);
            return '<article class="product-card"><div class="product-media"><a class="product-image-link" href="product.php?id=' + Number(product.id) + '" aria-label="View ' + escape(product.name) + '">' + visual.html + '</a><button class="product-share" type="button" data-share="' + Number(product.id) + '" aria-label="Share ' + escape(product.name) + '">↗ Share</button></div><div class="product-info"><p class="product-category">' + visual.category + '</p><h2><a href="product.php?id=' + Number(product.id) + '">' + escape(product.name) + '</a></h2><p class="description">' + escape(product.description || 'A useful addition to your everyday routine.') + '</p><div class="product-bottom"><span class="price">' + money(product.price) + '</span><button class="button small" data-add="' + Number(product.id) + '">Add to cart <span aria-hidden="true">+</span></button></div></div></article>';
        }).join('') : '<div class="empty" style="grid-column:1/-1"><h2>No matches found</h2><p>Try a different search to find what you’re looking for.</p></div>';
    }
    function renderTotals() {
        updateCart(cart.item_count);
        ['cartTotal','cartSubtotal','checkoutTotal'].forEach(id => { const element = document.getElementById(id); if (element) element.textContent = money(cart.total); });
        const count = document.getElementById('cartCount'); if (count) count.textContent = cart.item_count;
    }
    function renderCart() {
        renderTotals();
        const noItems = cart.data.length === 0;
        document.getElementById('cartLayout').hidden = noItems;
        document.getElementById('cartEmpty').hidden = !noItems;
        if (noItems) { empty(document.getElementById('cartEmpty'),'Your cart is waiting.','Find something you love and add it here. We’ll keep it ready for checkout.'); return; }
        document.getElementById('cart').innerHTML = cart.data.map(item =>
            '<article class="cart-row">' + art(item.name,true).html + '<div><h2>' + escape(item.name) + '</h2><span class="unit-price">' + money(item.price) + ' each</span></div><div class="cart-row-end"><div class="line-total">' + money(item.subtotal) + '</div><div class="quantity-control" aria-label="Quantity for ' + escape(item.name) + '"><button type="button" data-quantity="' + (Number(item.quantity)-1) + '" data-product="' + Number(item.product_id) + '" aria-label="Decrease quantity of ' + escape(item.name) + '">−</button><output aria-live="polite">' + Number(item.quantity) + '</output><button type="button" data-quantity="' + (Number(item.quantity)+1) + '" data-product="' + Number(item.product_id) + '" aria-label="Increase quantity of ' + escape(item.name) + '"' + (Number(item.quantity) >= 999 ? ' disabled' : '') + '>+</button></div><button type="button" class="remove-item" data-remove="' + Number(item.product_id) + '">Remove</button></div></article>'
        ).join('');
    }
    function renderCheckoutCart() {
        renderTotals();
        document.getElementById('checkoutForm').hidden = !cart.data.length;
        document.getElementById('checkoutEmpty').hidden = !!cart.data.length;
        if (!cart.data.length) { empty(document.getElementById('checkoutEmpty'),'Nothing to check out yet.','Add a few essentials to your cart, then come back to complete your order.'); return; }
        document.getElementById('reviewItems').innerHTML = cart.data.map(item => '<li><span>' + escape(item.name) + '<small>Qty ' + Number(item.quantity) + ' · ' + money(item.price) + ' each</small></span><strong>' + money(item.subtotal) + '</strong></li>').join('');
        updateSelections();
    }
    function updateSelections() {
        const selected = addresses.find(address => String(address.id) === document.getElementById('address').value);
        const preview = document.getElementById('addressPreview');
        preview.hidden = !selected;
        if (selected) preview.querySelector('span').textContent = [selected.address_line,selected.city + ', ' + selected.state + ' ' + selected.zip,selected.country].join('\n');
        const number = document.getElementById('card').value;
        document.getElementById('cardPreview').hidden = !number;
        document.getElementById('cardEnding').textContent = number ? 'Card ending in ' + number.slice(-4) : '';
        const ready = selected && number && cart?.data.length;
        document.getElementById('Checkout').disabled = !ready || submitting;
        document.getElementById('checkoutHint').hidden = !!ready;
    }
    async function loadCheckout() {
        const [cartData,cardData,addressData] = await Promise.all([request('cart_api.php'),request('cards_api.php'),request('addresses_api.php')]);
        cart = cartData; addresses = addressData.data;
        const addressSelect = document.getElementById('address');
        addressSelect.replaceChildren(new Option(addresses.length ? 'Select a delivery address' : 'No saved addresses available',''));
        addresses.forEach(address => addressSelect.add(new Option([address.address_line,address.city,address.state,address.zip,address.country].join(', '),address.id)));
        const cardSelect = document.getElementById('card');
        cardSelect.replaceChildren(new Option(cardData.data.length ? 'Select a payment card' : 'No saved payment cards available',''));
        cardData.data.forEach(card => cardSelect.add(new Option(card.card_number + ' (test card)',card.card_number)));
        addressSelect.disabled = !addresses.length; cardSelect.disabled = !cardData.data.length;
        renderCheckoutCart();
    }
    if (page === 'shop') {
        document.getElementById('productSearch').addEventListener('input',renderProducts);
        document.getElementById('productSort').addEventListener('change',renderProducts);
        document.getElementById('products').addEventListener('click',async event => {
            const share = event.target.closest('[data-share]');
            if (share) {
                const product = products.find(item => Number(item.id) === Number(share.dataset.share));
                if (product) await StoreUI.share({title:product.name,text:'Take a look at ' + product.name + ' from Mobile User.',url:new URL('product.php?id=' + product.id,location.href).href});
                return;
            }
            const button = event.target.closest('[data-add]'); if (!button) return;
            button.disabled = true; button.textContent = 'Adding…';
            try {
                const result = await request('cart_api.php',{method:'POST',body:{product_id:Number(button.dataset.add)}});
                updateCart(result.item_count); toast('Added to your cart.');
            } catch (error) { toast(error.message,true); }
            finally { button.disabled = false; button.innerHTML = 'Add to cart <span aria-hidden="true">+</span>'; }
        });
        request('products_api.php').then(result => { products = result.data; renderProducts(); }).catch(error => { document.getElementById('products').innerHTML = ''; document.getElementById('productCount').textContent = 'Collection unavailable'; notice(error.message,true); });
        StoreUI.refreshCart();
    }
    if (page === 'cart') {
        let updating = false;
        document.getElementById('cart').addEventListener('click',async event => {
            const button = event.target.closest('[data-quantity],[data-remove]'); if (!button || updating) return;
            updating = true;
            const controls = document.querySelectorAll('#cart button');
            controls.forEach(element => { element.disabled = true; });
            const remove = button.hasAttribute('data-remove');
            try {
                cart = await request('cart_api.php',{method:remove ? 'DELETE' : 'PATCH',body:remove ? {product_id:Number(button.dataset.remove)} : {product_id:Number(button.dataset.product),quantity:Number(button.dataset.quantity)}});
                renderCart();
            } catch (error) { notice(error.message,true); controls.forEach(element => { element.disabled = false; }); }
            finally { updating = false; }
        });
        request('cart_api.php').then(result => { cart = result; renderCart(); }).catch(error => { notice(error.message,true); document.getElementById('cartLayout').hidden = true; });
    }
    if (page === 'checkout') {
        document.getElementById('address').addEventListener('change',updateSelections);
        document.getElementById('card').addEventListener('change',updateSelections);
        document.getElementById('checkoutForm').addEventListener('submit',async event => {
            event.preventDefault();
            if (submitting || !event.target.reportValidity()) return;
            submitting = true;
            const button = document.getElementById('Checkout');
            button.disabled = true; button.textContent = 'Placing your order…';
            document.getElementById('address').disabled = true; document.getElementById('card').disabled = true;
            try {
                const result = await request('checkout_api.php',{method:'POST',body:{address_id:Number(document.getElementById('address').value),card_number:document.getElementById('card').value,expected_total:cart.total}});
                updateCart(0);
                document.getElementById('checkoutContent').hidden = true;
                const success = document.getElementById('orderSuccess');
                success.hidden = false; success.setAttribute('tabindex','-1');
                success.innerHTML = '<div class="success-icon" aria-hidden="true">✓</div><p class="eyebrow">ORDER CONFIRMED</p><h1>Thank you for your order.</h1><p class="muted">Order #' + escape(result.order_id) + ' is now being processed.</p><p class="small-text muted">' + (result.email_sent ? 'Your order confirmation has been sent to your email address.' : 'Your order is confirmed. You can view the details and tracking in My orders.') + '</p><div class="tracking-box"><strong>YOUR TRACKING NUMBER</strong><span>' + escape(result.tracking_number) + '</span></div><div class="success-actions"><a class="button" href="orders.php?tracking=' + encodeURIComponent(result.tracking_number) + '">View your order →</a><a class="button secondary" href="profile.php">Continue shopping</a></div>';
                success.focus();
            } catch (error) {
                if (error.status === 409) { try { cart = await request('cart_api.php'); renderCheckoutCart(); } catch (_) {} }
                notice(error.message,true);
            } finally { submitting = false; button.textContent = 'Place order'; if (!document.getElementById('checkoutContent').hidden) { document.getElementById('address').disabled = !addresses.length; document.getElementById('card').disabled = document.getElementById('card').options.length < 2; updateSelections(); } }
        });
        loadCheckout().catch(error => notice(error.message,true));
    }
})();
