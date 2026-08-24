<?php
// One-time migration: hash any plaintext passwords still stored in employees.password.
// Safe to re-run — already-hashed rows (bcrypt, starts with $2y$/$2a$/$2b$) are skipped.
// Run from the command line on the server: php sql/migrate_hash_passwords.php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This migration must be run from the command line, not a browser:\n  php " . basename(__FILE__) . "\n");
}

require_once __DIR__ . '/../includes/db.php';

$result = mysqli_query($conn, "SELECT id, email, password FROM employees");
$updated = 0;
$skipped = 0;

while ($row = mysqli_fetch_assoc($result)) {
    if (password_get_info($row['password'])['algo'] !== null) {
        $skipped++;
        continue;
    }
    $hash = password_hash($row['password'], PASSWORD_DEFAULT);
    $hash_escaped = mysqli_real_escape_string($conn, $hash);
    mysqli_query($conn, "UPDATE employees SET password = '$hash_escaped' WHERE id = {$row['id']}");
    $updated++;
    echo "Hashed password for employee id={$row['id']} ({$row['email']})\n";
}

echo "\nDone. Hashed: $updated, already hashed (skipped): $skipped\n";
