(() => {
    const {escape} = StoreUI;
    function render(target, order) {
        const number = order.carrier_tracking_number || order.tracking_number;
        const labelUrl = typeof order.label_url === 'string' && order.label_url.startsWith('https://') ? order.label_url : null;
        target.innerHTML = '<section class="shipment-details" aria-label="Delivery and tracking">' +
            '<h2>Delivery and tracking</h2><p class="shipment-status">' + escape(order.status || 'Processing') + '</p>' +
            '<p>' + escape(order.delivery_service || 'Standard delivery') + '</p>' +
            '<div class="tracking-box"><strong>' + (order.carrier_tracking_number ? 'CARRIER TRACKING NUMBER' : 'ORDER TRACKING REFERENCE') + '</strong><span>' + escape(number) + '</span>' +
            '<div class="barcode-wrap"><svg class="shipment-barcode" role="img" aria-label="Tracking barcode for ' + escape(number) + '"></svg></div></div>' +
            '<section class="delivery-label"><h3>' + (labelUrl ? 'Carrier delivery label' : 'Delivery label preview') + '</h3>' +
            (labelUrl ? '<a class="button secondary" href="' + escape(labelUrl) + '" target="_blank" rel="noopener noreferrer">Open / download label PDF</a><iframe class="label-preview" src="' + escape(labelUrl) + '" title="Carrier delivery label PDF" sandbox referrerpolicy="no-referrer"></iframe>' :
                '<div class="packing-label"><p class="label-disclaimer">DEMO DELIVERY LABEL — NOT VALID POSTAGE</p><p>ORDER #' + escape(order.id || order.order_id) + '</p><h3>SHIP TO</h3><p class="label-address">' + escape(order.delivery_address || 'See your saved order for the delivery address.') + '</p><p>' + escape(order.delivery_service || 'Standard delivery') + '</p><svg class="shipment-barcode" role="img" aria-label="Order reference barcode"></svg><p>' + escape(number) + '</p></div><p class="muted small-text">A carrier label and carrier tracking become available after fulfillment. This preview identifies your order.</p><button class="button secondary" type="button" data-print-label>Print delivery preview</button>') +
            '</section><p class="shipment-message small-text" role="status" aria-live="polite"></p></section>';
        if (number && window.JsBarcode) target.querySelectorAll('.shipment-barcode').forEach(svg => {
            try { JsBarcode(svg, String(number), {format:'CODE128', width:2, height:65, margin:16, displayValue:false}); }
            catch (_) { svg.replaceWith(document.createTextNode('Barcode unavailable. Use the tracking number above.')); }
        });
        target.querySelector('[data-print-label]')?.addEventListener('click', () => window.print());
    }
    window.ShipmentReceipt = {render};
})();
