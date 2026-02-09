<?php
require_once '../config/session.php';
require_login();
?>
<!DOCTYPE html>
<html>
<head>
<title>My Cart</title>
<style>
table { width:60%; margin:auto; border-collapse:collapse;}
th, td { border:1px solid #ccc; padding:8px; text-align:center;}
.total { font-weight:bold; text-align:right; }
button { padding:2px 6px; }
</style>
</head>

<body>
<h2 align="center">My Cart</h2>

<table id="cartTable">
    <thead>
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Subtotal</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody></tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total" id="cartTotal">$0.00</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<script>
async function loadCart() {
    const res = await fetch('../api/cart_api.php');
    const result = await res.json();

    if (!result.success) {
        alert(result.message);
        return;
    }

    const tbody = document.querySelector('#cartTable tbody');
    tbody.innerHTML = '';

    result.data.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${item.name}</td>
            <td>$${item.price}</td>
            <td>${item.quantity}</td>
            <td>$${(item.price * item.quantity).toFixed(2)}</td>
            <td>
                <button onclick="updateQty(${item.product_id}, 1)">+</button>
                <button onclick="updateQty(${item.product_id}, -1)">−</button>
                <button onclick="removeItem(${item.product_id})">Remove</button>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('cartTotal').innerText = '$' + result.total;
}

async function updateQty(product_id, change) {
    await fetch('../api/cart_api.php?PHPSESSID=<?php echo session_id(); ?>', {
        method: 'PATCH',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id, change})
    });
    loadCart();
}

async function removeItem(product_id) {
    await fetch('../api/cart_api.php?PHPSESSID=<?php echo session_id(); ?>', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id})
    });
    loadCart();
}

loadCart();
</script>
</body>
</html>
