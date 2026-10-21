<?php
// Gestion complète des deux familles de produits, réservée aux administrateurs.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/product_validation.php';
exiger_role('admin');
$categories = ['Homme', 'Femme', 'Enfant', 'Chaussures', 'Accessoires', 'Livres'];
$etats = ['Comme neuf', 'Très bon état', 'Bon état'];
$erreurs = [];
$idEdition = (int)($_GET['edit'] ?? 0);
$afficherFormulaire = isset($_GET['ajouter']) || isset($_GET['edit']);
$edit = ['categorie' => ($_GET['ajouter'] ?? '') === 'livres' ? 'Livres' : 'Homme', 'etat' => 'Très bon état'];
$fiche = null;
$confirmerSuppression = isset($_GET['supprimer']);
$idLecture = (int)($_GET['voir'] ?? $_GET['supprimer'] ?? $_GET['edit'] ?? 0);
if (isset($_GET['voir']) || isset($_GET['supprimer']) || isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM produits WHERE id = ?');
    $s->execute([$idLecture]);
    $fiche = $s->fetch();
    if (!$fiche) {
        http_response_code(404);
        $base = '../'; $titre = t('admin_product_missing'); $page = 'produits';
        require __DIR__ . '/../includes/header.php';
        echo '<h1>' . t('admin_product_missing') . '</h1><a href="produits.php">' . t('admin_back_products') . '</a>';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
    if (isset($_GET['edit'])) { $edit = $fiche; $fiche = null; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'sauver') {
        $idEdition = $id;
        $afficherFormulaire = true;
        [$donnees, $erreurs] = valider_produit_admin($_POST);
        $edit = $donnees;
        $existant = null;
        if ($id !== 0) {
            $s = $pdo->prepare('SELECT * FROM produits WHERE id = ?');
            $s->execute([$id]);
            $existant = $s->fetch();
            if (!$existant) $erreurs[] = 'admin_product_missing';
            $edit['image'] = $existant['image'] ?? null;
        }
        $image = null;
        $upload = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!$erreurs && $upload !== UPLOAD_ERR_NO_FILE) {
            $image = enregistrer_image('image');
            if (!$image || !is_file(UPLOAD_DIR . $image)) $erreurs[] = 'admin_image_invalid';
        }
        if (!$erreurs) {
            try {
                $champs = array_keys($donnees);
                $valeurs = array_values($donnees);
                if ($image) { $champs[] = 'image'; $valeurs[] = $image; }
                if ($id) {
                    $sql = 'UPDATE produits SET ' . implode(', ', array_map(static fn($c) => $c . ' = ?', $champs)) . ' WHERE id = ?';
                    $valeurs[] = $id;
                } else {
                    $sql = 'INSERT INTO produits (' . implode(', ', $champs) . ') VALUES (' . implode(', ', array_fill(0, count($champs), '?')) . ')';
                }
                $pdo->prepare($sql)->execute($valeurs);
                if (!$id) $id = (int)$pdo->lastInsertId();
                flash('success', t($idEdition ? 'admin_product_updated' : 'admin_product_published'));
                redirect('produits.php?voir=' . $id);
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $erreurs[] = 'admin_product_failed';
            }
        }
    } elseif (in_array($action, ['basculer', 'supprimer'], true)) {
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare('SELECT id, actif FROM produits WHERE id = ? FOR UPDATE');
            $s->execute([$id]);
            $cible = $s->fetch();
            if (!$cible) {
                flash('error', t('admin_product_missing'));
            } elseif ($action === 'basculer') {
                $pdo->prepare('UPDATE produits SET actif = ? WHERE id = ?')->execute([(int)!$cible['actif'], $id]);
                flash('success', t('admin_product_visibility_updated'));
            } else {
                $s = $pdo->prepare('SELECT id FROM commande_lignes WHERE produit_id = ? LIMIT 1 FOR UPDATE');
                $s->execute([$id]);
                if ($s->fetch()) {
                    flash('error', t('admin_delete_ordered'));
                } else {
                    $pdo->prepare('DELETE FROM produits WHERE id = ?')->execute([$id]);
                    flash('success', t('admin_product_deleted'));
                }
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($exception->getMessage());
            flash('error', t('admin_product_failed'));
        }
        redirect('produits.php');
    } else {
        http_response_code(400);
        die(e(t('invalid_request')));
    }
}
$type = in_array($_GET['type'] ?? '', ['vetements', 'livres'], true) ? $_GET['type'] : '';
$recherche = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 150) : '';
$conditions = []; $parametres = [];
if ($type) $conditions[] = $type === 'livres' ? "categorie = 'Livres'" : "categorie <> 'Livres'";
if ($recherche !== '') {
    $conditions[] = '(nom LIKE ? OR auteur LIKE ? OR isbn LIKE ?)';
    $parametres = array_fill(0, 3, '%' . $recherche . '%');
}
$s = $pdo->prepare('SELECT * FROM produits' . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '') . ' ORDER BY created_at DESC, id DESC');
$s->execute($parametres);
$produits = $s->fetchAll();
$commandeLiee = false;
if ($fiche && $confirmerSuppression) {
    $s = $pdo->prepare('SELECT 1 FROM commande_lignes WHERE produit_id = ? LIMIT 1');
    $s->execute([$fiche['id']]);
    $commandeLiee = (bool)$s->fetchColumn();
}
$base = '../'; $titre = t('admin_products'); $page = 'produits';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= t('admin_products') ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>
<div class="product-admin-actions">
  <a class="btn" href="produits.php?ajouter=vetements"><?= rewear_icon('shirt') ?> <?= t('admin_add_clothing') ?></a>
  <a class="btn btn-outline" href="produits.php?ajouter=livres"><?= rewear_icon('book') ?> <?= t('admin_add_book') ?></a>
  <?php if ($fiche || $afficherFormulaire): ?><a href="produits.php"><?= t('admin_back_products') ?></a><?php endif; ?>
