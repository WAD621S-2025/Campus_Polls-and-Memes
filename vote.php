<?php
// vote.php
// POST: poll_id, option_id  => records vote and returns updated results as JSON
// GET: ?action=get&poll_id=... => returns poll results

header('Content-Type: application/json; charset=utf-8');

// --- Configuration: adjust for your environment ---
$use_sqlite = false; // set true to use SQLite (file-based), false to use MySQL
$sqlite_file = __DIR__ . '/data/polls.sqlite';
$mysql = [
    'host'=>'127.0.0.1',
    'db'=>'campus_polls',
    'user'=>'root',
    'pass'=>'',
    'charset'=>'utf8mb4'
];
try {
    if($use_sqlite){
        $pdo = new PDO('sqlite:' . $sqlite_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        $dsn = "mysql:host={$mysql['host']};dbname={$mysql['db']};charset={$mysql['charset']}";
        $pdo = new PDO($dsn, $mysql['user'], $mysql['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>'DB connection failed']);
    exit;
}

// utilities
function jsonErr($msg){ echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function jsonOk($data){ echo json_encode(array_merge(['success'=>true], $data)); exit; }

// GET results
if($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action']==='get'){
    $poll_id = intval($_GET['poll_id'] ?? 0);
    if(!$poll_id) jsonErr('Invalid poll id');
    $stmt = $pdo->prepare('SELECT id, option_text, votes FROM poll_options WHERE poll_id = :pid ORDER BY id');
    $stmt->execute([':pid'=>$poll_id]);
    $rows = $stmt->fetchAll();
    // return as results array
    $results = array_map(function($r){ return ['id'=>$r['id'],'text'=>$r['option_text'],'votes'=>intval($r['votes'])]; }, $rows);
    jsonOk(['results'=>$results]);
}

// POST vote
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $poll_id = intval($_POST['poll_id'] ?? 0);
    $option_id = intval($_POST['option_id'] ?? 0);
    if(!$poll_id || !$option_id) jsonErr('Missing poll_id or option_id');

    // Simple anti-spam/csrf suggestion: check session or token here (omitted for demo)

    // increment vote (transaction)
    try {
        $pdo->beginTransaction();
        // ensure option belongs to poll
        $stmt = $pdo->prepare('SELECT id FROM poll_options WHERE id = :opt AND poll_id = :pid FOR UPDATE');
        $stmt->execute([':opt'=>$option_id, ':pid'=>$poll_id]);
        $row = $stmt->fetch();
        if(!$row){
            $pdo->rollBack();
            jsonErr('Option not found for poll');
        }
        $stmt = $pdo->prepare('UPDATE poll_options SET votes = votes + 1 WHERE id = :opt');
        $stmt->execute([':opt'=>$option_id]);
        $pdo->commit();

        // return updated totals
        $stmt = $pdo->prepare('SELECT id, option_text, votes FROM poll_options WHERE poll_id = :pid ORDER BY id');
        $stmt->execute([':pid'=>$poll_id]);
        $rows = $stmt->fetchAll();
        $results = array_map(function($r){ return ['id'=>$r['id'],'text'=>$r['option_text'],'votes'=>intval($r['votes'])]; }, $rows);
        jsonOk(['results'=>$results]);
    } catch(Exception $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        jsonErr('Database error: ' . $e->getMessage());
    }
}

jsonErr('Unsupported request');
