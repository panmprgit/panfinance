<?php
require_once 'functions.php';
require_login();
date_default_timezone_set('Europe/Berlin');

// Handle updates to the credit card payoff plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_plan'])) {
    $plan = max(1, min(12, intval($_POST['pay_plan'])));
    set_setting($_SESSION['user_id'], 'cc_pay_plan', $plan);
    // Record starting balance for progress tracking
    list($totals,,) = get_all_balances($_SESSION['user_id']);
    set_setting($_SESSION['user_id'], 'cc_start_total', $totals['credit_card']);
    header('Location: index.php');
    exit();
}

// Get summary and data
$user_id = $_SESSION['user_id'];
$userF = async_get_user($user_id);
$thisMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$summaryF = async_get_monthly_summary($user_id, $thisMonth);
$scoreF = async_get_health_score($user_id);
$payoffF = async_get_card_payoff_plan($user_id);
$accountsF = async_get_accounts($user_id);
$balancesF = async_get_all_balances($user_id);
$recentF = async_get_transactions($user_id, $thisMonth, null, 6);
$upcomingF = async_get_upcoming_recurring($user_id, 7);
$adviceF = async_get_ai_advice($user_id);

$summary = $summaryF->await();
$score = $scoreF->await();
$scoreColor = $score > 70 ? 'success' : ($score > 40 ? 'warning' : 'danger');
$payoff = $payoffF->await();
$user = $userF->await();
$accounts = $accountsF->await();
list($totals, $computed, $banks) = $balancesF->await();
$categories = $summary['categories'];
$biggest_cat = $summary['biggest'];
$recent = $recentF->await();
$upcoming = $upcomingF->await();
$advice = $adviceF->await();

