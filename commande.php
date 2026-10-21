<?php
// commande.php : validation de la commande (transaction SQL pour garder le stock cohérent)
require_once __DIR__ . '/includes/functions.php';
exiger_utilisateur();

$anglais = langue() === 'en';
$messageCommandeVide = $anglais
    ? 'Your order cannot be confirmed because it contains no available product.'
    : 'Votre commande ne peut pas être confirmée car elle ne contient aucun produit disponible.';
$messageTotalInvalide = $anglais
    ? 'An order with a total of Rs 0 cannot be confirmed.'
    : 'Une commande dont le total est de Rs 0 ne peut pas être confirmée.';

if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier']) || !$_SESSION['panier']) {
    $_SESSION['panier'] = [];
    flash('error', t('cart_empty'));
    redirect('catalogue.php');
}

$stmtU = $pdo->prepare('SELECT adresse FROM users WHERE id = ?');
$stmtU->execute([user()['id']]);
$adresseDefaut = $stmtU->fetchColumn() ?: '';

// Recalculer et normaliser le panier depuis la base. Les produits inactifs ou
// épuisés restent visibles dans l'avertissement avant d'être retirés de la session.
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

if (!$ids) {
    flash('error', $messageCommandeVide);
    redirect('panier.php');
}

$in = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT * FROM produits WHERE id IN ($in)");
$stmt->execute(array_values($ids));
$produitsParId = [];
foreach ($stmt->fetchAll() as $produit) {
    $produitsParId[(int)$produit['id']] = $produit;
}

$produits = [];
$avertissements = [];
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
            ? 'A product that no longer exists was removed from your order.'
            : 'Un produit qui n’existe plus a été retiré de votre commande.';
        continue;
    }

    if (!(int)$p['actif'] || (int)$p['stock'] < 1) {
        unset($_SESSION['panier'][$idSession]);
        $avertissements[] = $anglais
            ? sprintf('“%s” is no longer available and was removed from your order.', $p['nom'])
            : sprintf('« %s » n’est plus disponible et a été retiré de votre commande.', $p['nom']);
        continue;
    }

    $qte = min($qteDemandee, (int)$p['stock']);
    $_SESSION['panier'][$pid] = $qte;
    if ($qte < $qteDemandee) {
        $avertissements[] = $anglais
            ? sprintf('The quantity of “%s” was adjusted to the available stock (%d).', $p['nom'], $qte)
            : sprintf('La quantité de « %s » a été ajustée au stock disponible (%d).', $p['nom'], $qte);
    }
    $produits[] = $p;
}

if (!$produits) {
    flash('error', $avertissements ? implode(' ', array_unique($avertissements)) : $messageCommandeVide);
    redirect('panier.php');
}

// Préparer le récapitulatif à partir des quantités normalisées.
$resumeLignes = [];
$total = 0.0;
foreach ($produits as $p) {
    $qte = (int)($_SESSION['panier'][(int)$p['id']] ?? 0);
    if ($qte < 1) {
        continue;
    }
    $sousTotal = $qte * (float)$p['prix'];
    $total += $sousTotal;
    $resumeLignes[] = ['p' => $p, 'qte' => $qte, 'sous' => $sousTotal];
}

