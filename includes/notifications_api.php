<?php
require_once 'auth.php';
redirectIfNotLoggedIn();
require_once 'db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$action  = $_GET['action'] ?? '';
$user_id = (int)$_SESSION['user_id'];

try {
    switch ($action) {
        case 'count':
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM notifications WHERE employee_id=$user_id AND is_read=0"));
            echo json_encode(['count' => (int)($r['c'] ?? 0)]);
            break;

        case 'list':
            $rows = [];
            $res  = mysqli_query($conn, "SELECT * FROM notifications WHERE employee_id=$user_id ORDER BY created_at DESC LIMIT 15");
            if ($res) while ($n = mysqli_fetch_assoc($res)) $rows[] = $n;
            echo json_encode($rows);
            break;

        case 'mark_read':
            // State-changing — require POST so a plain cross-site GET (e.g. an <img> tag) can't trigger it.
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST required']); break; }
            $id = intval($_GET['id'] ?? 0);
            if ($id) mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE id=$id AND employee_id=$user_id");
            echo json_encode(['ok' => true]);
            break;

        case 'mark_all':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error' => 'POST required']); break; }
            mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE employee_id=$user_id");
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['error' => 'invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'temporarily unavailable']);
}
