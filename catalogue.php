<?php
// catalogue.php : boutique avec recherche et filtre par catégorie
require_once __DIR__ . '/includes/functions.php';
$titre = t('menu_shop');
require __DIR__ . '/includes/header.php';

$q   = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';
$categories = ['Homme', 'Femme', 'Enfant', 'Chaussures', 'Accessoires', 'Livres'];   // valeurs stockées en base

$sql = 'SELECT * FROM produits WHERE actif = 1 AND stock > 0';
$params = [];
if ($q !== '') {
    $sql .= ' AND (nom LIKE ? OR description LIKE ? OR auteur LIKE ? OR genre_livre LIKE ? OR isbn LIKE ? OR editeur LIKE ?)';
    for ($i = 0; $i < 6; $i++) $params[] = "%$q%";
}
if (in_array($cat, $categories, true)) { $sql .= ' AND categorie = ?'; $params[] = $cat; }
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produits = $stmt->fetchAll();
?>
<h1><?= t('shop_title') ?></h1>
<p class="muted"><?= t('shop_sub') ?></p>

<form class="filtres" method="get">
  <input type="text" name="q" placeholder="<?= e(t('search')) ?>" value="<?= e($q) ?>">
  <select name="cat">
    <option value=""><?= t('all_cats') ?></option>
    <?php foreach ($categories as $c): ?>
      <option value="<?= e($c) ?>" <?= $c === $cat ? 'selected' : '' ?>><?= e(t('cat_' . $c)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-small" type="submit"><?= t('filter') ?></button>
</form>

<?php if (!$produits): ?>
  <p class="muted"><?= t('none_found') ?></p>
<?php else: ?>
<div class="grille">
  <?php foreach ($produits as $p): ?>
    <a class="carte <?= $p['categorie'] === 'Livres' ? 'carte-livre' : 'carte-vetement' ?>" href="produit.php?id=<?= (int)$p['id'] ?>">
      <div class="photo"><?php if ($p['image']): ?><img src="uploads/<?= e(basename($p['image'])) ?>" alt="<?= e($p['nom']) ?>"><?php else: ?><span class="product-art <?= e(classe_visuel_produit($p)) ?>" role="img" aria-label="<?= e($p['nom']) ?>"></span><?php endif; ?><span class="favorite-mark" aria-hidden="true"><?= rewear_icon('heart') ?></span></div>
      <div class="corps">
        <h3><?= e($p['nom']) ?></h3>
        <?php if ($p['categorie'] === 'Livres'): ?>
          <p class="card-author"><?= e($p['auteur'] ?: t('book_author_unknown')) ?></p>
          <p class="card-book-meta"><?= e(implode(' · ', array_filter([$p['genre_livre'] ?? '', $p['taille'] ?? '']))) ?></p>
        <?php else: ?>
          <p class="card-book-meta"><?= e(t('cat_' . $p['categorie'])) ?><?php if ($p['taille']): ?> · <?= t('size') ?> <?= e($p['taille']) ?><?php endif; ?></p>
        <?php endif; ?>
        <div class="product-bottom"><span class="prix"><?= prix($p['prix']) ?></span><span class="reuse-mark" aria-hidden="true"><?= rewear_icon($p['categorie'] === 'Livres' ? 'book' : 'leaf') ?></span></div>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