$erreur = '';
$commandeBloquee = !$resumeLignes || $total <= 0;
if (!$resumeLignes) {
    $erreur = $messageCommandeVide;
} elseif ($total <= 0) {
    $erreur = $messageTotalInvalide;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $adresse = trim($_POST['adresse'] ?? '');
    // 'livraison' = paiement en liquide à la livraison, 'carte' = paiement en ligne (simulé)
    $mode = in_array($_POST['mode_paiement'] ?? '', ['livraison', 'carte'], true) ? $_POST['mode_paiement'] : 'livraison';

    if (!$resumeLignes) {
        $erreur = $messageCommandeVide;
    } elseif ($total <= 0) {
        $erreur = $messageTotalInvalide;
    } elseif ($adresse === '') {
        $erreur = t('err_address');
    } else {
        try {
            $pdo->beginTransaction();
            $totalVerrouille = 0.0;
            $lignes = [];

            foreach ($produits as $p) {
                $pid = (int)$p['id'];
                $qte = (int)($_SESSION['panier'][$pid] ?? 0);
                if ($qte < 1) {
                    continue;
                }

                // Verrouiller la ligne pour éviter que deux personnes achètent la dernière pièce.
                // L'état actif est revérifié dans la transaction, au dernier moment.
                $s = $pdo->prepare('SELECT stock, prix, actif FROM produits WHERE id = ? FOR UPDATE');
                $s->execute([$pid]);
                $frais = $s->fetch();

                if (!$frais || !(int)$frais['actif'] || (int)$frais['stock'] < 1) {
                    unset($_SESSION['panier'][$pid]);
                    throw new Exception($anglais
                        ? sprintf('“%s” is no longer available and was removed from your cart.', $p['nom'])
                        : sprintf('« %s » n’est plus disponible et a été retiré de votre panier.', $p['nom']));
                }
                if ($qte > (int)$frais['stock']) {
                    $_SESSION['panier'][$pid] = (int)$frais['stock'];
                    throw new Exception(t('err_stock', $p['nom']));
                }

                $prixUnitaire = (float)$frais['prix'];
                $totalVerrouille += $qte * $prixUnitaire;
                $lignes[] = [$pid, $qte, $prixUnitaire];
            }

            if (!$lignes) {
                throw new Exception($messageCommandeVide);
            }
            if ($totalVerrouille <= 0) {
                throw new Exception($messageTotalInvalide);
            }

            $pdo->prepare('INSERT INTO commandes (user_id, total, adresse_livraison, mode_paiement) VALUES (?,?,?,?)')
                ->execute([user()['id'], $totalVerrouille, $adresse, $mode]);
            $cid = $pdo->lastInsertId();
            $insL = $pdo->prepare('INSERT INTO commande_lignes (commande_id, produit_id, quantite, prix_unitaire) VALUES (?,?,?,?)');
            $maj = $pdo->prepare('UPDATE produits SET stock = stock - ? WHERE id = ? AND actif = 1 AND stock >= ?');
            foreach ($lignes as [$pid, $qte, $pu]) {
                $insL->execute([$cid, $pid, $qte, $pu]);
                $maj->execute([$qte, $pid, $qte]);
                if ($maj->rowCount() !== 1) {
                    throw new Exception($anglais
                        ? 'The stock changed while the order was being confirmed. Please check your cart.'
                        : 'Le stock a changé pendant la validation. Vérifiez votre panier.');
                }
            }
            $pdo->commit();
            $_SESSION['panier'] = [];
            flash('success', t('order_ok', (int)$cid));
            redirect('mon-compte.php');
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $ex->getMessage());
            redirect('commande.php');
        }
    }
}

$titre = t('order_title');
require __DIR__ . '/includes/header.php';
?>
<h1><?= t('order_title') ?></h1>

<?php if ($avertissements): ?>
  <div class="alert alert-info" role="status">
    <?php foreach (array_unique($avertissements) as $avertissement): ?>
      <div><?= e($avertissement) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>

<h2><?= $anglais ? 'Order summary' : 'Récapitulatif de la commande' ?></h2>
<div class="table-wrap"><table>
  <tr><th><?= t('th_product') ?></th><th><?= t('th_price') ?></th><th><?= t('th_qty') ?></th><th><?= t('th_subtotal') ?></th></tr>
  <?php foreach ($resumeLignes as $ligne): ?>
    <tr>
      <td>
        <a href="produit.php?id=<?= (int)$ligne['p']['id'] ?>"><?= e($ligne['p']['nom']) ?></a>
        <?php if (($ligne['p']['categorie'] ?? '') === 'Livres'): ?>
          <small class="muted" style="display:block">
            <?= !empty($ligne['p']['auteur']) ? e(t('by_author') . ' ' . $ligne['p']['auteur']) : e(t('book_author_unknown')) ?>
            <?php if (!empty($ligne['p']['taille'])): ?> · <?= e(t('book_format')) ?> : <?= e($ligne['p']['taille']) ?><?php endif; ?>
          </small>
        <?php endif; ?>
      </td>
      <td><?= prix($ligne['p']['prix']) ?></td>
      <td><?= (int)$ligne['qte'] ?></td>
      <td><?= prix($ligne['sous']) ?></td>
    </tr>
  <?php endforeach; ?>
</table></div>

<p><?= t('total_to_pay') ?> : <strong><?= prix($total) ?></strong></p>
<form class="form" method="post">
  <?= csrf_field() ?>
  <label><?= t('delivery_address') ?></label>
  <input type="text" name="adresse" value="<?= e($_POST['adresse'] ?? $adresseDefaut) ?>" required>
  <label><?= t('pay_method') ?></label>
  <select name="mode_paiement">
    <option value="livraison" <?= ($_POST['mode_paiement'] ?? '') === 'livraison' ? 'selected' : '' ?>><?= t('pay_cash') ?></option>
    <option value="carte" <?= ($_POST['mode_paiement'] ?? '') === 'carte' ? 'selected' : '' ?>><?= t('pay_online') ?></option>
  </select>
  <p class="muted"><?= t('pay_online_note') ?></p>
  <button class="btn" type="submit" <?= $commandeBloquee ? 'disabled aria-disabled="true"' : '' ?>><?= t('confirm_order') ?></button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
