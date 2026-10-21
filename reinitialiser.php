<?php
require_once __DIR__ . '/includes/functions.php';
$token = champ_texte($_GET, 'token') ?: champ_texte($_POST, 'token');
$tokenHash = hash('sha256', $token);
$s = $pdo->prepare('SELECT r.id, r.user_id FROM password_resets r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.used = 0 AND r.expires_at > NOW() AND u.actif = 1');
$s->execute([$tokenHash]); $reset = $s->fetch();
$erreur = '';
if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $mdp = champ_texte($_POST, 'mot_de_passe');
    if (!mot_de_passe_valide($mdp)) $erreur = t('err_pwd_len');
    elseif ($mdp !== champ_texte($_POST, 'mot_de_passe2')) $erreur = t('err_pwd_match');
    else {
        $nouveauHash = password_hash($mdp, PASSWORD_DEFAULT);
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare('SELECT id FROM users WHERE id = ? AND actif = 1 FOR UPDATE');
            $s->execute([$reset['user_id']]); $account = $s->fetch();
            $s = $pdo->prepare('SELECT id FROM password_resets WHERE token_hash = ? AND user_id = ? AND used = 0 AND expires_at > NOW() FOR UPDATE');
            $s->execute([$tokenHash, $reset['user_id']]);
            if (!$account || !$s->fetch()) {
                $pdo->rollBack(); $reset = false;
            } else {
                $pdo->prepare('UPDATE users SET mot_de_passe = ?, session_version = session_version + 1 WHERE id = ?')->execute([$nouveauHash, $reset['user_id']]);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
                $pdo->commit();
                fermer_session_auth();
                flash('success', t('pwd_changed'));
                redirect('login.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}

$titre = t('reset_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('reset_title') ?></h1>
<?php if (!$reset): ?>
  <div class="alert alert-error"><?= t('link_invalid') ?> <a href="mot-de-passe-oublie.php"><?= t('retry') ?></a></div>
<?php else: ?>
  <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
  <form class="form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label><?= t('new_pwd') ?></label><input type="password" name="mot_de_passe" required>
    <label><?= t('confirm') ?></label><input type="password" name="mot_de_passe2" required>
    <button class="btn" type="submit"><?= t('save') ?></button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
