<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'functions.php';
$user = isset($_SESSION['user_id']) ? get_user($_SESSION['user_id']) : null;
$page = basename($_SERVER['SCRIPT_NAME']);
function nav_active($file) { global $page; return $page == $file ? "active" : ""; }
?>

<nav class="navbar navbar-expand-lg sticky-top glassy-navbar">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php" style="gap:0.3em;">
      <span style="color:#22c55e; font-size:1.3rem;">💸</span>
      <span class="app-name">MyFinanceApp</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" 
      aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation" style="border:none;">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-4 gap-2 align-items-center d-flex flex-row">
        <li class="nav-item"><a class="nav-link <?=nav_active('index.php')?>" href="index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?=nav_active('banks.php')?>" href="banks.php">Banks &amp; Cards</a></li>
        <li class="nav-item"><a class="nav-link <?=nav_active('monthly.php')?>" href="monthly.php">Monthly</a></li>
        <li class="nav-item"><a class="nav-link <?=nav_active('settings.php')?>" href="settings.php">Settings</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
        <a href="monthly.php?add=1" class="btn btn-outline-success btn-sm d-none d-lg-inline" title="Add Transaction">+ Tx</a>
        <a href="banks.php#add" class="btn btn-outline-primary btn-sm d-none d-lg-inline" title="Add Bank/Card">+ Bank</a>
        <button class="btn btn-outline-secondary btn-sm" title="Notifications (coming soon)" style="pointer-events:none;opacity:.7;">
          <i class="bi bi-bell"></i>
        </button>
        <div class="dropdown">
          <a class="d-flex align-items-center text-decoration-none dropdown-toggle user-dropdown" href="#" id="dropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="user-avatar"><?= $user ? strtoupper(substr($user['username'],0,1)) : '?' ?></span>
            <span class="username d-none d-md-inline"><?= $user ? htmlspecialchars($user['username']) : 'Guest' ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownUser">
            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
            <li><a class="dropdown-item" href="monthly.php?add=1">Add Transaction</a></li>
            <li><a class="dropdown-item" href="banks.php#add">Add Bank/Card</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
          </ul>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm d-none d-lg-inline ms-2">Logout</a>
        <span class="navbar-copyright d-none d-lg-block ms-3">© Created by Panagiotis Brent's</span>
      </div>
    </div>
  </div>
</nav>

<!-- Bootstrap icons CDN (for bell) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
.glassy-navbar {
  background: rgba(255, 255, 255, 0.45);
  backdrop-filter: saturate(180%) blur(15px);
  box-shadow: 0 8px 24px rgb(0 0 0 / 0.08);
  transition: background-color 0.3s ease;
  padding: 0.4rem 1rem;
  user-select:none;
  z-index: 1030;
}
body.dark-mode .glassy-navbar {
  background: rgba(18, 25, 42, 0.75);
  box-shadow: 0 8px 28px rgb(0 0 0 / 0.7);
  color: #cbd5e1;
}

.navbar-brand {
  font-size: 1.25rem;
  color: #22c55e;
  user-select:none;
  letter-spacing: -0.03em;
  font-weight: 900;
}
body.dark-mode .navbar-brand {
  color: #4ade80;
}
.app-name {
  font-weight: 900;
  letter-spacing: -0.03em;
  color: inherit;
}

.nav-link {
  font-weight: 600;
  color: #374151;
  padding: 0.4rem 0.75rem;
  transition: color 0.2s ease;
}
body.dark-mode .nav-link {
  color: #94a3b8;
}
.nav-link:hover, .nav-link.active {
  color: #22c55e;
  font-weight: 700;
}
body.dark-mode .nav-link:hover,
body.dark-mode .nav-link.active {
  color: #4ade80;
}

.btn-outline-success {
  border-radius: 0.8rem;
  font-weight: 600;
  padding: 0.28rem 0.9rem;
  font-size: 0.85rem;
  transition: background-color 0.3s ease;
}
.btn-outline-success:hover {
  background-color: #22c55e;
  color: #fff;
  border-color: #22c55e;
}
.btn-outline-primary {
  border-radius: 0.8rem;
  font-weight: 600;
  padding: 0.28rem 0.9rem;
  font-size: 0.85rem;
  transition: background-color 0.3s ease;
}
.btn-outline-primary:hover {
  background-color: #2563eb;
  color: #fff;
  border-color: #2563eb;
}
.btn-outline-secondary {
  border-radius: 0.8rem;
  font-weight: 600;
  padding: 0.28rem 0.75rem;
  font-size: 0.85rem;
}

.btn-outline-danger {
  border-radius: 0.8rem;
  font-weight: 600;
  padding: 0.28rem 0.9rem;
  font-size: 0.85rem;
  transition: background-color 0.3s ease;
}
.btn-outline-danger:hover {
  background-color: #dc2626;
  color: #fff;
  border-color: #dc2626;
}

.user-dropdown {
  gap: 0.5rem;
  color: #374151;
  font-weight: 600;
  text-decoration: none;
  user-select:none;
}
body.dark-mode .user-dropdown {
  color: #cbd5e1;
}
.user-avatar {
  background: #22c55e;
  color: white;
  font-weight: 700;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  text-align: center;
  line-height: 36px;
  font-size: 1.15rem;
  user-select:none;
}
body.dark-mode .user-avatar {
  background: #4ade80;
}

.username {
  font-size: 1rem;
}

.dropdown-menu {
  border-radius: 0.8rem;
  user-select:none;
  font-weight: 600;
  min-width: 180px;
}

.dropdown-item {
  transition: background-color 0.15s ease;
}
.dropdown-item:hover {
  background-color: #dcfce7;
  color: #166534;
}
body.dark-mode .dropdown-item:hover {
  background-color: #166534;
  color: #dcfce7;
}

.dropdown-item.text-danger:hover {
  background-color: #fee2e2 !important;
  color: #b91c1c !important;
}

.navbar-copyright {
  font-size: 0.8rem;
  color: #64748b;
  user-select:none;
  white-space: nowrap;
  align-self: center;
}
body.dark-mode .navbar-copyright {
  color: #94a3b8;
}

@media (max-width: 992px) {
  .navbar-nav {
    gap: 0.75rem !important;
  }
  .btn-outline-success,
  .btn-outline-primary,
  .btn-outline-danger {
    font-size: 0.8rem;
    padding: 0.25rem 0.75rem;
  }
  .user-avatar {
    width: 32px;
    height: 32px;
    line-height: 32px;
    font-size: 1rem;
  }
  .username {
    display: none;
  }
  .navbar-copyright {
    display: none;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if(localStorage.getItem('darkMode')==='1'){
    document.body.classList.add('dark-mode');
  } else {
    document.body.classList.remove('dark-mode');
  }
});
</script>