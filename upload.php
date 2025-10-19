<?php
// upload.php - receive meme uploads and save file + DB record
header('Content-Type: application/json; charset=utf-8');

// Configuration
$uploadDir = __DIR__ . '/uploads';
$uploadUrlBase = 'uploads'; // relative path to serve files via web
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedMime = ['image/jpeg','image/png','image/gif','image/webp'];

// DB same config as vote.php; for brevity we reuse the MySQL defaults:
$mysql = [
    'host'=>'127.0.0.1',
    'db'=>'campus_polls',
    'user'=>'root',
    'pass'=>'',
    'charset'=>'utf8mb4'
];
try {
    $dsn = "mysql:host={$mysql['host']};dbname={$mysql['db']};charset={$mysql['charset']}";
    $pdo = new PDO($dsn, $mysql['user'], $mysql['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch(Exception $e){
    http_response_code(500); echo json_encode(['success'=>false,'error'=>'DB connection failed']); exit;
}

function jsonErr($msg){ echo json_encode(['success'=>false,'error'=>$msg]); exit; }
function jsonOk($data){ echo json_encode(array_merge(['success'=>true], $data)); exit; }

// Validate uploaded file
if(!isset($_FILES['meme'])) jsonErr('No file uploaded');
$file = $_FILES['meme'];
if($file['error'] !== UPLOAD_ERR_OK) jsonErr('Upload error code ' . $file['error']);
if($file['size'] > $maxFileSize) jsonErr('File too large');

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if(!in_array($mime, $allowedMime)) jsonErr('Unsupported file type');

// ensure upload dir exists
if(!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) jsonErr('Failed to create upload dir');

// generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$basename = bin2hex(random_bytes(8));
$targetName = $basename . '.' . $ext;
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetName;

if(!move_uploaded_file($file['tmp_name'], $targetPath)) jsonErr('Failed to move uploaded file');

// caption
$caption = trim($_POST['caption'] ?? '');
$caption = mb_substr($caption, 0, 200);

// store record in DB
try {
    $stmt = $pdo->prepare('INSERT INTO memes (file_name, caption, created_at) VALUES (:fn, :cap, NOW())');
    $stmt->execute([':fn'=>$targetName, ':cap'=>$caption]);
    $id = $pdo->lastInsertId();
    $url = $uploadUrlBase . '/' . $targetName;
    jsonOk(['meme'=>['id'=>$id, 'path'=>$url, 'caption'=>$caption]]);
} catch(Exception $e){
    // clean up file on db error
    @unlink($targetPath);
    jsonErr('DB error: ' . $e->getMessage());
}