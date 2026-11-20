<?php

require_once __DIR__ . '/app/config/database.php';

$username = 'amir';
$email = 'amir@pulse.local';
$password = 'Amir@Pulse2026';
$fullName = 'Amir';

try {
    $check = $pdo->prepare(
        "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1"
    );
    $check->execute([$username, $email]);

    if ($check->fetch()) {
        exit('Amir already exists.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            email,
            password,
            full_name,
            bio,
            role,
            status
        ) VALUES (?, ?, ?, ?, ?, 'user', 'active')
    ");

    $stmt->execute([
        $username,
        $email,
        $hashedPassword,
        $fullName,
        'Developer | Building my future in technology.'
    ]);

    echo 'Amir account created successfully.';

} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
}