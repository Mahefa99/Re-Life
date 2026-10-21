<?php
// produit.php : fiche détaillée d'un produit + ajout au panier
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM produits WHERE id = ? AND actif = 1');
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); die('404'); }

// Ajout au panier (connexion obligatoire)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    exiger_connexion();
    if (user()['role'] !== 'utilisateur') {
        flash('error', t('only_users_buy'));
        redirect('produit.php?id=' . $id);
    }
    $qte = max(1, (int)($_POST['quantite'] ?? 1));
    $deja = $_SESSION['panier'][$id] ?? 0;
    $_SESSION['panier'][$id] = min($p['stock'], $deja + $qte);   // jamais plus que le stock
    flash('success', t('added_cart'));
    redirect('panier.php');
}

$titre = $p['nom'];
require __DIR__ . '/includes/header.php';
?>
<p><a href="catalogue.php"><?= t('back_shop') ?></a></p>
<div class="detail">
  <div class="photo-grande"><?php if ($p['image']): ?><img src="uploads/<?= e(basename($p['image'])) ?>" alt="<?= e($p['nom']) ?>"><?php else: ?><span class="product-art <?= e(classe_visuel_produit($p)) ?>" role="img" aria-label="<?= e($p['nom']) ?>"></span><?php endif; ?></div>
  <div>
    <h1><?= e($p['nom']) ?></h1>
    <?php if ($p['categorie'] === 'Livres'): ?><p class="book-author"><?= t('by_author') ?> <?= e($p['auteur'] ?: t('book_author_unknown')) ?></p><?php endif; ?>
    <p><span class="badge"><?= e(t('cat_' . $p['categorie'])) ?></span> <span class="badge badge-gris"><?= e(t('etat_' . $p['etat'])) ?></span></p>
    <p class="prix" style="font-size:1.6rem"><?= prix($p['prix']) ?></p>
    <p><?= nl2br(e($p['description'])) ?></p>
    <?php if ($p['categorie'] === 'Livres'): ?>
      <dl class="book-details">
        <?php if ($p['genre_livre']): ?><div><dt><?= t('book_genre') ?></dt><dd><?= e($p['genre_livre']) ?></dd></div><?php endif; ?>
        <?php if ($p['editeur']): ?><div><dt><?= t('book_publisher') ?></dt><dd><?= e($p['editeur']) ?></dd></div><?php endif; ?>
        <?php if ($p['annee_publication']): ?><div><dt><?= t('book_year') ?></dt><dd><?= (int)$p['annee_publication'] ?></dd></div><?php endif; ?>
        <?php if ($p['langue_livre']): ?><div><dt><?= t('book_language') ?></dt><dd><?= e($p['langue_livre']) ?></dd></div><?php endif; ?>
        <?php if ($p['nombre_pages']): ?><div><dt><?= t('book_pages') ?></dt><dd><?= (int)$p['nombre_pages'] ?></dd></div><?php endif; ?>
        <?php if ($p['isbn']): ?><div><dt>ISBN</dt><dd><?= e($p['isbn']) ?></dd></div><?php endif; ?>
        <?php if ($p['taille']): ?><div><dt><?= t('book_format') ?></dt><dd><?= e($p['taille']) ?></dd></div><?php endif; ?>
      </dl>
      <p class="muted"><?= t('in_stock', (int)$p['stock']) ?></p>
    <?php else: ?>
      <p class="muted"><?= t('size') ?> : <?= e($p['taille'] ?: '—') ?> · <?= t('in_stock', (int)$p['stock']) ?></p>
    <?php endif; ?>
    <?php if ($p['stock'] > 0 && (!est_connecte() || user()['role'] === 'utilisateur')): ?>
      <form class="purchase-form" method="post">
        <?= csrf_field() ?>
        <label><?= t('qty') ?> <input type="number" name="quantite" value="1" min="1" max="<?= (int)$p['stock'] ?>"></label>
        <button class="btn" type="submit"><?= rewear_icon('cart') ?> <?= t('add_cart') ?></button>
      </form>
      <?php if (!est_connecte()): ?><p class="muted"><?= t('need_account') ?></p><?php endif; ?>
    <?php elseif ($p['stock'] <= 0): ?>
      <p class="alert alert-error"><?= t('out_of_stock') ?></p>
    <?php else: ?>
      <p class="alert alert-info"><?= t('only_users_buy') ?></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
