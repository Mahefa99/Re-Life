<?php
// don.php : formulaire de don (vêtements et livres). Compte obligatoire avant de soumettre.
require_once __DIR__ . '/includes/functions.php';

$erreurs = [];
$typesDonActifs = ['vetements'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typesRecus = $_POST['types_don'] ?? [];
    if (!is_array($typesRecus)) $typesRecus = [];
    $typesRecus = array_values(array_filter($typesRecus, 'is_string'));
    $typesDonActifs = array_values(array_intersect(['vetements', 'livres'], array_unique($typesRecus)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    exiger_connexion();
    if (user()['role'] !== 'utilisateur') {
        flash('error', t('only_users_donate'));
        redirect('index.php');
    }
    $typeDon = count($typesDonActifs) === 2 ? 'mixte' : ($typesDonActifs[0] ?? null);
    $desc    = trim($_POST['description'] ?? '');
    $qte     = min(10000, max(1, (int)($_POST['quantite'] ?? 1)));
    $adresse = trim($_POST['adresse_collecte'] ?? '');
    $date    = $_POST['date_souhaitee'] ?? '';
    if ($date !== '') {
        $dateValide = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$dateValide || $dateValide->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
            $erreurs[] = t('err_donation_date');
        }
    } else {
        $date = null;
    }

    if ($typeDon === null) $erreurs[] = t('err_donation_type');
    if ($desc === '' || $adresse === '') $erreurs[] = t('err_donate');
    if (mb_strlen($desc) > 2000) $erreurs[] = t('err_donation_desc_long');
    if (mb_strlen($adresse) > 255) $erreurs[] = t('err_address_long');

    if (!$erreurs) {
        $pdo->prepare('INSERT INTO dons (user_id, type_don, description, quantite, adresse_collecte, date_souhaitee) VALUES (?,?,?,?,?,?)')
            ->execute([user()['id'], $typeDon, $desc, $qte, $adresse, $date]);
        flash('success', t('donate_ok'));
        redirect('mon-compte.php');
    }
}

$adresseDefaut = '';
if (est_connecte()) {
    $s = $pdo->prepare('SELECT adresse FROM users WHERE id = ?');
    $s->execute([user()['id']]);
    $adresseDefaut = $s->fetchColumn() ?: '';
}

$titre = t('donate_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('donate_title') ?></h1>
<p class="muted"><?= t('donate_sub') ?></p>
<?php if (!est_connecte()): ?>
  <div class="alert alert-info"><?= t('donate_need', '<a href="login.php">' . t('login_lc') . '</a>', '<a href="register.php">' . t('register_lc') . '</a>') ?></div>
<?php elseif (user()['role'] !== 'utilisateur'): ?>
  <div class="alert alert-info"><?= t('only_users_donate') ?></div>
<?php endif; ?>
<?php if ($erreurs): ?><div class="alert alert-error" role="alert"><?php foreach ($erreurs as $erreur): ?><div><?= e($erreur) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!est_connecte() || user()['role'] === 'utilisateur'): ?>
<form class="form" method="post">
  <?= csrf_field() ?>
  <?php
    $donVetements = in_array('vetements', $typesDonActifs, true);
    $donLivres = in_array('livres', $typesDonActifs, true);
    $modeDescription = $donVetements && $donLivres ? 'mixed' : ($donLivres ? 'books' : 'clothes');
  ?>
  <fieldset class="donation-fieldset" data-donation-group data-required-message="<?= e(t('err_donation_type')) ?>">
  <legend class="donation-type-label"><?= t('donation_type') ?></legend>
  <div class="donation-types">
    <label class="donation-choice">
      <input type="checkbox" name="types_don[]" value="vetements" <?= $donVetements ? 'checked' : '' ?>>
      <span class="donation-choice-content"><?= rewear_icon('shirt') ?><span><strong><?= t('donation_clothes') ?></strong><small><?= t('donation_clothes_help') ?></small></span></span>
    </label>
    <label class="donation-choice">
      <input type="checkbox" name="types_don[]" value="livres" <?= $donLivres ? 'checked' : '' ?>>
      <span class="donation-choice-content"><?= rewear_icon('book') ?><span><strong><?= t('donation_books') ?></strong><small><?= t('donation_books_help') ?></small></span></span>
    </label>
  </div>
  <p class="donation-type-error" data-donation-type-error role="alert" hidden><?= e(t('err_donation_type')) ?></p>
  </fieldset>
  <label for="description-don" data-donation-description-label data-clothes="<?= e(t('what_donate')) ?>" data-books="<?= e(t('what_books')) ?>" data-mixed="<?= e(t('what_mixed')) ?>"><?= e(t('what_' . ($modeDescription === 'clothes' ? 'donate' : $modeDescription))) ?></label>
  <textarea id="description-don" name="description" maxlength="2000" data-donation-description data-clothes-placeholder="<?= e(t('what_ph')) ?>" data-books-placeholder="<?= e(t('what_books_ph')) ?>" data-mixed-placeholder="<?= e(t('what_mixed_ph')) ?>" placeholder="<?= e($modeDescription === 'mixed' ? t('what_mixed_ph') : ($modeDescription === 'books' ? t('what_books_ph') : t('what_ph'))) ?>" required><?= e($_POST['description'] ?? '') ?></textarea>
  <label><?= t('nb_items') ?></label>
  <input type="number" name="quantite" min="1" max="10000" value="<?= (int)($_POST['quantite'] ?? 1) ?>">
  <label><?= t('collect_address') ?></label>
  <input type="text" name="adresse_collecte" value="<?= e($_POST['adresse_collecte'] ?? $adresseDefaut) ?>" maxlength="255" autocomplete="street-address" required>
  <label><?= t('wish_date') ?></label>
  <input type="date" name="date_souhaitee" min="<?= date('Y-m-d') ?>">
  <button class="btn" type="submit"><?= t('send_donation') ?></button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
