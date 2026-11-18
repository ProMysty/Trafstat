<?php
/**
 * tools/create_admin.php
 * Provision an administrator account.
 *
 * Usage (from the project root):
 *   php tools/create_admin.php <username> "<Full Name>" <email>
 *
 * This script refuses to run over HTTP.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be executed from the command line.\n");
}

require_once __DIR__ . '/../includes/init.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php tools/create_admin.php <username> \"<Full Name>\" <email>\n");
    exit(1);
}

$username = trim($argv[1]);
$fullName = trim($argv[2]);
$email    = trim($argv[3]);

if (!preg_match('/^[a-zA-Z0-9._-]{4,50}$/', $username)) {
    fwrite(STDERR, "Username must be 4-50 characters (letters, digits, dot, underscore, hyphen).\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "A valid e-mail address is required.\n");
    exit(1);
}

if (db_value('SELECT COUNT(*) FROM administrators WHERE username = ?', [$username])) {
    fwrite(STDERR, "That username already exists.\n");
    exit(1);
}

if (db_value('SELECT COUNT(*) FROM administrators WHERE email = ?', [$email])) {
    fwrite(STDERR, "That e-mail address is already registered.\n");
    exit(1);
}

/**
 * Cryptographically secure temporary password.
 */
function generate_temporary_password(int $length = 18): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?';
    $maxIndex = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $maxIndex)];
    }

    return $password;
}

$temporaryPassword = generate_temporary_password();

db_execute(
    'INSERT INTO administrators
        (username, full_name, email, password_hash,
         account_status, must_change_password, password_changed_at)
     VALUES
        (:username, :full_name, :email, :password_hash,
         :status, 1, NULL)',
    [
        ':username'      => $username,
        ':full_name'     => $fullName,
        ':email'         => $email,
        ':password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
        ':status'        => 'active',
    ]
);

$newId = (int) db()->lastInsertId();

log_activity(
    $newId,
    'ADMIN_CREATED',
    'Administrator account provisioned via CLI: ' . $username,
    'administrators',
    $newId,
    ['provisioned_by' => 'cli']
);

echo "Administrator created successfully.\n";
echo "  ID        : {$newId}\n";
echo "  Username  : {$username}\n";
echo "  Full name : {$fullName}\n";
echo "  E-mail    : {$email}\n";
echo "  Temporary password: {$temporaryPassword}\n\n";
echo "IMPORTANT: Deliver this password through a secure channel.\n";
echo "The account is flagged to change its password on first login.\n";