// For Chart.js
$cat_labels = json_encode(array_keys($categories));
$cat_values = json_encode(array_values($categories));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Dashboard — Finance</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="style.css" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.stat-card { min-width:160px; padding:1em 1.2em; border-radius:1em; display:flex; align-items:center; gap:.5em; margin:0 .75em .75em 0; box-shadow:0 2px 10px rgb(0 0 0 / 0.05); background:#fff; }
body.dark-mode .stat-card { background:#1e293b; color:#e2e8f0; }
.stat-icon { font-size:1.3rem; line-height:1; }
.list-group-transparent .list-group-item { background:transparent; border:none; padding:.35em 0; }
.dashboard-header { display: flex; flex-wrap:wrap; gap: 2em; align-items: center;}
@media (max-width: 900px) { .dashboard-header { flex-direction:column; gap:1em;}.glass{padding:1em;} }
.score-badge { font-size:1.5em; font-weight:bold; border-radius:1em; background:linear-gradient(90deg,#4ade80 30%,#22d3ee 100%); color:#191a2b; padding:.4em 1.4em; display:inline-block; box-shadow:0 0 8px #00f2;}
body.dark-mode .score-badge { background:linear-gradient(90deg,#15803d 30%,#0e7490 100%); color:#b6fffa;}
.big-cat { border-radius:.7em; background:#f1f5f9; color:#64748b; font-weight:600; padding:.2em 1em;}
body.dark-mode .big-cat { background:#1e293b; color:#fbbf24;}
.pending-chip {background:#fff7ed; color:#e11d48; font-weight:600; padding:.2em 1em; border-radius:.8em;}
.sched-chip {background:#e0e7ff; color:#2563eb; font-weight:600; padding:.2em 1em; border-radius:.8em;}
.od-chip {background:#fef2f2; color:#be123c; font-weight:600; padding:.2em 1em; border-radius:.8em;}
.quick-link { text-decoration:none; font-size:.96em; color:#2563eb; margin-right:.6em;}
.quick-link:hover {text-decoration:underline;}
.tiny-tip { font-size:.85em; color:#64748b;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">

  <div class="dashboard-header mb-4">
    <div>
      <h2 class="mb-1">Welcome, <span class="fw-bold"><?= htmlspecialchars($user['username']) ?></span>! 👋</h2>
      <div>
        <span class="score-badge" title="Financial Health Score"><?= $score ?>/100</span>
        <span class="tiny-tip ms-2">AI-based health of your finances</span>
        <div class="progress mt-1" style="height:6px; max-width:200px;">
          <div class="progress-bar bg-<?= $scoreColor ?>" role="progressbar" style="width: <?= $score ?>%;"></div>
        </div>
        <small class="text-muted">Health Score: <?= $score ?>/100</small>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-2">
        <div class="stat-card">
          <span class="stat-icon text-success">⬆️</span>
          <div>
            <div class="small fw-bold text-uppercase">Income</div>
            <div class="text-success fw-bold">+<?= number_format($summary['income'],2) ?>€</div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon text-danger">⬇️</span>
          <div>
            <div class="small fw-bold text-uppercase">Expense</div>
            <div class="text-danger fw-bold">-<?= number_format($summary['expense'],2) ?>€</div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon <?= $summary['net'] >= 0 ? 'text-success' : 'text-danger' ?>">💰</span>
          <div>
            <div class="small fw-bold text-uppercase">Net</div>
            <div class="<?= $summary['net'] >= 0 ? 'text-success' : 'text-danger' ?> fw-bold">
              <?= ($summary['net'] >= 0 ? '+' : '') . number_format($summary['net'],2) ?>€
            </div>
          </div>
        </div>
      </div>
    </div>
    <div>
      <canvas id="catChart" width="260" height="180"></canvas>
    </div>
    <div class="flex-grow-1"></div>
    <div>
      <a href="monthly.php?add=1" class="quick-link">+ Add Transaction</a>
      <a href="banks.php#add" class="quick-link">+ Add Bank/Card</a>
      <a href="settings.php" class="quick-link">Settings</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="glass">
        <div class="mb-2">
          <span class="fw-bold">Biggest Category:</span>
          <span class="big-cat"><?= htmlspecialchars($biggest_cat) ?></span>
        </div>
        <div class="mb-2">
          <span class="fw-bold">Pending:</span>
          <span class="pending-chip"><?= $summary['pending'] ?? 0 ?></span>
          <span class="fw-bold ms-3">Scheduled:</span>
          <span class="sched-chip"><?= $summary['scheduled'] ?? 0 ?></span>
          <span class="fw-bold ms-3">Overdue:</span>
          <span class="od-chip"><?= $summary['overdue'] ?? 0 ?></span>
        </div>
        <div class="tiny-tip mt-2">Click “Add Transaction” to resolve pending/overdue.</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="glass">
        <div class="mb-2"><span class="fw-bold">Banks:</span></div>
        <ul class="list-unstyled mb-0">
        <?php foreach($banks as $b):
              $total = $totals[$b['type']] ?: 1;
              $width = min(100, abs($b['balance']) / $total * 100);
        ?>
          <li class="mb-2">
            <div class="d-flex justify-content-between">
              <span class="fw-bold"><?= htmlspecialchars($b['name']) ?></span>
              <span class="<?= $b['type']=='bank' ? 'text-success':'text-danger' ?>">
                <?= ($b['type']=='bank' ? '' : '-') . number_format($b['balance'],2) ?>€
              </span>
            </div>
            <div class="progress" style="height:4px;">
              <div class="progress-bar <?= $b['type']=='bank' ? 'bg-success':'bg-danger' ?>" style="width:<?= $width ?>%"></div>
            </div>
          </li>
        <?php endforeach; ?>
        </ul>
        <div class="tiny-tip mt-2">
          <a href="banks.php" class="quick-link">Manage Banks/Cards</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="glass">
        <div class="mb-2 fw-bold">Credit Card Payoff Plan</div>
        <form method="post" id="planForm" action="" class="mb-2 d-flex align-items-center">
          <label class="me-2">Pay off in</label>
          <select name="pay_plan" id="pay_plan" class="form-select form-select-sm w-auto" onchange="document.getElementById('planForm').submit()">
            <?php for($i=1;$i<=12;$i++): ?>
              <option value="<?= $i ?>" <?= $i==$payoff['plan_months']?'selected':'' ?>><?= $i ?> month<?= $i>1?'s':'' ?></option>
            <?php endfor; ?>
          </select>
        </form>
        <?php $progress = $payoff['goal_total'] > 0 ? (1 - $payoff['card_owed'] / $payoff['goal_total']) * 100 : 100; ?>
        <div class="progress mb-2" style="height:6px;">
          <div class="progress-bar bg-danger" role="progressbar" style="width:<?= max(0,$progress) ?>%"></div>
        </div>
        <div>
          <span class="fw-bold">Monthly Target:</span>
          <span><?= number_format($payoff['goal_monthly'],2) ?>€</span>
        </div>
        <div>
          <span class="fw-bold">To be card-debt free by:</span>
          <span><?= htmlspecialchars($payoff['free_day']) ?></span>
        </div>
        <div>
          <span class="fw-bold">Months Left:</span>
          <span><?= $payoff['months_left'] ?></span>
        </div>
        <div>
          <span class="fw-bold">Total Owed:</span>
          <span><?= number_format($payoff['goal_total'],2) ?>€</span>
        </div>
        <div class="tiny-tip mt-2">
          <a href="monthly.php?bank_id=<?= htmlspecialchars($payoff['card_id']??'') ?>" class="quick-link">See Card Transactions</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Upcoming Recurring Transactions -->
  <div class="glass mb-4">
    <h5 class="fw-bold mb-3">Upcoming Recurring</h5>
    <?php if(count($upcoming)): ?>
    <ul class="list-group list-group-transparent mb-0">
      <?php foreach($upcoming as $u): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <span class="me-2 fw-bold"><?= htmlspecialchars($u['date']) ?></span>
          <span><?= htmlspecialchars($u['description']) ?></span>
        </div>
        <span class="<?= $u['type']=='income' ? 'text-success' : 'text-danger' ?> fw-bold">
          <?= $u['type']=='income' ? '+' : '-' ?><?= number_format($u['amount'],2) ?>€
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="text-muted mb-0">No upcoming recurring transactions.</p>
    <?php endif; ?>
  </div>

  <!-- Financial Advisor Widget -->
  <div class="glass mb-4">
    <h4 class="fw-bold mb-3">AI Financial Advisor 🤖</h4>
    <p><?= nl2br(h($advice)) ?></p>
    <ul class="mt-3">
      <?php if($payoff['goal_monthly']>0): ?>
      <li>To clear your credit card in <strong><?= $payoff['plan_months'] ?> month<?= $payoff['plan_months']>1?'s':'' ?></strong>, pay at least <strong><?= number_format($payoff['goal_monthly'],2) ?>€</strong> per month (next payoff date: <strong><?= htmlspecialchars($payoff['free_day']) ?></strong>).</li>
      <?php else: ?>
      <li>Congrats! You have no credit card debt. Keep up the good work.</li>
      <?php endif; ?>
      <?php if($score < 65): ?>
      <li>⚠️ <strong>Tip:</strong> Reduce spending in <span class="big-cat"><?= htmlspecialchars($biggest_cat) ?></span> to improve your financial health.</li>
      <?php endif; ?>
      <?php if($summary['pending']>0): ?>
      <li>⏳ You have <strong><?= $summary['pending'] ?></strong> pending transactions – mark them reflected once processed!</li>
      <?php endif; ?>
    </ul>
    <div class="tiny-tip">Advice is generated locally using your recent data.</div>
  </div>

  <div class="glass mb-3">
    <h5 class="fw-bold mb-3">Recent Transactions</h5>
    <div class="table-responsive">
    <table class="table align-middle table-sm">
      <thead>
        <tr>
          <th>Date</th>
          <th>Description</th>
          <th class="text-end">Amount</th>
          <th>Type</th>
          <th>Account</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($recent as $tr): ?>
        <tr>
          <td><?= htmlspecialchars($tr['date']) ?></td>
          <td><?= htmlspecialchars($tr['description']) ?></td>
          <td class="text-end <?= $tr['type']=='income' ? 'text-success fw-bold' : 'text-danger fw-bold' ?>">
            <?= $tr['type']=='income' ? '+' : '-' ?><?= number_format($tr['amount'],2) ?>€
          </td>
          <td><?= ucfirst($tr['type']) ?></td>
          <td><?= htmlspecialchars($tr['bank_name'] ?: '-') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
  if(window.Chart){
    const ctx = document.getElementById('catChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: <?= $cat_labels ?>,
        datasets: [{
          data: <?= $cat_values ?>,
          borderWidth: 1
        }]
      },
      options: {
        plugins: { legend: { position: 'right' } },
        cutout: "65%"
      }
    });
  }
  if(localStorage.getItem('darkMode')==='1'){ document.body.classList.add('dark-mode'); }
});
</script>
</body>
</html>