</div>
<?php if ($erreurs): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($erreurs as $erreur): ?><li><?= e(t($erreur)) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($fiche): ?>
<section class="product-admin-panel">
  <h2><?= e($fiche['nom']) ?></h2>
  <span class="badge"><?= t('cat_' . $fiche['categorie']) ?></span>
  <div class="detail">
    <div class="photo-grande"><?php if ($fiche['image']): ?><img src="../uploads/<?= e(basename($fiche['image'])) ?>" alt="<?= e($fiche['nom']) ?>"><?php else: ?><span class="product-art <?= e(classe_visuel_produit($fiche)) ?>" role="img" aria-label="<?= e($fiche['nom']) ?>"></span><?php endif; ?></div>
    <div>
      <p class="prix"><?= prix($fiche['prix']) ?></p>
      <p><?= nl2br(e($fiche['description'])) ?></p>
      <dl class="book-details">
        <div><dt><?= t('admin_condition') ?></dt><dd><?= t('etat_' . $fiche['etat']) ?></dd></div>
        <div><dt><?= t('admin_stock') ?></dt><dd><?= (int)$fiche['stock'] ?></dd></div>
        <div><dt><?= t('admin_visible') ?></dt><dd><?= t($fiche['actif'] ? 'admin_yes' : 'admin_no') ?></dd></div>
        <div><dt><?= t($fiche['categorie'] === 'Livres' ? 'book_format' : 'size') ?></dt><dd><?= e($fiche['taille'] ?: '—') ?></dd></div>
        <?php if ($fiche['categorie'] === 'Livres'): ?>
          <?php foreach (['auteur' => 'admin_author', 'genre_livre' => 'book_genre', 'isbn' => 'admin_isbn', 'editeur' => 'book_publisher', 'annee_publication' => 'book_year', 'langue_livre' => 'book_language', 'nombre_pages' => 'book_pages'] as $champ => $libelle): ?>
          <div><dt><?= t($libelle) ?></dt><dd><?= e($fiche[$champ] ?: '—') ?></dd></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </dl>
      <?php if ($confirmerSuppression): ?>
        <p><?= t($commandeLiee ? 'admin_delete_ordered' : 'admin_delete_confirm') ?></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$fiche['id'] ?>">
          <?php if (!$commandeLiee): ?><button class="btn btn-danger" name="action" value="supprimer"><?= t('admin_delete_permanently') ?></button>
          <?php elseif ($fiche['actif']): ?><button class="btn" name="action" value="basculer"><?= t('admin_hide') ?></button><?php endif; ?>
          <a class="btn btn-outline" href="produits.php?voir=<?= (int)$fiche['id'] ?>"><?= t('admin_cancel') ?></a>
        </form>
      <?php else: ?>
        <div class="product-admin-actions"><a class="btn" href="produits.php?edit=<?= (int)$fiche['id'] ?>"><?= t('admin_edit') ?></a><a class="btn btn-outline" href="produits.php?supprimer=<?= (int)$fiche['id'] ?>"><?= t('admin_delete') ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php elseif ($afficherFormulaire): ?>
