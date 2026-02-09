<!DOCTYPE html>
<html>
<head>
<title>Forgot Password</title>
<meta charset="UTF-8">

<!-- ✅ REQUIRED FOR RESPONSIVE DESIGN -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ---------------- RESET ---------------- */
* {
    box-sizing: border-box;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
}

/* ---------------- PAGE ---------------- */
body {
    margin: 0;
    min-height: 100vh;
    background: #f4f6f8;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

/* ---------------- CARD ---------------- */
.container {
    width: 100%;
    max-width: 420px;
    padding: 24px;
    background: white;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(0,0,0,.12);
}

/* ---------------- HEADINGS ---------------- */
h2 {
    text-align: center;
    margin-bottom: 16px;
}

/* ---------------- INPUT ---------------- */
input {
    width: 100%;
    padding: 14px;
    margin: 12px 0;
    font-size: 16px; /* ✅ prevents iOS zoom */
    border: 1px solid #ccc;
    border-radius: 8px;
}

/* ---------------- BUTTON ---------------- */
.btn {
    width: 100%;
    padding: 14px;
    margin-top: 10px;
    font-size: 16px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.btn:hover {
    background: #5a67d8;
}

/* ---------------- MESSAGE ---------------- */
.msg {
    margin-top: 16px;
    padding: 12px;
    border-radius: 8px;
    display: none;
    font-size: 15px;
}

.success {
    background: #e6fffa;
    color: #065f46;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

/* ---------------- LINK ---------------- */
a {
    color: #667eea;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

/* ---------------- MOBILE TWEAKS ---------------- */
@media (max-width: 480px) {
    .container {
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="container">
    <h2>🔑 Forgot Password</h2>

    <form id="forgotForm">
        <input type="email" name="email" placeholder="Enter your email" required>
        <button class="btn">Send Reset Link</button>
    </form>

    <div id="msg" class="msg"></div>

    <p style="text-align:center;margin-top:18px">
        <a href="login.php">← Back to Login</a>
    </p>
</div>

<script>
document.getElementById('forgotForm').addEventListener('submit', async e => {
    e.preventDefault();

    const email = new FormData(e.target).get('email');

    const res = await fetch('../api/forgot_password_api.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ email })
    });

    const data = await res.json();
    const msg = document.getElementById('msg');

    msg.style.display = 'block';

    if(data.success){
        msg.className = 'msg success';
        msg.innerText = '📧 Password reset link sent. Check your email.';
    } else {
        msg.className = 'msg error';
        msg.innerText = data.message;
    }
});
</script>

</body>
</html>
