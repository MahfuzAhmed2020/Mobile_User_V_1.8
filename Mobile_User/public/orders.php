<?php
include '../config/session.php';
require_login();
?>
<!DOCTYPE html>
<html>
<head>
<title>My Orders</title>

<!-- ✅ REQUIRED -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ---------------- BASE ---------------- */
*{
    box-sizing:border-box;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
}

body{
    margin:0;
    background:#f4f6f8;
    padding:12px;
}

/* ---------------- CONTAINER ---------------- */
.container{
    max-width:1000px;
    margin:auto;
}

/* ---------------- PROFILE HEADER ---------------- */
.prf{
    display:inline-block;
    margin-bottom:12px;
    color:white;
    padding:10px 14px;
    background:#60a5fa;
    border-radius:8px;
    font-size:16px;
}

/* ---------------- TOP HEADER ---------------- */
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.header h1{
    margin:0;
}

/* ---------------- BUTTON ---------------- */
.btn{
    padding:10px 14px;
    background:#667eea;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
}

.btn:hover{
    background:#5a67d8;
}

/* ---------------- ORDER CARD ---------------- */
.order{
    background:white;
    border-radius:12px;
    padding:16px;
    margin-bottom:18px;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}

/* ---------------- LINKS ---------------- */
.link{
    color:#667eea;
    cursor:pointer;
    text-decoration:underline;
    display:inline-block;
    margin:10px 0;
}

/* ---------------- ADDRESS BOX ---------------- */
.box{
    margin-top:10px;
}

select{
    width:100%;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
    font-size:14px;
}

/* ---------------- LIST ---------------- */
ul{
    padding-left:18px;
}

/* ---------------- MOBILE ---------------- */
@media (max-width: 600px){
    .header{
        flex-direction:column;
        align-items:flex-start;
    }

    .btn{
        width:100%;
    }

    .prf{
        width:100%;
        text-align:center;
    }
}
</style>
</head>

<body>

<div class="container">

<h1 class="prf">
User Name: <?php echo htmlspecialchars($_SESSION['first_name'].' '.$_SESSION['last_name']); ?>
</h1>

<div class="header">
    <h1>My Orders</h1>
    
</div>

<div id="orders"></div>

<a href="/Mobile_User/public/profile.php" class="btn">Back</a>
<div>
<div>    

<button class="btn" onclick="logout()">Logout</button>
</div>

<script>
async function fetchOrders(){
    const res = await fetch('/Mobile_User/api/orders_api.php');
    const json = await res.json();
    const div = document.getElementById('orders');
    div.innerHTML = '';

    if(!json.success || json.data.length === 0){
        div.innerHTML = '<p>No orders found.</p>';
        return;
    }

    json.data.forEach(o => {

        let items = '';
        o.items.forEach(i=>{
            items += `<li>${i.name} x ${i.quantity} ($${i.price})</li>`;
        });

        let opts = '<option value="">Select new address</option>';
        o.addresses_set_b.forEach(b=>{
            opts += `<option value="${b.id}">
                ${b.address_line}, ${b.city}
            </option>`;
        });

        div.innerHTML += `
        <div class="order">
            <p><b>Order #</b> ${o.id}</p>
            <p><b>Tracking:</b> ${o.tracking_number}</p>
            <p><b>Status:</b> ${o.status || 'Processing'}</p>
            <p><b>Total:</b> $${o.total}</p>

            <p><b>Delivery Address:</b><br>${o.delivery_address}</p>

            <span class="link" onclick="toggle(${o.id})">
                Want to change/update the delivery address?
            </span>

            <div class="box" id="box-${o.id}" style="display:none">
                <select onchange="updateAddress(${o.id},this.value)">
                    ${opts}
                </select>
            </div>

            <ul>${items}</ul>

            <button class="btn" onclick="cancelOrder(${o.id})">
                Cancel Order
            </button>
        </div>`;
    });
}

function toggle(id){
    const box = document.getElementById('box-'+id);
    box.style.display = box.style.display==='none'?'block':'none';
}

async function updateAddress(orderId,addressId){
    if(!addressId) return;
    await fetch('/Mobile_User/api/orders_api.php',{
        method:'PATCH',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
            order_id:orderId,
            whitelist_address_id:addressId
        })
    });
    fetchOrders();
}

async function cancelOrder(id){
    if(!confirm('Cancel this order?')) return;
    await fetch('/Mobile_User/api/orders_api.php',{
        method:'DELETE',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({order_id:id})
    });
    fetchOrders();
}

async function logout(){
    const res = await fetch('/Mobile_User/api/logout_api.php',{method:'POST'});
    const data = await res.json();
    if(data.success){
        window.location.href = '/Mobile_User/public/login.php';
    }
}

fetchOrders();
</script>

</body>
</html>
