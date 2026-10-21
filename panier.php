<?php
// panier.php : voir, modifier et vider le panier
require_once __DIR__ . '/includes/functions.php';
exiger_utilisateur();

$anglais = langue() === 'en';

if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['id'] ?? 0);

    if ($action === 'maj') {
        $qte = (int)($_POST['quantite'] ?? 0);
        if ($pid < 1 || $qte <= 0) {
            unset($_SESSION['panier'][$pid]);
        } else {
            // Le stock peut avoir changé depuis l'affichage du panier : la session est
            // toujours ramenée à la quantité réellement disponible.
            $stmtProduit = $pdo->prepare('SELECT nom, stock, actif FROM produits WHERE id = ?');
            $stmtProduit->execute([$pid]);
            $produit = $stmtProduit->fetch();

            if (!$produit || !(int)$produit['actif'] || (int)$produit['stock'] < 1) {
                unset($_SESSION['panier'][$pid]);
                flash('error', $anglais
                    ? 'This product is no longer available and has been removed from your cart.'
                    : 'Ce produit n’est plus disponible et a été retiré de votre panier.');
            } else {
                $qteNormalisee = min($qte, (int)$produit['stock']);
                $_SESSION['panier'][$pid] = $qteNormalisee;
                if ($qteNormalisee < $qte) {
                    flash('info', $anglais
                        ? sprintf('The quantity of “%s” was adjusted to the available stock (%d).', $produit['nom'], $qteNormalisee)
                        : sprintf('La quantité de « %s » a été ajustée au stock disponible (%d).', $produit['nom'], $qteNormalisee));
                }
            }
        }
    } elseif ($action === 'retirer') {
        unset($_SESSION['panier'][$pid]);
    } elseif ($action === 'vider') {
        $_SESSION['panier'] = [];
    }
    redirect('panier.php');
}

// Charger tous les produits référencés par la session, y compris les produits
// désactivés, afin de pouvoir prévenir l'utilisateur avant de les retirer.
$lignes = [];
$avertissements = [];
$total = 0.0;
$panierSession = $_SESSION['panier'];
$ids = [];

foreach ($panierSession as $idSession => $qteSession) {
    $pid = (int)$idSession;
    if ($pid > 0 && (int)$qteSession > 0) {
        $ids[$pid] = $pid;
    } else {
        unset($_SESSION['panier'][$idSession]);
    }
}

$produitsParId = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE id IN ($in)");
    $stmt->execute(array_values($ids));
    foreach ($stmt->fetchAll() as $produit) {
        $produitsParId[(int)$produit['id']] = $produit;
    }
}

foreach ($panierSession as $idSession => $qteSession) {
    $pid = (int)$idSession;
    $qteDemandee = (int)$qteSession;
    if ($pid < 1 || $qteDemandee < 1) {
        continue;
    }

    $p = $produitsParId[$pid] ?? null;
    if (!$p) {
        unset($_SESSION['panier'][$idSession]);
        $avertissements[] = $anglais
            ? 'A product that no longer exists was removed from your cart.'
            : 'Un produit qui n’existe plus a été retiré de votre panier.';
        continue;
    }

    if (!(int)$p['actif'] || (int)$p['stock'] < 1) {
        unset($_SESSION['panier'][$idSession]);
        $avertissements[] = $anglais
            ? sprintf('“%s” is no longer available and was removed from your cart.', $p['nom'])
            : sprintf('« %s » n’est plus disponible et a été retiré de votre panier.', $p['nom']);
        continue;
    }

    $qte = min($qteDemandee, (int)$p['stock']);
    $_SESSION['panier'][$pid] = $qte;
    if ($qte < $qteDemandee) {
        $avertissements[] = $anglais
            ? sprintf('The quantity of “%s” was adjusted to the available stock (%d).', $p['nom'], $qte)
            : sprintf('La quantité de « %s » a été ajustée au stock disponible (%d).', $p['nom'], $qte);
    }

    $sous = $qte * (float)$p['prix'];
    $total += $sous;
    $lignes[] = ['p' => $p, 'qte' => $qte, 'sous' => $sous];
}

$titre = t('cart_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('cart_title') ?></h1>

<?php if ($avertissements): ?>
  <div class="alert alert-info" role="status">
    <?php foreach (array_unique($avertissements) as $avertissement): ?>
      <div><?= e($avertissement) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$lignes): ?>
  <p><?= t('cart_empty') ?> <a href="catalogue.php"><?= t('discover_shop') ?></a></p>
<?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('th_product') ?></th><th><?= t('th_price') ?></th><th><?= t('th_qty') ?></th><th><?= t('th_subtotal') ?></th><th></th></tr>
  <?php foreach ($lignes as $l): ?>
  <tr>
    <td>
      <a href="produit.php?id=<?= (int)$l['p']['id'] ?>"><?= e($l['p']['nom']) ?></a>
      <?php if (($l['p']['categorie'] ?? '') === 'Livres'): ?>
        <small class="muted" style="display:block">
          <?= !empty($l['p']['auteur']) ? e(t('by_author') . ' ' . $l['p']['auteur']) : e(t('book_author_unknown')) ?>
          <?php if (!empty($l['p']['taille'])): ?> · <?= e(t('book_format')) ?> : <?= e($l['p']['taille']) ?><?php endif; ?>
        </small>
      <?php endif; ?>
    </td>
    <td><?= prix($l['p']['prix']) ?></td>
    <td>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="maj"><input type="hidden" name="id" value="<?= (int)$l['p']['id'] ?>">
        <input type="number" name="quantite" value="<?= (int)$l['qte'] ?>" min="0" max="<?= (int)$l['p']['stock'] ?>" style="width:70px;padding:6px">
        <button class="btn btn-small btn-outline" type="submit"><?= t('ok') ?></button>
      </form>
    </td>
    <td><?= prix($l['sous']) ?></td>
    <td>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="retirer"><input type="hidden" name="id" value="<?= (int)$l['p']['id'] ?>">
        <button class="btn btn-small btn-danger" type="submit"><?= t('remove') ?></button></form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<h2><?= t('total') ?> : <?= prix($total) ?></h2>
<div style="display:flex;gap:10px;flex-wrap:wrap">
  <a class="btn" href="commande.php"><?= t('checkout_btn') ?></a>
  <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="vider"><button class="btn btn-outline" type="submit"><?= t('clear_cart') ?></button></form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