<h2><?= $idEdition ? t('admin_edit_product') : t('admin_add_product') ?></h2>
<form class="form large" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sauver">
  <input type="hidden" name="id" value="<?= $idEdition ?>">
  <label for="nom-produit"><?= t('admin_name') ?></label><input id="nom-produit" maxlength="150" type="text" name="nom" value="<?= e($edit['nom'] ?? '') ?>" required>
  <label for="description-produit"><?= t('th_desc') ?></label><textarea id="description-produit" name="description" maxlength="15000"><?= e($edit['description'] ?? '') ?></textarea>
  <label for="categorie-produit"><?= t('admin_category') ?></label>
  <select name="categorie" id="categorie-produit"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>" <?= ($edit['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= t('cat_' . $c) ?></option><?php endforeach; ?></select>
  <div class="book-fields" data-book-fields>
    <p class="book-fields-title"><?= t('admin_book_info') ?></p>
    <div class="form-grid">
      <div class="field-wide"><label for="auteur"><?= t('admin_author') ?></label><input id="auteur" type="text" name="auteur" value="<?= e($edit['auteur'] ?? '') ?>" maxlength="150"></div>
      <div><label for="genre-livre"><?= t('book_genre') ?></label><input id="genre-livre" type="text" name="genre_livre" value="<?= e($edit['genre_livre'] ?? '') ?>" maxlength="80" placeholder="<?= t('admin_book_genre_placeholder') ?>"></div>
      <div><label for="isbn"><?= t('admin_isbn') ?></label><input id="isbn" type="text" name="isbn" value="<?= e($edit['isbn'] ?? '') ?>" maxlength="20" placeholder="978-…"></div>
      <div><label for="editeur"><?= t('book_publisher') ?></label><input id="editeur" type="text" name="editeur" value="<?= e($edit['editeur'] ?? '') ?>" maxlength="150"></div>
      <div><label for="annee-publication"><?= t('admin_publication_year') ?></label><input id="annee-publication" type="number" name="annee_publication" value="<?= e($edit['annee_publication'] ?? '') ?>" min="1000" max="<?= date('Y') + 1 ?>"></div>
      <div><label for="langue-livre"><?= t('book_language') ?></label><input id="langue-livre" type="text" name="langue_livre" value="<?= e($edit['langue_livre'] ?? '') ?>" maxlength="50"></div>
      <div><label for="nombre-pages"><?= t('admin_page_count') ?></label><input id="nombre-pages" type="number" name="nombre_pages" value="<?= e($edit['nombre_pages'] ?? '') ?>" min="1" max="50000"></div>
    </div>
  </div>
  <label for="etat-produit"><?= t('admin_condition') ?></label>
  <select id="etat-produit" name="etat"><?php foreach ($etats as $c): ?><option value="<?= e($c) ?>" <?= ($edit['etat'] ?? '') === $c ? 'selected' : '' ?>><?= t('etat_' . $c) ?></option><?php endforeach; ?></select>
  <label for="taille-produit" data-size-label data-size-text="<?= e(t('size')) ?>" data-format-text="<?= e(t('admin_format_optional')) ?>"><?= t('size') ?></label><input id="taille-produit" type="text" name="taille" value="<?= e($edit['taille'] ?? '') ?>" maxlength="20">
  <label for="prix-produit"><?= t('admin_price_rs') ?></label><input id="prix-produit" max="99999999.99" type="number" name="prix" step="0.01" min="0" value="<?= e($edit['prix'] ?? '') ?>" required>
  <label for="stock-produit"><?= t('admin_stock') ?></label><input id="stock-produit" max="1000000" type="number" name="stock" min="0" value="<?= e($edit['stock'] ?? 1) ?>" required>
  <label for="image-produit"><?= t('admin_photo_help') ?></label><input id="image-produit" type="file" name="image" accept="image/jpeg,image/png,image/webp">
  <?php if (!empty($edit['image'])): ?><p><img class="miniature" src="../uploads/<?= e(basename($edit['image'])) ?>" alt="<?= e($edit['nom']) ?>"> <?= t('admin_photo_kept') ?></p><?php endif; ?>
  <button class="btn" type="submit"><?= $idEdition ? t('save') : t('admin_publish') ?></button>
  <?php if ($idEdition): ?><a class="btn btn-outline" href="produits.php"><?= t('admin_cancel') ?></a><?php endif; ?>
