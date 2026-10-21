<?php
require_once __DIR__ . '/includes/functions.php';
if (est_connecte()) redirect('index.php');
$envoye = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $email = mb_substr(trim(champ_texte($_POST, 'email')), 0, 150);
    $retry = limiter_auth('reset', $email, 3, 20, 3600);
    if ($retry) reponse_limite($retry);
    if (!$retry && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare('SELECT id FROM users WHERE email = ? AND actif = 1 FOR UPDATE');
            $s->execute([$email]); $uid = $s->fetchColumn();
            if ($uid) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$uid]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')->execute([$uid, hash('sha256', $token)]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        if ($uid) {
            $lien = SITE_URL . '/reinitialiser.php?token=' . $token;
            // Aucun lien affiché ni journalisé, y compris en développement.
            if (!function_exists('mail') || !@mail($email, t('mail_subject'), t('mail_body', $lien))) error_log('ReWear: envoi de récupération indisponible.');
        }
    }
    $envoye = true;
}

$titre = t('forgot_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('forgot_title') ?></h1>
<?php if ($envoye): ?>
  <div class="alert alert-success"><?= t('forgot_sent') ?></div>
<?php else: ?>
  <form class="form" method="post">
    <?= csrf_field() ?>
    <label><?= t('your_email') ?></label><input type="email" name="email" required>
    <button class="btn" type="submit"><?= t('send_link') ?></button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
