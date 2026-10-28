(() => {
    const product = document.getElementById('productDetails');
    if (!product) return;
    document.getElementById('detailArt').innerHTML = StoreArt(product.dataset.name).html;
    document.getElementById('detailShare').addEventListener('click', () => StoreUI.share({title:product.dataset.name,text:'Take a look at ' + product.dataset.name + ' from Mobile User.',url:product.dataset.url}));
    const add = document.getElementById('detailAdd');
    if (!add) return;
    StoreUI.refreshCart();
    add.addEventListener('click',async () => {
        add.disabled = true; add.textContent = 'Adding…';
        try {
            const cart = await StoreUI.request('cart_api.php',{method:'POST',body:{product_id:Number(product.dataset.id)}});
            StoreUI.updateCart(cart.item_count); StoreUI.toast('Added to your cart.');
        } catch (error) { StoreUI.toast(error.message,true); }
        finally { add.disabled = false; add.textContent = 'Add to cart +'; }
    });
})();
