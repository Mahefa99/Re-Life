<?php
require_once __DIR__ . '/includes/functions.php';
if (est_connecte()) redirect('index.php');
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $email = mb_substr(trim(champ_texte($_POST, 'email')), 0, 150);
    $mdp = champ_texte($_POST, 'mot_de_passe');
    $retry = limiter_auth('login', $email, 10, 50, 900);
    if ($retry) {
        reponse_limite($retry);
        $erreur = t('auth_rate_limited');
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]); $u = $stmt->fetch();
        // Même vérification coûteuse pour les comptes absents et existants.
        $hash = $u['mot_de_passe'] ?? '$2y$10$vwIJLJDAcXtYqC/S9bv24uvbWHMrqLrJdyHDra6pHVKmX.FRpg7Ue';
        $valide = strlen($mdp) <= 72 && !str_contains($mdp, "\0") && password_verify($mdp, $hash);
        if ($u && $u['actif'] && $valide) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET mot_de_passe = ? WHERE id = ? AND mot_de_passe = ?')->execute([password_hash($mdp, PASSWORD_DEFAULT), $u['id'], $hash]);
            }
            $pdo->prepare('DELETE FROM auth_throttles WHERE bucket = ?')->execute([cle_limite('login', 'account', mb_strtolower($email, 'UTF-8'))]);
            $retour = $_SESSION['retour'] ?? null;
            unset($_SESSION['retour']);
            ouvrir_session_auth($u);
            if ($u['role'] === 'admin') redirect('admin/index.php');
            if ($u['role'] === 'livreur') redirect('livreur/index.php');
            redirect(is_string($retour) && str_starts_with($retour, '/') && !str_starts_with($retour, '//')
                && !str_contains($retour, chr(92)) && !preg_match('/[\x00-\x20]/', $retour) ? $retour : 'index.php');
        } else {
            $erreur = t('bad_login');
        }
    }
}

$titre = t('login_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('login_title') ?></h1>
<?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>
<form class="form" method="post">
  <?= csrf_field() ?>
  <label><?= t('email') ?></label><input type="email" name="email" required>
  <label><?= t('password') ?></label><input type="password" name="mot_de_passe" required>
  <button class="btn" type="submit"><?= t('login_link') ?></button>
  <p><a href="mot-de-passe-oublie.php"><?= t('forgot') ?></a></p>
</form>
<p><?= t('no_account') ?> <a href="register.php"><?= t('register') ?></a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