</form>

<?php endif; ?>
<?php if (!$fiche && !$afficherFormulaire): ?>
<form class="product-admin-filter form" method="get">
  <div><label for="filtre-type"><?= t('admin_product_family') ?></label><select id="filtre-type" name="type">
    <option value=""><?= t('admin_products_all') ?></option>
    <option value="vetements" <?= $type === 'vetements' ? 'selected' : '' ?>><?= t('donation_clothes') ?></option>
    <option value="livres" <?= $type === 'livres' ? 'selected' : '' ?>><?= t('donation_books') ?></option>
  </select></div>
  <div><label for="recherche-produit"><?= t('admin_product_search') ?></label><input id="recherche-produit" name="q" type="text" value="<?= e($recherche) ?>" maxlength="150"></div>
  <button class="btn" type="submit"><?= t('admin_filter') ?></button>
  <a href="produits.php"><?= t('admin_reset_filter') ?></a>
</form>
<h2><?= t('admin_all_products', count($produits)) ?></h2>
<?php if (!$produits): ?><p class="alert alert-info"><?= t('admin_no_products') ?></p><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= t('admin_photo') ?></th><th><?= t('admin_name') ?></th><th><?= t('admin_category') ?></th><th><?= t('th_price') ?></th><th><?= t('admin_stock') ?></th><th><?= t('admin_visible') ?></th><th><?= t('admin_actions') ?></th></tr></thead>
  <tbody><?php foreach ($produits as $p): ?>
  <tr>
    <td><?php if ($p['image']): ?><img class="miniature" src="../uploads/<?= e(basename($p['image'])) ?>" alt=""><?php else: ?><span class="miniature product-art <?= e(classe_visuel_produit($p)) ?>" role="img" aria-label="<?= e($p['nom']) ?>"></span><?php endif; ?></td>
    <td><a href="produits.php?voir=<?= (int)$p['id'] ?>"><strong><?= e($p['nom']) ?></strong></a><?php if ($p['categorie'] === 'Livres'): ?><br><span class="muted"><?= e($p['auteur']) ?></span><?php endif; ?></td>
    <td><?= t('cat_' . $p['categorie']) ?></td><td><?= prix($p['prix']) ?></td><td><?= (int)$p['stock'] ?></td><td><?= t($p['actif'] ? 'admin_yes' : 'admin_no') ?></td>
    <td><div class="product-admin-row-actions">
      <a class="btn btn-small btn-outline" href="produits.php?voir=<?= (int)$p['id'] ?>"><?= t('admin_view') ?></a>
      <a class="btn btn-small btn-outline" href="produits.php?edit=<?= (int)$p['id'] ?>"><?= t('admin_edit') ?></a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="basculer"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-small btn-outline" type="submit"><?= t($p['actif'] ? 'admin_hide' : 'admin_show') ?></button></form>
      <a class="btn btn-small btn-danger" href="produits.php?supprimer=<?= (int)$p['id'] ?>"><?= t('admin_delete') ?></a>
    </div></td>
  </tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
