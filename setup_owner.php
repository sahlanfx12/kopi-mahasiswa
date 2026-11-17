<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function setup_owner_prompt(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $value = fgets(STDIN);
    if ($value === false) {
        throw new RuntimeException('Input tidak dapat dibaca dari terminal.');
    }

    return trim($value);
}

function setup_owner_secret(string $prompt): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Input password tersembunyi tidak tersedia pada sistem ini.');
        }

        $escapedPrompt = str_replace("'", "''", $prompt);
        $powershell = '$ErrorActionPreference = "Stop"; '
            . '$secret = Read-Host -Prompt ' . "'{$escapedPrompt}'" . ' -AsSecureString; '
            . '$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret); '
            . 'try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)) } '
            . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }';
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-Command', $powershell],
            [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Input password tersembunyi gagal dimulai.');
        }

        $value = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || $value === false) {
            throw new RuntimeException('Input password tersembunyi gagal dibaca.');
        }

        return rtrim($value, "\r\n");
    }

    $terminalMode = shell_exec('stty -g 2>/dev/null');
    if (!is_string($terminalMode) || trim($terminalMode) === '') {
        throw new RuntimeException('Jalankan setup dari terminal interaktif agar password tidak ditampilkan.');
    }

    exec('stty -echo 2>/dev/null', $output, $status);
    try {
        if ($status !== 0) {
            throw new RuntimeException('Terminal tidak mendukung input password tersembunyi.');
        }
        $value = fgets(STDIN);
        if ($value === false) {
            throw new RuntimeException('Input password tidak dapat dibaca.');
        }
    } finally {
        exec('stty ' . escapeshellarg(trim($terminalMode)) . ' 2>/dev/null');
        fwrite(STDOUT, PHP_EOL);
    }

    return rtrim($value, "\r\n");
}

function setup_owner_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

$conn = null;
$transactionStarted = false;

try {
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('config.php belum tersedia. Salin config.example.php dan isi konfigurasi lokal.');
    }

    require_once $configPath;
    if (!isset($conn) || !$conn instanceof mysqli) {
        throw new RuntimeException('Koneksi database lokal tidak tersedia. Periksa config.php.');
    }

    $ownerRoleName = 'Owner';
    $roleCheck = $conn->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
    $roleCheck->bind_param('s', $ownerRoleName);
    $roleCheck->execute();
    $roleResult = $roleCheck->get_result();
    $role = $roleResult->fetch_assoc();
    $roleCheck->close();
    if (!$role) {
        throw new RuntimeException('Role Owner belum ada. Jalankan setup schema terlebih dahulu.');
    }
    $ownerRoleId = (int)$role['id'];

    $existingOwnerCheck = $conn->prepare(
        'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.id = ? LIMIT 1'
    );
    $existingOwnerCheck->bind_param('i', $ownerRoleId);
    $existingOwnerCheck->execute();
    $hasOwner = $existingOwnerCheck->get_result()->num_rows > 0;
    $existingOwnerCheck->close();
    if ($hasOwner) {
        fwrite(STDOUT, "Akun Owner sudah ada. Tidak ada perubahan yang dilakukan.\n");
        exit(0);
    }

    fwrite(STDOUT, "Skema users tidak memiliki kolom username; gunakan nama dan email Owner.\n");
    $name = setup_owner_prompt('Nama Owner: ');
    $email = setup_owner_prompt('Email Owner: ');
    if ($name === '' || setup_owner_length($name) > 100) {
        throw new RuntimeException('Nama wajib diisi dan maksimal 100 karakter.');
    }
    if ($email === '' || setup_owner_length($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Email tidak valid atau melebihi batas skema.');
    }

    $password = setup_owner_secret('Password baru (minimal 12 karakter): ');
    $passwordConfirmation = setup_owner_secret('Ulangi password baru: ');
    if (strlen($password) < 12) {
        throw new RuntimeException('Password harus memiliki minimal 12 karakter.');
    }
    if (!hash_equals($password, $passwordConfirmation)) {
        throw new RuntimeException('Konfirmasi password tidak cocok.');
    }
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    unset($password, $passwordConfirmation);
    if ($passwordHash === false) {
        throw new RuntimeException('Password tidak dapat diamankan.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    // Serialize initial setup and recheck after taking the role row lock.
    $roleLock = $conn->prepare('SELECT id FROM roles WHERE id = ? FOR UPDATE');
    $roleLock->bind_param('i', $ownerRoleId);
    $roleLock->execute();
    if (!$roleLock->get_result()->fetch_assoc()) {
        throw new RuntimeException('Role Owner tidak lagi tersedia.');
    }
    $roleLock->close();

    $ownerRecheck = $conn->prepare('SELECT id FROM users WHERE role_id = ? LIMIT 1 FOR UPDATE');
    $ownerRecheck->bind_param('i', $ownerRoleId);
    $ownerRecheck->execute();
    $ownerAlreadyExists = $ownerRecheck->get_result()->num_rows > 0;
    $ownerRecheck->close();
    if ($ownerAlreadyExists) {
        throw new RuntimeException('Akun Owner sudah dibuat oleh proses setup lain. Tidak ada akun yang diubah.');
    }

    $insertOwner = $conn->prepare(
        'INSERT INTO users (role_id, name, email, password) VALUES (?, ?, ?, ?)'
    );
    $insertOwner->bind_param('isss', $ownerRoleId, $name, $email, $passwordHash);
    $insertOwner->execute();
    $insertOwner->close();

    $conn->commit();
    $transactionStarted = false;
    unset($passwordHash);
    fwrite(STDOUT, "Akun Owner berhasil dibuat. Login menggunakan email yang didaftarkan.\n");
} catch (Throwable $error) {
    if ($transactionStarted && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Keep the user-facing failure message generic.
        }
    }
    error_log('setup_owner.php failed: ' . get_class($error));
    fwrite(STDERR, "Setup Owner gagal. Tidak ada perubahan parsial yang diterapkan. Periksa konfigurasi serta skema database.\n");
    exit(1);
} finally {
    unset($password, $passwordConfirmation, $passwordHash);
}
