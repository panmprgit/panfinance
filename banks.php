<?php
require_once 'functions.php';
require_login();
date_default_timezone_set('Europe/Berlin');

$userF = async_get_user($_SESSION['user_id']);
$bankListF = async_get_accounts($_SESSION['user_id']);
$user = $userF->await();
$bank_list = $bankListF->await();

// Add bank/card
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add
    if (isset($_POST['add_bank'])) {
        $pdo = get_db();
        $stmt = $pdo->prepare("INSERT INTO finance_banks (user_id, name, type, balance) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['name'],
            $_POST['type'],
            $_POST['balance']
        ]);
        header("Location: banks.php"); exit;
    }
    // Edit
    if (isset($_POST['edit_bank'])) {
        $pdo = get_db();
        $stmt = $pdo->prepare("UPDATE finance_banks SET name=?, type=?, balance=? WHERE id=? AND user_id=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['type'],
            $_POST['balance'],
            $_POST['id'],
            $_SESSION['user_id']
        ]);
        header("Location: banks.php"); exit;
    }
    // Delete
    if (isset($_POST['delete_bank'])) {
        $pdo = get_db();
        $stmt = $pdo->prepare("DELETE FROM finance_banks WHERE id=? AND user_id=?");
        $stmt->execute([
            $_POST['id'],
            $_SESSION['user_id']
        ]);
        header("Location: banks.php"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Banks & Cards — Finance</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="style.css" />
<style>
.card-bank {
  width:100%;
  background:rgba(255,255,255,0.9);
  border-radius:1.5em;
  box-shadow:0 2px 10px #0001;
  padding:1.5em 1.5em 1.2em;
  margin-bottom:2em;
  transition:box-shadow .3s ease;
  backdrop-filter: blur(4px);
}
.card-bank:hover {box-shadow:0 4px 24px #3b82f630;}
body.dark-mode .card-bank {
  background: rgba(30,35,48,0.9);
  box-shadow: 0 2px 10px #0004;
  color: #e2e8f0;
}
.bank-head{display:flex;align-items:center;gap:.75em;}
.bank-actions{margin-left:auto;}
.bank-icon{font-size:2em;line-height:1;}
.cc-icon{font-size:2em;line-height:1;}
.badge-bank {background:#22d3ee;color:#0369a1;}
.badge-card {background:#fbbf24;color:#92400e;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Banks & Credit Cards 🏦</h2>
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addBankModal">+ Add Account</button>
  </div>
  <div class="row">
    <?php foreach($bank_list as $b): ?>
      <div class="col-12">
        <div class="card-bank glass mb-4">
          <div class="bank-head">
            <span class="bank-icon <?= $b['type']=='credit_card'?'cc-icon':'bank-icon' ?>">
              <?= $b['type']=='credit_card' ? '💳' : '🏦' ?>
            </span>
            <div class="flex-grow-1">
              <div class="fw-bold mb-1"><?= htmlspecialchars($b['name']) ?></div>
              <span class="badge <?= $b['type']=='credit_card'?'badge-card':'badge-bank' ?>">
                <?= $b['type']=='credit_card' ? 'Credit Card' : 'Bank' ?>
              </span>
            </div>
            <div class="bank-actions">
              <button class="btn btn-sm btn-primary edit-bank-btn"
                data-id="<?= $b['id'] ?>"
                data-name="<?= htmlspecialchars($b['name'], ENT_QUOTES) ?>"
                data-type="<?= $b['type'] ?>"
                data-balance="<?= $b['balance'] ?>"
                data-bs-toggle="modal"
                data-bs-target="#editBankModal">
                Edit
              </button>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this bank/card?')">
                <input type="hidden" name="id" value="<?= $b['id'] ?>" />
                <button type="submit" name="delete_bank" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </div>
          </div>
          <div class="bank-balance display-6 <?= $b['type']=='credit_card'?'text-danger':'text-success' ?> mt-3">
            <?= $b['type']=='credit_card' ? 'Owed: ' : 'Balance: ' ?>
            €<?= number_format($b['balance'],2) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Add Bank Modal -->
<div class="modal fade" id="addBankModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="add_bank" value="1" />
      <div class="modal-header">
        <h5 class="modal-title">Add Bank or Credit Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>Name</label>
        <input type="text" name="name" class="form-control" required />
        <label class="mt-3">Type</label>
        <select name="type" class="form-select" required>
          <option value="bank">Bank</option>
          <option value="credit_card">Credit Card</option>
        </select>
        <label class="mt-3">Balance (€)</label>
        <input type="number" step="0.01" name="balance" class="form-control" required />
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Add</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Bank Modal -->
<div class="modal fade" id="editBankModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="edit_bank" value="1" />
      <input type="hidden" name="id" id="edit-bank-id" />
      <div class="modal-header">
        <h5 class="modal-title">Edit Bank/Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>Name</label>
        <input type="text" name="name" id="edit-bank-name" class="form-control" required />
        <label class="mt-3">Type</label>
        <select name="type" id="edit-bank-type" class="form-select" required>
          <option value="bank">Bank</option>
          <option value="credit_card">Credit Card</option>
        </select>
        <label class="mt-3">Balance (€)</label>
        <input type="number" step="0.01" name="balance" id="edit-bank-balance" class="form-control" required />
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.edit-bank-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit-bank-id').value = btn.dataset.id;
    document.getElementById('edit-bank-name').value = btn.dataset.name;
    document.getElementById('edit-bank-type').value = btn.dataset.type;
    document.getElementById('edit-bank-balance').value = btn.dataset.balance;
  });
});
document.addEventListener('DOMContentLoaded', function(){
  if(localStorage.getItem('darkMode')==='1'){
    document.body.classList.add('dark-mode');
  }
});
</script>
</body>
</html>
