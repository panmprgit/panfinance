<?php
require_once 'future.php';
// Enable error reporting (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ---- DATABASE ----
function get_db() {
    static $pdo;
    if ($pdo) return $pdo;
    $host = 'localhost';
    $db   = 'skypan_db0main';
    $user = 'skypan_db0main';
    $pass = 'h06P0akLJ+';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    return $pdo;
}

session_start();

// ---- AUTH & USERS ----
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php?timeout=1");
        exit();
    }
}
function get_user($uid) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_users WHERE id=?");
    $stmt->execute([$uid]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function get_user_by_username($username) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_users WHERE username=?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ---- SETTINGS ----
function get_setting($uid, $key, $def = null) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT setting_value FROM finance_settings WHERE user_id=? AND setting_key=?");
    $stmt->execute([$uid, $key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $def;
}
function set_setting($uid, $key, $val) {
    $pdo = get_db();
    $old = get_setting($uid, $key, null);
    if ($old === null) {
        $stmt = $pdo->prepare("INSERT INTO finance_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $key, $val]);
    } else {
        $stmt = $pdo->prepare("UPDATE finance_settings SET setting_value=? WHERE user_id=? AND setting_key=?");
        $stmt->execute([$val, $uid, $key]);
    }
}

// ---- ACCOUNTS / BANKS / CARDS ----
function get_accounts($uid) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_banks WHERE user_id=? ORDER BY type, name");
    $stmt->execute([$uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function get_account_by_id($uid, $bank_id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_banks WHERE user_id=? AND id=?");
    $stmt->execute([$uid, $bank_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
function add_account($uid, $data) {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO finance_banks (user_id, name, type, balance, last_updated) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$uid, $data['name'], $data['type'], $data['balance']]);
}
function update_account($uid, $id, $data) {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE finance_banks SET name=?, type=?, balance=?, last_updated=NOW() WHERE user_id=? AND id=?");
    $stmt->execute([$data['name'], $data['type'], $data['balance'], $uid, $id]);
}
function delete_account($uid, $id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("DELETE FROM finance_banks WHERE user_id=? AND id=?");
    $stmt->execute([$uid, $id]);
}

// ---- GET ALL BALANCES (for dashboard/stats) ----
function get_all_balances($uid) {
    $accounts = get_accounts($uid);
    $totals = ['bank'=>0,'credit_card'=>0];
    $computed = [];
    foreach ($accounts as $a) {
        $computed[$a['id']] = $a['balance'];
        if ($a['type']=='bank') $totals['bank'] += $a['balance'];
        else $totals['credit_card'] += $a['balance'];
    }
    return [$totals, $computed, $accounts];
}

// ---- TRANSACTIONS ----
function get_transactions($uid, $month = null, $bank_id = null, $limit = null) {
    $pdo = get_db();
    $sql = "SELECT t.*, b.name as bank_name, b.type as bank_type FROM finance_transactions t LEFT JOIN finance_banks b ON t.bank_id = b.id WHERE t.user_id=?";
    $params = [$uid];
    if ($month) {
        $sql .= " AND DATE_FORMAT(t.date, '%Y-%m')=?";
        $params[] = $month;
    }
    if ($bank_id) {
        $sql .= " AND t.bank_id=?";
        $params[] = $bank_id;
    }
    $sql .= " ORDER BY t.date DESC, t.id DESC";
    if ($limit) $sql .= " LIMIT ".intval($limit);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function add_transaction($uid, $data) {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "INSERT INTO finance_transactions (user_id, bank_id, date, description, amount, type, already_in_balance) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $uid,
        $data['bank_id'],
        $data['date'],
        $data['description'],
        $data['amount'],
        $data['type'],
        $data['already_in_balance']
    ]);
    // Reflect to bank balance if not already_in_balance
    update_account_balance_for_transaction($data['bank_id'], $data['amount'], $data['type'], $data['already_in_balance']);
}
function update_transaction($uid, $id, $data) {
    $pdo = get_db();
    // (optional) Before update, get old data to revert bank balance if necessary
    $stmt = $pdo->prepare("SELECT * FROM finance_transactions WHERE user_id=? AND id=?");
    $stmt->execute([$uid, $id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old && !$old['already_in_balance']) {
        // Revert old transaction
        update_account_balance_for_transaction($old['bank_id'], -$old['amount'], $old['type'], 0);
    }
    // Update transaction
    $fields = [];
    $params = [];
    foreach (['bank_id','date','description','amount','type','already_in_balance'] as $k) {
        if (isset($data[$k])) {
            $fields[] = "$k=?";
            $params[] = $data[$k];
        }
    }
    $params[] = $uid;
    $params[] = $id;
    $sql = "UPDATE finance_transactions SET ".implode(', ',$fields)." WHERE user_id=? AND id=?";
    $stmt2 = $pdo->prepare($sql);
    $stmt2->execute($params);
    // Apply new transaction if not already_in_balance
    if ($data['already_in_balance']==0) {
        update_account_balance_for_transaction($data['bank_id'], $data['amount'], $data['type'], 0);
    }
}
function delete_transaction($uid, $id) {
    $pdo = get_db();
    // If needed, revert bank balance
    $stmt = $pdo->prepare("SELECT * FROM finance_transactions WHERE user_id=? AND id=?");
    $stmt->execute([$uid, $id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old && !$old['already_in_balance']) {
        update_account_balance_for_transaction($old['bank_id'], -$old['amount'], $old['type'], 0);
    }
    // Delete
    $stmt2 = $pdo->prepare("DELETE FROM finance_transactions WHERE user_id=? AND id=?");
    $stmt2->execute([$uid, $id]);
}
// Update bank balance for a transaction (+ means income/bank, - means expense/bank, credit cards are reversed)
function update_account_balance_for_transaction($bank_id, $amount, $type, $already_in_balance = 0) {
    if ($already_in_balance) return;
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_banks WHERE id=?");
    $stmt->execute([$bank_id]);
    $bank = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bank) return;
    if ($bank['type'] == 'credit_card') {
        // For credit cards: expenses increase owed, incomes (payments) decrease
        $delta = ($type == 'expense') ? $amount : -$amount;
    } else {
        // For banks: income increases, expense decreases
        $delta = ($type == 'expense') ? -$amount : $amount;
    }
    $pdo->prepare("UPDATE finance_banks SET balance = balance + ?, last_updated=NOW() WHERE id=?")->execute([$delta, $bank_id]);
}

// ---- RECURRING ----
function get_recurring($uid) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_recurring WHERE user_id=? ORDER BY start_date DESC");
    $stmt->execute([$uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function add_recurring($uid, $data) {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO finance_recurring (user_id, description, amount, type, bank_id, start_date, end_date, days_of_week, day_of_month) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $uid,
        $data['description'],
        $data['amount'],
        $data['type'],
        $data['bank_id'],
        $data['start_date'],
        $data['end_date'],
        $data['days_of_week'],
        $data['day_of_month']
    ]);
}
function update_recurring($uid, $id, $data) {
    $pdo = get_db();
    $fields = [];
    $params = [];
    foreach (['description','amount','type','bank_id','start_date','end_date','days_of_week','day_of_month'] as $k) {
        if (isset($data[$k])) {
            $fields[] = "$k=?";
            $params[] = $data[$k];
        }
    }
    $params[] = $uid;
    $params[] = $id;
    $sql = "UPDATE finance_recurring SET ".implode(', ',$fields)." WHERE user_id=? AND id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
function delete_recurring($uid, $id) {
    $pdo = get_db();
    $stmt = $pdo->prepare("DELETE FROM finance_recurring WHERE user_id=? AND id=?");
    $stmt->execute([$uid, $id]);
}

// ---- AUTOCOMPLETE (DESCRIPTION) ----
function get_all_descriptions($uid) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT DISTINCT description FROM finance_transactions WHERE user_id=? ORDER BY id DESC LIMIT 100");
    $stmt->execute([$uid]);
    return array_map(function($row){ return $row['description']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ---- CATEGORY (for summary) ----
function categorize($desc) {
    $desc = strtolower($desc);
    $category_keywords = [
        'Home' => ['rent', 'utilities', 'electricity', 'deposit', 'bauhaus', 'ikea', 'hornbach', 'gas', 'apartment'],
        'Groceries' => ['aldi', 'lidl', 'edeka', 'rewe', 'kaufland', 'netto', 'penny', 'supermarket', 'grocery'],
        'Fun' => ['netflix', 'spotify', 'cinema', 'movie', 'bar', 'club', 'prime', 'disney', 'restaurant', 'party', 'theater', 'concert', 'game', 'gaming'],
        'Transport' => ['bahn', 'db', 'ubahn', 'sbahn', 'bus', 'bvg', 'train', 'uber', 'taxi'],
        'Health' => ['doctor', 'pharmacy', 'dentist', 'clinic', 'fitx', 'fitness', 'gym', 'medicine'],
        'Other' => [],
    ];
    foreach ($category_keywords as $cat => $words) {
        foreach ($words as $w) {
            if (strpos($desc, $w) !== false) return $cat;
        }
    }
    return 'Other';
}

// ---- SUMMARY ----
function get_monthly_summary($uid, $month = null) {
    $month = $month ?: date('Y-m');
    $transactions = get_transactions($uid, $month);
    $categories = [
        'Home'=>0.0, 'Groceries'=>0.0, 'Fun'=>0.0, 'Transport'=>0.0, 'Health'=>0.0, 'Other'=>0.0
    ];
    $income = 0; $expense = 0; $net = 0;
    $pending = 0; $scheduled = 0; $overdue = 0;
    $today = strtotime(date('Y-m-d'));
    foreach ($transactions as $tr) {
        $isInBal = $tr['already_in_balance']==1;
        $isReflected = !$isInBal;
        $trDate = strtotime($tr['date']);
        // Track scheduling status for transactions not already in starting balance
        if (!$isInBal) {
            if ($trDate > $today) {
                $scheduled++;
            } else {
                $pending++;
                if ($trDate < $today) $overdue++;
            }
        }
        // Categories (for reflected only)
        if ($isReflected && $tr['type']=='expense') {
            $cat = categorize($tr['description']);
            $categories[$cat] += $tr['amount'];
        }
        // Totals
        if ($isReflected) {
            if ($tr['type']=='income') $income += $tr['amount'];
            else $expense += $tr['amount'];
        }
    }
    $net = $income - $expense;
    arsort($categories);
    $biggest = array_key_first($categories);
    return [
        'categories'=>$categories,
        'biggest'=>$biggest,
        'income'=>$income,
        'expense'=>$expense,
        'net'=>$net,
        'pending'=>$pending,
        'scheduled'=>$scheduled,
        'overdue'=>$overdue,
    ];
}

// ---- HEALTH SCORE & CARD PAYOFF ----
function get_health_score($uid) {
    list($totals,,) = get_all_balances($uid);
    $sum = get_monthly_summary($uid, date('Y-m'));
    $total_bank = $totals['bank'];
    $total_card = $totals['credit_card'];
    $net = $sum['net'];
    $income = $sum['income'];
    $score = 100;
    if ($total_card > 0) $score -= min(40, round(35 * $total_card / max(1,$total_bank+1)));
    if ($net < 0) $score -= min(20, round(abs($net) / max(1, $income)*20));
    if ($income > 0 && ($income-$sum['expense'])/$income < 0.2) $score -= 10;
    if ($total_bank < 500) $score -= 5;
    return max(1, min(100, $score));
}
function get_card_payoff_plan($uid) {
    list($totals,,) = get_all_balances($uid);
    $total_card = $totals['credit_card'];
    $plan_months = intval(get_setting($uid, 'cc_pay_plan', 3));
    $goal_monthly = $total_card > 0 ? ceil($total_card / max(1, $plan_months)) : 0;
    $months_left  = $goal_monthly > 0 ? ceil($total_card / $goal_monthly) : 0;
    $cc_free_day = $total_card > 0 ? date('Y-m-d', strtotime("+".($months_left*30)." days")) : date('Y-m-d');
    return [
        'plan_months'=>$plan_months,
        'goal_monthly'=>$goal_monthly,
        'goal_total'=>$total_card,
        'months_left'=>$months_left,
        'free_day'=>$cc_free_day,
        'card_owed'=>$total_card,
    ];
}

// ---- AI ADVICE ----
function get_ai_advice($uid) {
    $summary = get_monthly_summary($uid, date('Y-m'));
    $score   = get_health_score($uid);

    $tips = [];

    // Tip based on health score
    if ($score >= 80) {
        $tips[] = 'Great job maintaining a high financial health score.';
    } elseif ($score >= 60) {
        $tips[] = 'Your finances look decent; see if you can push that score even higher.';
    } else {
        $tips[] = 'Focus on reducing debts and building savings to lift your health score.';
    }

    // Net income evaluation
    if ($summary['net'] < 0) {
        $tips[] = 'Expenses exceed income this month. Consider cutting back discretionary costs.';
    } else {
        $tips[] = 'You earned more than you spent. Transfer part of that surplus to savings.';
    }

    // Major spending category hint
    if (!empty($summary['biggest'])) {
        $tips[] = 'Major spending noted in '.htmlspecialchars($summary['biggest']).'. Review for possible savings.';
    }

    $generic = [
        'Automate a monthly transfer to your savings account.',
        'Review any unused subscriptions and cancel them.',
        'Keep track of all expenses to identify spending patterns.',
        'Build an emergency fund covering three months of expenses.'
    ];
    shuffle($generic);
    $tips[] = array_shift($generic);

    return implode("\n", array_slice($tips, 0, 3));
}

// ---- UPCOMING RECURRING ----
function get_upcoming_recurring($uid, $days = 7) {
    $recurrings = get_recurring($uid);
    $upcoming = [];
    $start = strtotime(date('Y-m-d'));
    $end = strtotime("+{$days} days", $start);
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $date = date('Y-m-d', $ts);
        $w = date('w', $ts); // 0 (Sun) - 6 (Sat)
        $dom = intval(date('j', $ts));
        foreach ($recurrings as $rec) {
            if ($date < $rec['start_date']) continue;
            if ($rec['end_date'] && $date > $rec['end_date']) continue;
            if ($rec['days_of_week']) {
                $dows = array_map('intval', explode(',', $rec['days_of_week']));
                if (in_array($w, $dows)) {
                    $upcoming[] = array_merge($rec, ['date' => $date]);
                }
            } elseif ($rec['day_of_month']) {
                if (intval($rec['day_of_month']) === $dom) {
                    $upcoming[] = array_merge($rec, ['date' => $date]);
                }
            } else {
                if ($rec['start_date'] === $date) {
                    $upcoming[] = array_merge($rec, ['date' => $date]);
                }
            }
        }
    }
    usort($upcoming, function($a, $b) { return strcmp($a['date'], $b['date']); });
    return $upcoming;
}

function duplicate_transaction($uid, $id, $new_date = null) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM finance_transactions WHERE user_id=? AND id=?");
    $stmt->execute([$uid, $id]);
    $tr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tr) return;
    $data = [
        'bank_id' => $tr['bank_id'],
        'date' => $new_date ?: date('Y-m-d'),
        'description' => $tr['description'],
        'amount' => $tr['amount'],
        'type' => $tr['type'],
        'already_in_balance' => $tr['already_in_balance']
    ];
    add_transaction($uid, $data);
}

// ---- ESCAPE OUTPUT ----
function h($s) { return htmlspecialchars($s, ENT_QUOTES); }

// ---- CSRF TOKEN ----
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf($token) {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// ---- AJAX/REALTIME API ENDPOINT (optional) ----
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $uid = $_SESSION['user_id'];
    try {
        if ($_GET['api']=='balances') {
            list($totals, $computed, $accounts) = get_all_balances($uid);
            echo json_encode(['success'=>true, 'totals'=>$totals, 'accounts'=>$computed, 'banks'=>$accounts]);
            exit();
        }
        if ($_GET['api']=='transactions') {
            $month = $_GET['month'] ?? null;
            $bank_id = $_GET['bank_id'] ?? null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
            echo json_encode(['success'=>true, 'transactions'=>get_transactions($uid, $month, $bank_id, $limit)]);
            exit();
        }
        if ($_GET['api']=='summary') {
            $month = $_GET['month'] ?? null;
            echo json_encode(['success'=>true, 'summary'=>get_monthly_summary($uid, $month)]);
            exit();
        }
        if ($_GET['api']=='score') {
            echo json_encode(['success'=>true, 'score'=>get_health_score($uid)]);
            exit();
        }
        if ($_GET['api']=='categories') {
            $month = $_GET['month'] ?? null;
            $sum = get_monthly_summary($uid, $month);
            echo json_encode(['success'=>true, 'categories'=>$sum['categories'],'biggest'=>$sum['biggest']]);
            exit();
        }
        if ($_GET['api']=='recent') {
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
            echo json_encode(['success'=>true, 'transactions'=>get_transactions($uid, null, null, $limit)]);
            exit();
        }
        if ($_GET['api']=='payoff') {
            echo json_encode(['success'=>true, 'plan'=>get_card_payoff_plan($uid)]);
            exit();
        }
        echo json_encode(['success'=>false,'error'=>'Unknown API call']);
    } catch(Exception $e) {
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    exit();
}

// ---- ASYNC WRAPPERS ----
function async_get_user(int $uid): Future {
    return async(fn() => get_user($uid));
}

function async_get_accounts(int $uid): Future {
    return async(fn() => get_accounts($uid));
}

function async_get_transactions(int $uid, ?string $month = null, ?int $bank_id = null, ?int $limit = null): Future {
    return async(fn() => get_transactions($uid, $month, $bank_id, $limit));
}

function async_get_monthly_summary(int $uid, ?string $month = null): Future {
    return async(fn() => get_monthly_summary($uid, $month));
}

function async_get_health_score(int $uid): Future {
    return async(fn() => get_health_score($uid));
}

function async_get_card_payoff_plan(int $uid): Future {
    return async(fn() => get_card_payoff_plan($uid));
}

function async_get_ai_advice(int $uid): Future {
    return async(fn() => get_ai_advice($uid));
}

function async_get_all_balances(int $uid): Future {
    return async(fn() => get_all_balances($uid));
}

function async_get_upcoming_recurring(int $uid, int $days = 7): Future {
    return async(fn() => get_upcoming_recurring($uid, $days));
}
?>