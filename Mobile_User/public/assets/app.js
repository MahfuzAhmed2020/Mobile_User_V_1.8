(() => {
    const stickyHeader = document.querySelector('.site-header');
    if (stickyHeader) {
        const syncHeaderHeight = () => document.documentElement.style.setProperty('--header-height', stickyHeader.getBoundingClientRect().height + 'px');
        syncHeaderHeight();
        if (window.ResizeObserver) new ResizeObserver(syncHeaderHeight).observe(stickyHeader);
        else window.addEventListener('resize', syncHeaderHeight);
    }
    const money = value => new Intl.NumberFormat('en-US', {style:'currency',currency:'USD'}).format(Number(value));
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let toastTimer;
    function toast(message, error = false) {
        const notice = document.getElementById('globalNotice');
        if (!notice) return;
        notice.textContent = message; notice.className = 'toast' + (error ? ' error' : ''); notice.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(() => {
    const stickyHeader = document.querySelector('.site-header');
    if (stickyHeader) {
        const syncHeaderHeight = () => document.documentElement.style.setProperty('--header-height', stickyHeader.getBoundingClientRect().height + 'px');
        syncHeaderHeight();
        if (window.ResizeObserver) new ResizeObserver(syncHeaderHeight).observe(stickyHeader);
        else window.addEventListener('resize', syncHeaderHeight);
    } notice.hidden = true; }, 5000);
    }
    async function request(endpoint, {method='GET',body} = {}) {
        let response;
        try {
            response = await fetch('../api/' + endpoint, {method,credentials:'same-origin',headers:{'Content-Type':'application/json'},...(body ? {body:JSON.stringify(body)} : {})});
        } catch (_) { throw new Error('We could not connect. Check your connection and try again.'); }
        if (response.status === 401) {
            location.href = 'login.php?redirect=' + encodeURIComponent(location.pathname + location.search);
            throw new Error('Your session has expired. Please sign in again.');
        }
        let data;
        try { data = await response.json(); } catch (_) { throw new Error('We could not complete your request. Please try again.'); }
        if (!response.ok || !data.success) {
            const error = new Error(data.message || data.errors?.join(' ') || 'Unable to complete your request.');
            error.status = response.status;
            throw error;
        }
        return data;
    }
    function updateCart(count) { document.querySelectorAll('[data-cart-count]').forEach(element => { element.textContent = count > 99 ? '99+' : count; element.setAttribute('aria-label', count + ' items in cart'); }); }
    async function refreshCart() { try { const cart = await request('cart_api.php'); updateCart(cart.item_count); return cart; } catch (_) { return null; } }
    function confirmAction({title,message,label='Confirm',cancelLabel='Cancel',danger=false}) {
        const dialog = document.getElementById('confirmDialog');
        dialog.querySelector('#confirmTitle').textContent = title;
        dialog.querySelector('#confirmDescription').textContent = message;
        const confirm = dialog.querySelector('[data-dialog-confirm]');
        confirm.textContent = label; confirm.className = 'button' + (danger ? ' danger' : '');
        dialog.querySelector('[data-dialog-cancel]').textContent = cancelLabel;
        return new Promise(resolve => {
            dialog.returnValue = '';
            dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm'), {once:true});
            dialog.showModal();
        });
    }
    const dialog = document.getElementById('confirmDialog');
    if (dialog) {
        dialog.querySelector('[data-dialog-cancel]').addEventListener('click', () => dialog.close('cancel'));
        dialog.querySelector('[data-dialog-confirm]').addEventListener('click', () => dialog.close('confirm'));
    }
    document.querySelectorAll('[data-signout]').forEach(button => button.addEventListener('click', async () => {
        const confirmed = await confirmAction({title:'Sign out of your account?',message:"Your cart and orders will be saved. You can sign in again whenever you're ready.",label:'Sign out',cancelLabel:'Stay signed in'});
        if (!confirmed) return;
        button.disabled = true; button.textContent = 'Signing out…';
        try { await request('logout_api.php',{method:'POST'}); location.href = 'signed_out.php'; }
        catch (error) { toast(error.message,true); button.disabled = false; button.textContent = 'Sign out'; }
    }));
    async function shareProduct(data) {
        if (navigator.share) {
            try { await navigator.share(data); return true; }
            catch (error) { if (error.name === 'AbortError') return false; }
        }
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(data.url);
            toast('Product link copied. Share it with anyone.');
            return true;
        } catch (_) {
            const shareDialog = document.getElementById('shareDialog');
            if (!shareDialog) { toast('Could not copy the product link. Please try again.',true); return false; }
            document.getElementById('shareTitle').textContent = 'Share ' + data.title;
            document.getElementById('shareLink').value = data.url;
            document.getElementById('shareFeedback').textContent = 'Copy this link to share the product.';
            if (!shareDialog.open) shareDialog.showModal();
            document.getElementById('shareLink').select();
            return false;
        }
    }
    const shareDialog = document.getElementById('shareDialog');
    if (shareDialog) {
        document.getElementById('closeShare').addEventListener('click', () => shareDialog.close());
        document.getElementById('copyShare').addEventListener('click', async () => {
            const input = document.getElementById('shareLink');
            try {
                await navigator.clipboard.writeText(input.value);
                document.getElementById('shareFeedback').textContent = 'Product link copied.';
            } catch (_) {
                input.focus(); input.select();
                document.getElementById('shareFeedback').textContent = 'Select and copy the link above using your device’s copy command.';
            }
        });
    }
    window.StoreUI = {money,escape,toast,request,updateCart,refreshCart,confirm:confirmAction,share:shareProduct};
})();
