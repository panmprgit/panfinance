<?php
require_once 'functions.php';
require_login();
date_default_timezone_set('Europe/Berlin');

$userF = async_get_user($_SESSION['user_id']);
$bankListF = async_get_accounts($_SESSION['user_id']);
$user = $userF->await();
$bank_list = $bankListF->await();
$filter_bank = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : 0;
$defaultBankF = async(fn() => get_setting($_SESSION['user_id'], 'default_bank', 0));
$default_bank = intval($defaultBankF->await());
$descF = async(fn() => get_all_descriptions($_SESSION['user_id']));
$all_descriptions = $descF->await();

$thisMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$transactionsF = async_get_transactions($_SESSION['user_id'], $thisMonth, $filter_bank);
$recurringsF = async(fn() => get_recurring($_SESSION['user_id']));
$transactions = $transactionsF->await();
$recurrings = $recurringsF->await();

// Recurring add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD recurring
    if (isset($_POST['add_recur'])) {
        $dow = isset($_POST['days_of_week']) ? implode(',', $_POST['days_of_week']) : '';
        $day_of_month = isset($_POST['day_of_month']) && $_POST['day_of_month'] ? intval($_POST['day_of_month']) : null;
        if ($dow && $day_of_month) $day_of_month = null;
        add_recurring($_SESSION['user_id'], [
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'type' => $_POST['type'],
            'bank_id' => $_POST['bank_id'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'] ?: null,
            'days_of_week' => $dow,
            'day_of_month' => $day_of_month
        ]);
        header("Location: monthly.php"); exit;
    }
    // EDIT recurring
    if (isset($_POST['edit_recur'])) {
        $dow = isset($_POST['days_of_week']) ? implode(',', $_POST['days_of_week']) : '';
        $day_of_month = isset($_POST['day_of_month']) && $_POST['day_of_month'] ? intval($_POST['day_of_month']) : null;
        if ($dow && $day_of_month) $day_of_month = null;
        update_recurring($_SESSION['user_id'], $_POST['id'], [
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'type' => $_POST['type'],
            'bank_id' => $_POST['bank_id'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'] ?: null,
            'days_of_week' => $dow,
            'day_of_month' => $day_of_month
        ]);
        header("Location: monthly.php"); exit;
    }
    // DELETE recurring
    if (isset($_POST['delete_recur'])) {
        delete_recurring($_SESSION['user_id'], $_POST['id']);
        header("Location: monthly.php"); exit;
    }
    // ADD transaction
    if (isset($_POST['add_trans'])) {
        $data = [
            'bank_id' => $_POST['bank_id'],
            'date' => $_POST['date'],
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'type' => $_POST['type'],
            'already_in_balance' => isset($_POST['already_in_balance']) ? 1 : 0
        ];
        add_transaction($_SESSION['user_id'], $data);
        header("Location: monthly.php?month=$thisMonth&bank_id=$filter_bank"); exit;
    }
    // EDIT transaction
    if (isset($_POST['edit_trans'])) {
        $data = [
            'bank_id' => $_POST['bank_id'],
            'date' => $_POST['date'],
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'type' => $_POST['type'],
            'already_in_balance' => isset($_POST['already_in_balance']) ? 1 : 0
        ];
        update_transaction($_SESSION['user_id'], $_POST['id'], $data);
        header("Location: monthly.php?month=$thisMonth&bank_id=$filter_bank"); exit;
    }
    // DELETE transaction
    if (isset($_POST['delete_trans'])) {
        delete_transaction($_SESSION['user_id'], $_POST['id']);
        header("Location: monthly.php?month=$thisMonth&bank_id=$filter_bank"); exit;
    }
    if (isset($_POST['duplicate_trans'])) {
        duplicate_transaction($_SESSION['user_id'], $_POST['id']);
        header("Location: monthly.php?month=$thisMonth&bank_id=$filter_bank"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Monthly Transactions — Finance</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
body {
  background: linear-gradient(135deg,#e0f2fe 0%,#fef9c3 100%);
  font-family: 'Inter', sans-serif;
  min-height:100vh;
}
body.dark-mode {
  background: linear-gradient(135deg,#161b22 0%,#222a3f 100%);
  color:#ddd;
}
.glass {
  background: rgba(255,255,255,0.85);
  border-radius: 1.2em;
  box-shadow: 0 4px 24px rgb(0 0 0 / 0.08);
  padding: 1.5em 1.5em 2em;
  margin-bottom: 2em;
  backdrop-filter: blur(6px);
  transition: box-shadow 0.3s ease;
}
body.dark-mode .glass {
  background: rgba(30, 35, 48, 0.85);
  box-shadow: 0 4px 24px rgb(0 0 0 / 0.45);
}
.glass:hover {
  box-shadow: 0 8px 48px rgb(0 0 0 / 0.12);
}
.status-chip {
  display: inline-block;
  padding: 0.25em 0.7em;
  border-radius: 1em;
  font-size: 0.85em;
  font-weight: 600;
  user-select:none;
}
.status-reflected {
  background: #d1fae5;
  color: #065f46;
}
.status-inbalance {
  background: #e2e8f0;
  color: #475569;
}
.table tbody tr {
  background: #f8fafc;
  border-radius: 0.8rem;
  box-shadow: 0 1px 6px rgb(0 0 0 / 0.04);
}
body.dark-mode .table tbody tr {
  background: #192734;
  box-shadow: 0 1px 8px rgb(0 0 0 / 0.5);
}
.table tbody tr:hover {
  background: #e0f2fe;
}
body.dark-mode .table tbody tr:hover {
  background: #22334a;
}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Monthly Transactions 📅</h2>
    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addRecurModal">+ Add Recurring</button>
  </div>

  <!-- Recurring Transactions -->
  <div class="glass shadow-sm mb-4">
    <h4>Recurring Transactions 🔁</h4>
    <?php if(count($recurrings)): ?>
    <div class="row row-cols-1 row-cols-md-2 g-3 mt-2">
      <?php foreach($recurrings as $rec): ?>
      <div class="col">
        <div class="p-3 border rounded" style="background:#f0f9ff;">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($rec['description']) ?></strong>
              <span class="text-muted ms-2">(<?= $rec['type']=='income'?'+':'-' ?>€<?= number_format($rec['amount'],2) ?>)</span><br/>
              <small class="text-muted">
              <?php
              if ($rec['days_of_week']) {
                  $dows = explode(',', $rec['days_of_week']);
                  $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                  $labels = [];
                  foreach ($dows as $d) $labels[] = $days[intval($d)];
                  echo "Every ".implode(', ', $labels);
                  if ($rec['end_date']) echo " until ".htmlspecialchars($rec['end_date']);
              } elseif ($rec['day_of_month']) {
                  echo "Every month, on the {$rec['day_of_month']}";
                  if ($rec['end_date']) echo " until ".htmlspecialchars($rec['end_date']);
              } else {
                  echo "One-time or custom";
              }
              ?>
              </small>
            </div>
            <div>
              <button class="btn btn-sm btn-primary edit-recur-btn"
                data-id="<?= $rec['id'] ?>"
                data-description="<?= htmlspecialchars($rec['description'],ENT_QUOTES) ?>"
                data-amount="<?= $rec['amount'] ?>"
                data-type="<?= $rec['type'] ?>"
                data-bank="<?= $rec['bank_id'] ?>"
                data-start_date="<?= $rec['start_date'] ?>"
                data-end_date="<?= $rec['end_date'] ?>"
                data-dow="<?= $rec['days_of_week'] ?>"
                data-day_of_month="<?= $rec['day_of_month'] ?>"
                title="Edit Recurring">
                ✏️
              </button>
              <form method="post" style="display:inline">
                <input type="hidden" name="delete_recur" value="1" />
                <input type="hidden" name="id" value="<?= $rec['id'] ?>" />
                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this recurring transaction?')">🗑️</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-muted">No recurring transactions set yet.</p>
    <?php endif; ?>
  </div>

  <!-- Transaction Form -->
  <div class="glass shadow-sm mb-4">
    <form method="post" autocomplete="off" class="row g-2 align-items-end">
      <input type="hidden" name="add_trans" value="1" />
      <div class="col-lg-2 col-md-3 col-6">
        <label for="dateInput" class="form-label">Date</label>
        <input type="date" id="dateInput" name="date" class="form-control" required />
      </div>
      <div class="col-lg-4 col-md-5 col-12">
        <label for="descInput" class="form-label">Description</label>
        <input type="text" id="descInput" name="description" class="form-control" required list="desc-list" autocomplete="off" />
        <datalist id="desc-list">
          <?php foreach($all_descriptions as $desc): ?>
            <option value="<?= htmlspecialchars($desc) ?>"></option>
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="col-lg-2 col-md-3 col-6">
        <label for="amountInput" class="form-label">Amount (€)</label>
        <input type="number" step="0.01" id="amountInput" name="amount" class="form-control" required />
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <label for="typeInput" class="form-label">Type</label>
        <select id="typeInput" name="type" class="form-select" required>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <label for="bankInput" class="form-label">Account</label>
        <select id="bankInput" name="bank_id" class="form-select" required>
          <option value="">-- Select Account --</option>
          <?php foreach($bank_list as $b): ?>
            <option value="<?= $b['id'] ?>"
              <?php if($filter_bank && $b['id'] == $filter_bank) echo 'selected'; else if(!$filter_bank && $default_bank && $b['id'] == $default_bank) echo 'selected'; ?>
            ><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-2 col-md-4 col-6 d-flex align-items-center">
        <div class="form-check mt-4">
          <input type="checkbox" class="form-check-input" id="inBalanceCheck" name="already_in_balance" value="1" />
          <label class="form-check-label" for="inBalanceCheck" title="Already included in starting balance">Already in balance</label>
        </div>
      </div>
      <div class="col-lg-1 col-md-2 col-12 mt-3 mt-lg-0">
        <button type="submit" class="btn btn-success w-100">Add</button>
      </div>
    </form>
    <small class="text-muted mt-2 d-block">
      Autocomplete description remembers previous entries.<br/>
      "Already in balance" marks entries counted in your starting balances.
    </small>
  </div>

  <!-- Filter & Transactions Table -->
  <div class="glass shadow-sm">
    <form method="get" class="mb-3 row g-2">
      <div class="col-md-3">
        <select name="bank_id" class="form-select" onchange="this.form.submit()">
          <option value="">All Accounts & Cards</option>
          <?php foreach($bank_list as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $filter_bank == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <input type="month" name="month" value="<?= htmlspecialchars($thisMonth) ?>" class="form-control" onchange="this.form.submit()">
      </div>
      <noscript><button class="btn btn-primary ms-2">Filter</button></noscript>
      <?php if($filter_bank || (isset($_GET['month']) && $_GET['month'] != date('Y-m'))): ?>
      <div class="col-md-2">
        <a href="monthly.php" class="btn btn-outline-secondary">Clear Filter</a>
      </div>
      <?php endif; ?>
    </form>

    <div class="mb-3">
      <input type="text" id="searchInput" class="form-control" placeholder="Search description...">
    </div>

    <div class="table-responsive">
      <table class="table align-middle" id="transTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th class="text-end">Amount (€)</th>
            <th>Type</th>
            <th>Account</th>
            <th>Status</th>
            <th>Edit</th>
            <th>Copy</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach($transactions as $tr):
            $isInBal = $tr['already_in_balance']==1;
          ?>
          <tr>
            <td><?= htmlspecialchars($tr['date']) ?></td>
            <td><?= htmlspecialchars($tr['description']) ?></td>
            <td class="text-end <?= $tr['type']=='income' ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
              <?= $tr['type']=='income' ? '+' : '-' ?><?= number_format($tr['amount'],2) ?>
            </td>
            <td><?= ucfirst($tr['type']) ?></td>
            <td><?= htmlspecialchars($tr['bank_name'] ?: '-') ?></td>
            <td>
              <?php if($isInBal): ?>
                <span class="status-chip status-inbalance" title="Already included in starting balance">In Balance</span>
              <?php else: ?>
                <span class="status-chip status-reflected" title="Reflected in balance">Reflected</span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-primary edit-trans-btn"
                data-id="<?= $tr['id'] ?>"
                data-date="<?= $tr['date'] ?>"
                data-desc="<?= htmlspecialchars($tr['description'], ENT_QUOTES) ?>"
                data-amount="<?= $tr['amount'] ?>"
                data-type="<?= $tr['type'] ?>"
                data-bank="<?= $tr['bank_id'] ?>"
                data-inbal="<?= $tr['already_in_balance'] ?>"
                data-bs-toggle="modal"
                data-bs-target="#editModal"
                >Edit</button>
            </td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= $tr['id'] ?>" />
                <button type="submit" name="duplicate_trans" class="btn btn-sm btn-secondary">Copy</button>
              </form>
            </td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this transaction?')">
                <input type="hidden" name="id" value="<?= $tr['id'] ?>" />
                <button type="submit" name="delete_trans" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Recurring Modal -->
<div class="modal fade" id="addRecurModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="add_recur" value="1" />
      <div class="modal-header">
        <h5 class="modal-title">Add Recurring Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>Description</label>
        <input type="text" name="description" class="form-control" required list="desc-list" autocomplete="off" />
        <label class="mt-3">Amount</label>
        <input type="number" name="amount" step="0.01" class="form-control" required />
        <label class="mt-3">Type</label>
        <select name="type" class="form-select" required>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
        <label class="mt-3">Bank</label>
        <select name="bank_id" class="form-select" required>
          <?php foreach($bank_list as $b): ?>
          <option value="<?= $b['id'] ?>"
            <?php if($filter_bank && $b['id'] == $filter_bank) echo 'selected'; else if(!$filter_bank && $default_bank && $b['id'] == $default_bank) echo 'selected'; ?>>
            <?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="mt-3">Start Date</label>
        <input type="date" name="start_date" class="form-control" required />
        <label class="mt-3">End Date (optional)</label>
        <input type="date" name="end_date" class="form-control" />
        <label class="mt-3">Repeat on (weekdays)</label>
        <div>
          <?php
          $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
          foreach($days as $i=>$d): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="days_of_week[]" value="<?= ($i+1)%7 ?>" id="add-dow<?= ($i+1)%7 ?>" />
            <label class="form-check-label" for="add-dow<?= ($i+1)%7 ?>"><?= $d ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <label class="mt-3">or every month on day</label>
        <input type="number" name="day_of_month" min="1" max="31" class="form-control" />
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Add Recurring</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Recurring Modal -->
<div class="modal fade" id="editRecurModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="edit_recur" value="1" />
      <input type="hidden" name="id" id="edit-recur-id" />
      <div class="modal-header">
        <h5 class="modal-title">Edit Recurring Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>Description</label>
        <input type="text" name="description" id="edit-recur-description" class="form-control" required />
        <label class="mt-3">Amount</label>
        <input type="number" name="amount" step="0.01" id="edit-recur-amount" class="form-control" required />
        <label class="mt-3">Type</label>
        <select name="type" id="edit-recur-type" class="form-select" required>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
        <label class="mt-3">Bank</label>
        <select name="bank_id" id="edit-recur-bank" class="form-select" required>
          <?php foreach($bank_list as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="mt-3">Start Date</label>
        <input type="date" name="start_date" id="edit-recur-start_date" class="form-control" required />
        <label class="mt-3">End Date (optional)</label>
        <input type="date" name="end_date" id="edit-recur-end_date" class="form-control" />
        <label class="mt-3">Repeat on (weekdays)</label>
        <div>
          <?php
          $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
          foreach($days as $i=>$d): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="days_of_week[]" value="<?= ($i+1)%7 ?>" id="edit-dow<?= ($i+1)%7 ?>" />
            <label class="form-check-label" for="edit-dow<?= ($i+1)%7 ?>"><?= $d ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <label class="mt-3">or every month on day</label>
        <input type="number" name="day_of_month" min="1" max="31" id="edit-recur-day_of_month" class="form-control" />
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="edit_trans" value="1" />
      <input type="hidden" name="id" id="edit-id" />
      <div class="modal-header">
        <h5 class="modal-title">Edit Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>Date</label>
        <input type="date" name="date" id="edit-date" class="form-control" required />
        <label class="mt-3">Description</label>
        <input type="text" name="description" id="edit-desc" class="form-control" required list="desc-list" autocomplete="off" />
        <label class="mt-3">Amount (€)</label>
        <input type="number" name="amount" step="0.01" id="edit-amount" class="form-control" required />
        <label class="mt-3">Type</label>
        <select name="type" id="edit-type" class="form-select" required>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
        <label class="mt-3">Account</label>
        <select name="bank_id" id="edit-bank" class="form-select" required>
          <option value="">-- Select Account --</option>
          <?php foreach($bank_list as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-check mt-3">
          <input type="checkbox" name="already_in_balance" id="edit-inbal" class="form-check-input" value="1" />
          <label for="edit-inbal" class="form-check-label" title="Already included in starting balance">Already in balance</label>
        </div>
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
document.querySelectorAll('.edit-recur-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('edit-recur-id').value = btn.dataset.id;
    document.getElementById('edit-recur-description').value = btn.dataset.description;
    document.getElementById('edit-recur-amount').value = btn.dataset.amount;
    document.getElementById('edit-recur-type').value = btn.dataset.type;
    document.getElementById('edit-recur-bank').value = btn.dataset.bank;
    document.getElementById('edit-recur-start_date').value = btn.dataset.start_date;
    document.getElementById('edit-recur-end_date').value = btn.dataset.end_date||'';
    document.getElementById('edit-recur-day_of_month').value = btn.dataset.day_of_month||'';
    document.querySelectorAll('#editRecurModal input[name="days_of_week[]"]').forEach(cb => cb.checked = false);
    if(btn.dataset.dow) {
      btn.dataset.dow.split(',').forEach(val => {
        let cb = document.querySelector(`#editRecurModal input[name="days_of_week[]"][value="${val}"]`);
        if(cb) cb.checked = true;
      });
    }
    new bootstrap.Modal(document.getElementById('editRecurModal')).show();
  });
});

const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', event => {
  let button = event.relatedTarget;
  document.getElementById('edit-id').value = button.getAttribute('data-id');
  document.getElementById('edit-date').value = button.getAttribute('data-date');
  document.getElementById('edit-desc').value = button.getAttribute('data-desc');
  document.getElementById('edit-amount').value = button.getAttribute('data-amount');
  document.getElementById('edit-type').value = button.getAttribute('data-type');
  document.getElementById('edit-bank').value = button.getAttribute('data-bank') || "";
  document.getElementById('edit-inbal').checked = button.getAttribute('data-inbal') == "1";
});

// search filter
document.getElementById('searchInput').addEventListener('input', function(){
  let q = this.value.toLowerCase();
  document.querySelectorAll('#transTable tbody tr').forEach(tr => {
    let desc = tr.children[1].textContent.toLowerCase();
    tr.style.display = desc.includes(q) ? '' : 'none';
  });
});

if(localStorage.getItem('darkMode')==='1'){
  document.body.classList.add('dark-mode');
}
</script>
</body>
</html>