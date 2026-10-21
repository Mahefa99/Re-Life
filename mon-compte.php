<?php
// mon-compte.php : profil, sécurité, historique des commandes et des dons
require_once __DIR__ . '/includes/functions.php';
exiger_utilisateur();
$uid = (int)user()['id'];

$chargerCompte = static function () use ($pdo, $uid) {
    $stmt = $pdo->prepare('SELECT nom, email, telephone, adresse, mot_de_passe FROM users WHERE id = ? AND actif = 1');
    $stmt->execute([$uid]);
    return $stmt->fetch();
};

$compte = $chargerCompte();
if (!$compte) {
    unset($_SESSION['user']);
    flash('error', t('login_required'));
    redirect('login.php');
}

$erreursProfil = [];
$erreursMdp = [];
$valeursProfil = [
    'nom' => $compte['nom'],
    'email' => $compte['email'],
    'telephone' => $compte['telephone'] ?? '',
    'adresse' => $compte['adresse'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $action = $_POST['action'] ?? '';

    if ($action === 'profil') {
        foreach ($valeursProfil as $champ => $_) {
            $valeursProfil[$champ] = trim($_POST[$champ] ?? '');
        }

        if ($valeursProfil['nom'] === '') $erreursProfil[] = t('err_name');
        if (mb_strlen($valeursProfil['nom']) > 100) $erreursProfil[] = t('err_name_long');
        if (!filter_var($valeursProfil['email'], FILTER_VALIDATE_EMAIL)) $erreursProfil[] = t('err_email');
        if (mb_strlen($valeursProfil['email']) > 150) $erreursProfil[] = t('err_email_long');
        if (mb_strlen($valeursProfil['telephone']) > 30) $erreursProfil[] = t('err_phone_long');
        if (mb_strlen($valeursProfil['adresse']) > 255) $erreursProfil[] = t('err_address_long');

        if (!$erreursProfil) {
            $emailPris = $pdo->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
            $emailPris->execute([$valeursProfil['email'], $uid]);
            if ($emailPris->fetchColumn()) $erreursProfil[] = t('err_email_used');
        }

        if (!$erreursProfil) {
            try {
                $pdo->prepare('UPDATE users SET nom = ?, email = ?, telephone = ?, adresse = ? WHERE id = ?')
                    ->execute([$valeursProfil['nom'], $valeursProfil['email'], $valeursProfil['telephone'], $valeursProfil['adresse'], $uid]);
                $_SESSION['user']['nom'] = $valeursProfil['nom'];
                $_SESSION['user']['email'] = $valeursProfil['email'];
                flash('success', t('profile_saved'));
                redirect('mon-compte.php#profil');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $erreursProfil[] = t('err_email_used');
                } else {
                    error_log($e->getMessage());
                    $erreursProfil[] = t('save_error');
                }
            }
        }
    } elseif ($action === 'mot_de_passe') {
        $motActuel = $_POST['mot_de_passe_actuel'] ?? '';
        $nouveau = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirmation = $_POST['confirmation_mot_de_passe'] ?? '';

        if (!password_verify($motActuel, $compte['mot_de_passe'])) $erreursMdp[] = t('err_current_pwd');
        if (strlen($nouveau) < 8) $erreursMdp[] = t('err_pwd_len');
        if ($nouveau !== $confirmation) $erreursMdp[] = t('err_pwd_match');

        if (!$erreursMdp) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE users SET mot_de_passe = ? WHERE id = ?')
                    ->execute([password_hash($nouveau, PASSWORD_DEFAULT), $uid]);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$uid]);
                $pdo->commit();
                session_regenerate_id(true);
                flash('success', t('password_updated'));
                redirect('mon-compte.php#securite');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                $erreursMdp[] = t('save_error');
            }
        }
    }
}

$commandes = $pdo->prepare('SELECT * FROM commandes WHERE user_id = ? ORDER BY created_at DESC');
$commandes->execute([$uid]);
$commandes = $commandes->fetchAll();

$dons = $pdo->prepare('SELECT * FROM dons WHERE user_id = ? ORDER BY created_at DESC');
$dons->execute([$uid]);
$dons = $dons->fetchAll();

$titre = t('account_title');
require __DIR__ . '/includes/header.php';
?>
<header class="account-heading">
  <div>
    <h1><?= t('account_title') ?></h1>
    <p class="muted"><?= t('account_intro') ?></p>
  </div>
  <form method="post" action="logout.php"><?= csrf_field() ?><button class="btn btn-outline btn-small" type="submit"><?= t('logout') ?></button></form>
