<?php 
include '../config/session.php';
require_login();
include '../config/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Update Profile</title>

<!-- ✅ ADDED: Required for mobile responsiveness -->
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ---------------- BASE RESET ---------------- */
* {
    box-sizing: border-box;
    font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
}

/* ---------------- PAGE ---------------- */
body {
    margin: 0;
    padding: 16px;
    background: #f5f6fa;
}

/* ---------------- CONTAINER ---------------- */
.container {
    max-width: 480px;
    margin: auto;
    padding: 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

/* ---------------- HEADINGS ---------------- */
h2 {
    text-align: center;
    margin-bottom: 20px;
}

h3 {
    margin-top: 20px;
    font-size: 16px;
}

/* ---------------- INPUTS ---------------- */
input {
    width: 100%;
    padding: 14px;
    margin: 10px 0;
    font-size: 16px; /* ✅ prevents iOS zoom */
    border: 1px solid #ccc;
    border-radius: 8px;
}

/* ---------------- BUTTONS ---------------- */
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

/* ---------------- ACTION BUTTON GROUP ---------------- */
.actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.actions .btn {
    flex: 1;
}

/* ---------------- DIVIDER ---------------- */
hr {
    margin: 24px 0;
    border: none;
    border-top: 1px solid #eee;
}

/* ---------------- MOBILE TWEAKS ---------------- */
@media (max-width: 480px) {
    body {
        padding: 10px;
    }

    .container {
        padding: 16px;
    }
}
</style>
</head>

<body>

<div class="container">
<h2>Update Profile</h2>

<form id="updateForm">
    <input type="text" name="first_name" value="<?=htmlspecialchars($user['first_name'])?>" required>
    <input type="text" name="last_name" value="<?=htmlspecialchars($user['last_name'])?>" required>
    <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" required>

    <hr>

    <h3>Change Password (Optional)</h3>
    <input type="password" name="old_password" placeholder="Old Password">
    <input type="password" name="new_password" placeholder="New Password">

    <button class="btn">Update Profile</button>
</form>

<div class="actions">
    <button class="btn" onclick="goBack()">Back</button>
    <button class="btn" onclick="logout()">Logout</button>
</div>
</div>

<script>
// ---------------- LOGOUT ----------------
async function logout() {
    const res = await fetch('../api/logout_api.php', { method: 'POST' });
    const data = await res.json();
    if (data.success) window.location.href = "login.php";
}

document.getElementById('updateForm').addEventListener('submit', async e => {
    e.preventDefault();

    const form = new FormData(e.target);
    const obj = Object.fromEntries(form.entries());

    const res = await fetch('../api/update_profile_api.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(obj)
    });

    const data = await res.json();

    if (data.success) {
        alert(data.message);
        window.location.href = 'profile.php';
    } else {
        alert(data.message);
    }
});

function goBack(){
    window.location.href = 'profile.php';
}
</script>

</body>
</html>
