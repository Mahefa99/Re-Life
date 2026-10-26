<?php
require_once __DIR__ . '/../includes/functions.php';
exiger_role('admin');

$type = $_GET['type'] ?? '';
if (!in_array($type, ['dons', 'commandes'], true)) {
    http_response_code(404); exit;
}
$id = (int)($_GET['id'] ?? 0);
$retour = $type . '.php';
$estCommande = $type === 'commandes';
$bloques = $estCommande ? ['en_livraison'] : ['collecte'];
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    try {
        $pdo->beginTransaction();
        // La table provient exclusivement de la liste autorisée ci-dessus.
        $s = $pdo->prepare("SELECT * FROM $type WHERE id = ? FOR UPDATE");
        $s->execute([$id]);
        $operation = $s->fetch();
        if (!$operation) {
            $pdo->rollBack();
            flash('error', t('operation_missing'));
            redirect($retour);
        }
        if (in_array($operation['statut'], $bloques, true)) {
            $pdo->rollBack();
            flash('error', t('operation_in_progress'));
            redirect($retour);
        }
        if ($estCommande && in_array($operation['statut'], ['en_attente', 'confirmee'], true)) {
            $s = $pdo->prepare('SELECT produit_id, SUM(quantite) AS quantite FROM commande_lignes WHERE commande_id = ? GROUP BY produit_id ORDER BY produit_id');
            $s->execute([$id]);
            $stock = $pdo->prepare('UPDATE produits SET stock = stock + ? WHERE id = ?');
            foreach ($s->fetchAll() as $ligne) $stock->execute([$ligne['quantite'], $ligne['produit_id']]);
        }
        $pdo->prepare("DELETE FROM $type WHERE id = ?")->execute([$id]);
        $pdo->commit();
        flash('success', t('operation_deleted'));
        redirect($retour);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $erreur = t('operation_delete_failed');
    }
}
$s = $pdo->prepare("SELECT o.*, u.nom FROM $type o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
$s->execute([$id]);
$operation = $s->fetch();
if (!$operation) { http_response_code(404); exit(t('operation_missing')); }
$base = '../'; $page = $type; $titre = t('operation_delete_title');
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($titre) ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>
<section class="form">
  <h2><?= e(t($estCommande ? 'admin_orders' : 'admin_donations')) ?> #<?= $id ?></h2>
  <p><?= e($operation['nom']) ?> · <?= e(libelle_statut($operation['statut'])) ?></p>
  <p><?= e($estCommande ? $operation['adresse_livraison'] : $operation['description']) ?></p>
  <?php if ($erreur): ?><p role="alert"><?= e($erreur) ?></p><?php endif; ?>
  <?php if (in_array($operation['statut'], $bloques, true)): ?>
    <p><?= e(t('operation_in_progress')) ?></p>
  <?php else: ?>
    <p><?= e(t('operation_delete_warning')) ?></p>
    <?php if ($estCommande): ?><p><?= e(t(in_array($operation['statut'], ['en_attente', 'confirmee'], true) ? 'operation_stock_restore' : 'operation_stock_unchanged')) ?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <button class="btn" type="submit"><?= e(t('operation_delete_confirm')) ?></button>
      <a class="btn btn-outline" href="<?= $retour ?>"><?= e(t('operation_cancel')) ?></a>
    </form>
  <?php endif; ?>
  <p><a href="<?= $retour ?>"><?= e(t('operation_back')) ?></a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
