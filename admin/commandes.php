<?php
// admin/commandes.php : suivi des commandes, changement de statut, assignation d'un livreur
require_once __DIR__ . '/../includes/functions.php';
exiger_role('admin');

$statuts = ['en_attente', 'confirmee', 'en_livraison', 'livree', 'annulee'];
$transitions = [
    'en_attente'    => ['en_attente', 'confirmee', 'annulee'],
    'confirmee'     => ['confirmee', 'en_livraison', 'annulee'],
    'en_livraison' => ['en_livraison', 'livree'],
    'livree'        => ['livree'],
    'annulee'       => ['annulee'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $id = (int)($_POST['id'] ?? 0);
    $statut = $_POST['statut'] ?? '';
    $livreur = (int)($_POST['livreur_id'] ?? 0) ?: null;
    if ($id <= 0 || !in_array($statut, $statuts, true)) {
        flash('error', t('admin_update_invalid'));
        redirect('commandes.php');
    }

    try {
        $pdo->beginTransaction();

        // Le verrou empêche deux administrateurs d'annuler et de remettre le même stock deux fois.
        $s = $pdo->prepare('SELECT statut FROM commandes WHERE id = ? FOR UPDATE');
        $s->execute([$id]);
        $ancien = $s->fetchColumn();

        if ($ancien === false) {
            $pdo->rollBack();
            flash('error', t('admin_order_not_found'));
            redirect('commandes.php');
        }
        if (in_array($ancien, ['livree', 'annulee'], true)) {
            $pdo->rollBack();
            flash('error', t('admin_order_locked'));
            redirect('commandes.php');
        }
        if (!in_array($statut, $transitions[$ancien] ?? [], true)) {
            $pdo->rollBack();
            flash('error', t('admin_order_transition_invalid'));
            redirect('commandes.php');
        }

        if ($livreur !== null) {
            $s = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'livreur' AND actif = 1 FOR UPDATE");
            $s->execute([$livreur]);
            if ($s->fetchColumn() === false) {
                $pdo->rollBack();
                flash('error', t('admin_courier_invalid'));
                redirect('commandes.php');
            }
        }
        if (in_array($statut, ['en_livraison', 'livree'], true) && $livreur === null) {
            $pdo->rollBack();
            flash('error', t('admin_order_courier_required'));
            redirect('commandes.php');
        }

        if ($statut === 'annulee') {
            $pdo->prepare('UPDATE produits p JOIN commande_lignes l ON l.produit_id = p.id SET p.stock = p.stock + l.quantite WHERE l.commande_id = ?')->execute([$id]);
            $livreur = null;
        }

        $s = $pdo->prepare('UPDATE commandes SET statut = ?, livreur_id = ? WHERE id = ? AND statut = ?');
        $s->execute([$statut, $livreur, $id, $ancien]);
        $pdo->commit();
        flash('success', t('admin_order_updated'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', t('admin_order_update_failed'));
    }
    redirect('commandes.php');
}

$livreurs = $pdo->query("SELECT id, nom FROM users WHERE role = 'livreur' AND actif = 1 ORDER BY nom")->fetchAll();
$aTraiter = ($_GET['filtre'] ?? '') === 'a_traiter';
$commandes = $pdo->query('SELECT c.*, u.nom AS client, u.telephone, l.nom AS livreur_nom FROM commandes c JOIN users u ON u.id = c.user_id LEFT JOIN users l ON l.id = c.livreur_id'
    . ($aTraiter ? " WHERE c.statut IN ('en_attente', 'confirmee')" : '') . ' ORDER BY c.created_at DESC')->fetchAll();
$detail = $pdo->prepare('SELECT p.nom, l.quantite FROM commande_lignes l JOIN produits p ON p.id = l.produit_id WHERE l.commande_id = ?');

$base = '../'; $titre = t('admin_orders'); $page = 'commandes';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= t('admin_orders') ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>
<?php if ($aTraiter): ?><p><?= t('admin_stat_orders') ?> · <a href="commandes.php"><?= t('admin_show_all') ?></a></p><?php endif; ?>
<?php if (!$commandes): ?><p class="muted"><?= t('admin_no_orders') ?></p><?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('th_no') ?></th><th><?= t('admin_th_client') ?></th><th><?= t('admin_th_items') ?></th><th><?= t('th_total') ?></th><th><?= t('admin_th_delivery') ?></th><th><?= t('admin_th_status_courier') ?></th></tr>
  <?php foreach ($commandes as $c): $detail->execute([$c['id']]); ?>
  <tr>
    <td>#<?= (int)$c['id'] ?><br><span class="muted"><?= e(date('d/m/Y', strtotime($c['created_at']))) ?></span></td>
    <td><?= e($c['client']) ?><br><span class="muted"><?= e($c['telephone']) ?></span></td>
    <td><?php foreach ($detail->fetchAll() as $l): ?><?= e($l['nom']) ?> × <?= (int)$l['quantite'] ?><br><?php endforeach; ?></td>
    <td><?= prix($c['total']) ?><br><span class="muted"><?= libelle_paiement($c['mode_paiement']) ?></span></td>
    <td><?= e($c['adresse_livraison']) ?></td>
    <td>
      <?php if (in_array($c['statut'], ['livree', 'annulee'], true)): ?>
        <span class="badge"><?= e(libelle_statut($c['statut'])) ?></span>
        <?php if ($c['livreur_nom']): ?><br><span class="muted"><?= t('admin_courier') ?> : <?= e($c['livreur_nom']) ?></span><?php endif; ?>
      <?php else: ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <select name="statut"><?php foreach ($transitions[$c['statut']] ?? [$c['statut']] as $s): ?><option value="<?= $s ?>" <?= $c['statut'] === $s ? 'selected' : '' ?>><?= e(libelle_statut($s)) ?></option><?php endforeach; ?></select>
        <select name="livreur_id"><option value="">— <?= t('admin_courier') ?> —</option>
          <?php foreach ($livreurs as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$c['livreur_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['nom']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-small" type="submit"><?= t('ok') ?></button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
