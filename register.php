<?php
// register.php : création de compte (rôle "utilisateur" = donateur + acheteur)
require_once __DIR__ . '/includes/functions.php';
if (est_connecte()) redirect('index.php');

$erreurs = [];
$vals = ['nom' => '', 'email' => '', 'telephone' => '', 'adresse' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    foreach ($vals as $k => $_) $vals[$k] = trim(champ_texte($_POST, $k));
    $mdp  = champ_texte($_POST, 'mot_de_passe');
    $mdp2 = champ_texte($_POST, 'mot_de_passe2');

    if ($vals['nom'] === '')                                $erreurs[] = t('err_name');
    if (!filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = t('err_email');
    if (!mot_de_passe_valide($mdp))                                   $erreurs[] = t('err_pwd_len');
    if ($mdp !== $mdp2)                                     $erreurs[] = t('err_pwd_match');

    if (mb_strlen($vals['nom']) > 100 || mb_strlen($vals['email']) > 150 || mb_strlen($vals['telephone']) > 30 || mb_strlen($vals['adresse']) > 255) $erreurs[] = t('admin_field_too_long');
    $retry = limiter_auth('register', $vals['email'], 5, 20, 3600);
    if ($retry) { reponse_limite($retry); $erreurs[] = t('auth_rate_limited'); }
    if (!$erreurs) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$vals['email']]);
        if ($stmt->fetch()) $erreurs[] = t('err_email_used');
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe, telephone, adresse) VALUES (?,?,?,?,?)');
        $stmt->execute([$vals['nom'], $vals['email'], password_hash($mdp, PASSWORD_DEFAULT), $vals['telephone'], $vals['adresse']]);
        flash('success', t('account_created'));
        redirect('login.php');
    }
}

$titre = t('register_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('register_title') ?></h1>
<p class="muted"><?= t('register_sub') ?></p>
<?php foreach ($erreurs as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<form class="form" method="post">
  <?= csrf_field() ?>
  <label><?= t('full_name') ?></label><input type="text" name="nom" value="<?= e($vals['nom']) ?>" required>
  <label><?= t('email') ?></label><input type="email" name="email" value="<?= e($vals['email']) ?>" required>
  <label><?= t('phone') ?></label><input type="tel" name="telephone" value="<?= e($vals['telephone']) ?>">
  <label><?= t('address') ?></label><input type="text" name="adresse" value="<?= e($vals['adresse']) ?>">
  <label><?= t('pwd_min') ?></label><input type="password" name="mot_de_passe" required>
  <label><?= t('confirm_pwd') ?></label><input type="password" name="mot_de_passe2" required>
  <button class="btn" type="submit"><?= t('create_my_account') ?></button>
</form>
<p><?= t('already') ?> <a href="login.php"><?= t('login_link') ?></a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