</header>

<div class="account-settings">
  <section class="account-panel" id="profil" aria-labelledby="profil-titre">
    <h2 id="profil-titre"><?= t('personal_info') ?></h2>
    <p class="muted"><?= t('personal_info_help') ?></p>
    <?php if ($erreursProfil): ?><div class="alert alert-error" role="alert"><?php foreach ($erreursProfil as $erreur): ?><div><?= e($erreur) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form class="form account-form" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="profil">
      <label for="profil-nom"><?= t('full_name') ?></label>
      <input id="profil-nom" type="text" name="nom" value="<?= e($valeursProfil['nom']) ?>" maxlength="100" autocomplete="name" required>
      <label for="profil-email"><?= t('email') ?></label>
      <input id="profil-email" type="email" name="email" value="<?= e($valeursProfil['email']) ?>" maxlength="150" autocomplete="email" required>
      <label for="profil-telephone"><?= t('phone') ?></label>
      <input id="profil-telephone" type="tel" name="telephone" value="<?= e($valeursProfil['telephone']) ?>" maxlength="30" autocomplete="tel">
      <label for="profil-adresse"><?= t('address') ?></label>
      <input id="profil-adresse" type="text" name="adresse" value="<?= e($valeursProfil['adresse']) ?>" maxlength="255" autocomplete="street-address">
      <button class="btn" type="submit"><?= t('save_profile') ?></button>
    </form>
  </section>

  <section class="account-panel" id="securite" aria-labelledby="securite-titre">
    <h2 id="securite-titre"><?= t('security') ?></h2>
    <p class="muted"><?= t('security_help') ?></p>
    <?php if ($erreursMdp): ?><div class="alert alert-error" role="alert"><?php foreach ($erreursMdp as $erreur): ?><div><?= e($erreur) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form class="form account-form" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="mot_de_passe">
      <label for="mot-de-passe-actuel"><?= t('current_password') ?></label>
      <input id="mot-de-passe-actuel" type="password" name="mot_de_passe_actuel" autocomplete="current-password" required>
      <label for="nouveau-mot-de-passe"><?= t('new_pwd') ?></label>
      <input id="nouveau-mot-de-passe" type="password" name="nouveau_mot_de_passe" minlength="8" autocomplete="new-password" required>
      <label for="confirmation-mot-de-passe"><?= t('confirm_pwd') ?></label>
      <input id="confirmation-mot-de-passe" type="password" name="confirmation_mot_de_passe" minlength="8" autocomplete="new-password" required>
      <button class="btn" type="submit"><?= t('change_password') ?></button>
    </form>
  </section>
</div>

<section class="account-history" aria-labelledby="commandes-titre">
<h2 id="commandes-titre"><?= t('my_orders') ?></h2>
<?php if (!$commandes): ?><p class="muted"><?= t('no_orders') ?></p><?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('th_no') ?></th><th><?= t('th_date') ?></th><th><?= t('th_total') ?></th><th><?= t('th_payment') ?></th><th><?= t('th_status') ?></th></tr>
  <?php foreach ($commandes as $c): ?>
  <tr><td>#<?= (int)$c['id'] ?></td><td><?= e(date('d/m/Y', strtotime($c['created_at']))) ?></td><td><?= prix($c['total']) ?></td>
      <td><?= e(libelle_paiement($c['mode_paiement'])) ?></td><td><span class="badge"><?= e(libelle_statut($c['statut'])) ?></span></td></tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
</section>

<section class="account-history" aria-labelledby="dons-titre">
<h2 id="dons-titre"><?= t('my_donations') ?></h2>
<?php if (!$dons): ?><p class="muted"><?= t('no_donations') ?> <a href="don.php"><?= t('btn_donate') ?></a></p><?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('th_date') ?></th><th><?= t('th_desc') ?></th><th><?= t('th_qty_short') ?></th><th><?= t('th_status') ?></th></tr>
  <?php foreach ($dons as $d): ?>
  <tr><td><?= e(date('d/m/Y', strtotime($d['created_at']))) ?></td><td><span class="badge"><?= e(libelle_type_don($d['type_don'] ?? 'vetements')) ?></span><br><?= e($d['description']) ?></td><td><?= (int)$d['quantite'] ?></td>
      <td><span class="badge"><?= e(libelle_statut($d['statut'])) ?></span></td></tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
