<?php
require_once 'functions.php';
require_login();
date_default_timezone_set('Europe/Berlin');

$userF = async_get_user($_SESSION['user_id']);
$accountsF = async_get_accounts($_SESSION['user_id']);
$user = $userF->await();
$accounts = $accountsF->await();

$defaultBankF = async(fn() => get_setting($_SESSION['user_id'], 'default_bank', 0));
$darkModeF = async(fn() => get_setting($_SESSION['user_id'], 'dark_mode', '0'));
$default_bank = intval($defaultBankF->await());
$dark_mode = $darkModeF->await();

$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            $messages[] = ['type'=>'danger','text'=>'New passwords do not match.'];
        } else {
            if (password_verify($current, $user['password_hash'])) {
                $pdo = get_db();
                $new_hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE finance_users SET password_hash=? WHERE id=?");
                $stmt->execute([$new_hash, $_SESSION['user_id']]);
                $messages[] = ['type'=>'success','text'=>'Password updated successfully.'];
            } else {
                $messages[] = ['type'=>'danger','text'=>'Current password incorrect.'];
            }
        }
    }

    if (isset($_POST['save_settings'])) {
        $def_bank = intval($_POST['default_bank'] ?? 0);
        $dm = ($_POST['dark_mode'] ?? '0') === '1' ? '1' : '0';

        set_setting($_SESSION['user_id'], 'default_bank', $def_bank);
        set_setting($_SESSION['user_id'], 'dark_mode', $dm);

        $messages[] = ['type'=>'success','text'=>'Settings saved.'];
        $default_bank = $def_bank;
        $dark_mode = $dm;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Settings — Finance</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
body {
    background: linear-gradient(135deg,#e0f2fe 0%,#fef9c3 100%);
    font-family: 'Inter', sans-serif;
    color: #111827;
    min-height: 100vh;
}
body.dark-mode {
    background: linear-gradient(135deg,#161b22 0%,#222a3f 100%);
    color: #ddd;
}
.container {
    max-width: 650px;
    padding-top: 2.5rem;
    padding-bottom: 3rem;
}
.glass-card {
    background: rgba(255,255,255,0.92);
    border-radius: 1.2em;
    padding: 2.5rem 2.5rem 3rem;
    box-shadow: 0 4px 24px rgb(0 0 0 / 0.08);
    backdrop-filter: blur(8px);
    transition: box-shadow 0.3s ease, background 0.3s ease;
    margin-bottom: 2em;
}
body.dark-mode .glass-card {
    background: rgba(30, 35, 48, 0.93);
    box-shadow: 0 4px 24px rgb(0 0 0 / 0.32);
}
h1 {
    font-weight: 900;
    font-size: 2.3rem;
    margin-bottom: 2rem;
    color: #2563eb;
    letter-spacing: -0.03em;
    user-select:none;
}
body.dark-mode h1 {
    color: #60a5fa;
}
.form-label {
    font-weight: 600;
    color: #374151;
    user-select:none;
}
body.dark-mode .form-label {
    color: #cbd5e1;
}
.form-control, .form-select {
    border-radius: 1rem;
    border: 1.8px solid #d1d5db;
    font-size: 1.1rem;
    padding: 0.6rem 1.2rem;
    box-shadow: none !important;
    transition: border-color 0.3s ease;
}
body.dark-mode .form-control, body.dark-mode .form-select {
    background: #15202b;
    border-color: #475569;
    color: #e0e7ff;
}
.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 8px #93c5fdaa;
}
body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 8px #60a5faaa;
}
.btn-primary {
    background: linear-gradient(90deg,#3b82f6 15%,#2563eb 85%);
    font-weight: 700;
    padding: 0.65rem 2.5rem;
    border-radius: 1.2rem;
    box-shadow: 0 6px 14px #2563eb88;
    transition: background 0.3s ease, box-shadow 0.3s ease;
    border: none;
    user-select:none;
}
.btn-primary:hover {
    background: linear-gradient(90deg,#2563eb 15%,#1e40af 85%);
    box-shadow: 0 8px 22px #1e40afbb;
}
.form-check-label {
    font-weight: 600;
    color: #374151;
    user-select:none;
}
body.dark-mode .form-check-label {
    color: #e0e7ff;
}
.form-check-input {
    width: 1.3em;
    height: 1.3em;
    cursor: pointer;
}
.alert {
    border-radius: 1rem;
    font-weight: 600;
    max-width: 650px;
    margin: 0 auto 1.3rem auto;
    user-select:none;
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <h1>Settings ⚙️</h1>

    <?php foreach ($messages as $msg): ?>
        <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endforeach; ?>

    <div class="glass-card shadow-sm">
        <form method="post" autocomplete="off" class="mb-5">
            <h4 class="mb-4">Change Password 🔐</h4>
            <div class="mb-4">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password" />
            </div>
            <div class="mb-4">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required autocomplete="new-password" />
            </div>
            <div class="mb-5">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required autocomplete="new-password" />
            </div>
            <button type="submit" name="change_password" class="btn btn-primary w-100">Update Password</button>
        </form>

        <form method="post" autocomplete="off">
            <h4 class="mb-4">Preferences 🛠️</h4>
            <div class="mb-4">
                <label for="default_bank" class="form-label">Default Account for New Transactions</label>
                <select name="default_bank" id="default_bank" class="form-select">
                    <option value="0">-- None --</option>
                    <?php foreach($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $default_bank === intval($a['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check form-switch mb-4">
                <input type="checkbox" name="dark_mode" class="form-check-input" id="dark_mode_toggle" value="1" <?= $dark_mode === '1' ? 'checked' : '' ?> />
                <label for="dark_mode_toggle" class="form-check-label">Enable Dark Mode</label>
            </div>
            <button type="submit" name="save_settings" class="btn btn-primary w-100">Save Preferences</button>
        </form>
    </div>
</div>

<script>
const darkToggle = document.getElementById('dark_mode_toggle');
darkToggle.addEventListener('change', function(){
    if(this.checked){
        localStorage.setItem('darkMode','1');
        document.body.classList.add('dark-mode');
    } else {
        localStorage.setItem('darkMode','0');
        document.body.classList.remove('dark-mode');
    }
});
if(localStorage.getItem('darkMode')==='1'){
    document.body.classList.add('dark-mode');
}
</script>
</body>
</html>