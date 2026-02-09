<?php
// Get token from URL
$token = $_GET['token'] ?? '';
if (!$token) {
    die("Invalid password reset link.");
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Reset Password</title>
<meta charset="UTF-8">
<style>
body{
    font-family: Arial, sans-serif;
    background:#f4f6f8;
}
.container{
    max-width:420px;
    margin:80px auto;
    padding:25px;
    background:white;
    border-radius:10px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);
}
h2{text-align:center}
input{
    width:100%;
    padding:10px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:5px;
}
.btn{
    width:100%;
    padding:10px;
    background:#667eea;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
.msg{
    margin-top:15px;
    padding:10px;
    border-radius:5px;
    display:none;
}
.success{
    background:#e6fffa;
    color:#065f46;
}
.error{
    background:#fee2e2;
    color:#991b1b;
}
</style>
</head>

<body>

<div class="container">
    <h2>🔐 Reset Password</h2>

    <form id="resetForm">
        <input type="password" name="password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
        <button class="btn">Reset Password</button>
    </form>

    <div id="msg" class="msg"></div>
</div>

<script>
const token = "<?= htmlspecialchars($token) ?>";

document.getElementById('resetForm').addEventListener('submit', async e => {
    e.preventDefault();

    const form = new FormData(e.target);

    const payload = {
        token: token,
        password: form.get('password'),
        confirm_password: form.get('confirm_password')
    };

    const res = await fetch('../api/reset_password_api.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });

    const data = await res.json();
    const msg = document.getElementById('msg');

    msg.style.display = 'block';

    if (data.success) {
        msg.className = 'msg success';
        msg.innerText = '✅ Password reset successful. Redirecting to login...';

        setTimeout(() => {
            window.location.href = 'login.php';
        }, 3000);
    } else {
        msg.className = 'msg error';
        msg.innerText = data.message || data.errors.join(', ');
    }
});
</script>

</body>
</html>
