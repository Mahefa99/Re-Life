<?php
function champ_texte(array $source, string $key): string {
    return is_string($source[$key] ?? null) ? $source[$key] : '';
}
function mot_de_passe_valide($password): bool {
    // Bcrypt ne doit jamais tronquer silencieusement un mot de passe.
    return is_string($password) && mb_strlen($password, 'UTF-8') >= 12
        && strlen($password) <= 72 && !str_contains($password, "\0");
}
function fermer_session_auth(): void {
    $lang = $_SESSION['lang'] ?? 'fr';
    $_SESSION = ['lang' => in_array($lang, ['fr', 'en'], true) ? $lang : 'fr'];
    session_regenerate_id(true);
}
function ouvrir_session_auth(array $account): void {
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$account['id'], 'nom' => $account['nom'],
        'email' => $account['email'], 'role' => $account['role']];
    $_SESSION['session_version'] = (int)$account['session_version'];
    $_SESSION['auth_started'] = $_SESSION['auth_last_seen'] = time();
    unset($_SESSION['csrf']);
}
function session_expiree(array $session, int $now): bool {
    return empty($session['auth_started']) || empty($session['auth_last_seen'])
        || $now - $session['auth_last_seen'] >= SESSION_IDLE_SECONDS
        || $now - $session['auth_started'] >= SESSION_MAX_SECONDS;
}
function cle_limite(string $action, string $scope, string $value): string {
    return hash('sha256', $action . ':' . $scope . ':' . $value);
}
// Compteurs en base : changer de cookie ne contourne pas la limite.
function limiter_auth(string $action, string $email, int $accountLimit, int $ipLimit, int $window): int {
    global $pdo;
    $buckets = [cle_limite($action, 'account', mb_strtolower($email, 'UTF-8')) => $accountLimit,
        cle_limite($action, 'ip', $_SERVER['REMOTE_ADDR'] ?? 'local') => $ipLimit];
    ksort($buckets);
    $retry = 0;
    $pdo->beginTransaction();
    try {
        foreach ($buckets as $key => $limit) {
            $s = $pdo->prepare('INSERT INTO auth_throttles (bucket, attempts, expires_at) VALUES (?, 1, UNIX_TIMESTAMP() + ?)
                ON DUPLICATE KEY UPDATE attempts = IF(expires_at <= UNIX_TIMESTAMP(), 1, LEAST(attempts + 1, ?)),
                expires_at = IF(expires_at <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + ?, expires_at)');
            $s->execute([$key, $window, $limit + 1, $window]);
            $s = $pdo->prepare('SELECT attempts, expires_at - UNIX_TIMESTAMP() AS remaining FROM auth_throttles WHERE bucket = ?');
            $s->execute([$key]); $row = $s->fetch();
            if ((int)$row['attempts'] > $limit) $retry = max($retry, (int)$row['remaining']);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    if (random_int(1, 100) === 1) $pdo->exec('DELETE FROM auth_throttles WHERE expires_at < UNIX_TIMESTAMP()');
    return $retry;
}
function reponse_limite(int $retry): void {
    http_response_code(429);
    header('Retry-After: ' . max(1, $retry));
}
function enregistrer_image($champ) {
    $file = $_FILES[$champ] ?? null;
    if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])
        || !function_exists('imagecreatefromstring')) return null;
    $size = filesize($file['tmp_name']);
    if (!$size || $size > 3 * 1024 * 1024) return null;
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $info = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || !$info || ($info['mime'] ?? '') !== $mime
        || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 12000000) return null;
    $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$image) return null;
    try {
        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) return null;
        $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
        $path = UPLOAD_DIR . $name;
        imagesavealpha($image, true);
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 85),
            'image/png' => imagepng($image, $path, 6),
            'image/webp' => function_exists('imagewebp') && imagewebp($image, $path, 85),
        };
        if (!$ok || filesize($path) > 3 * 1024 * 1024) {
            if (is_file($path)) unlink($path);
            return null;
        }
        return $name;
    } finally {
        imagedestroy($image);
    }
}
