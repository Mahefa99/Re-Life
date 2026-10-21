<?php
// admin/dons.php : liste des dons à collecter, assignation d'un livreur, changement de statut
require_once __DIR__ . '/../includes/functions.php';
exiger_role('admin');

$statuts = ['en_attente', 'assigne', 'collecte', 'recu', 'refuse'];
$transitions = [
    'en_attente' => ['en_attente', 'assigne', 'refuse'],
    'assigne'    => ['assigne', 'collecte', 'refuse'],
    'collecte'   => ['collecte', 'recu'],
    'recu'       => ['recu'],
    'refuse'     => ['refuse'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $id = (int)($_POST['id'] ?? 0);
    $statut = $_POST['statut'] ?? '';
    $livreur = (int)($_POST['livreur_id'] ?? 0) ?: null;
    if ($id <= 0 || !in_array($statut, $statuts, true)) {
        flash('error', t('admin_update_invalid'));
        redirect('dons.php');
    }

    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT statut FROM dons WHERE id = ? FOR UPDATE');
        $s->execute([$id]);
        $ancien = $s->fetchColumn();

        if ($ancien === false) {
            $pdo->rollBack();
            flash('error', t('admin_donation_not_found'));
            redirect('dons.php');
        }
        if (in_array($ancien, ['recu', 'refuse'], true)) {
            $pdo->rollBack();
            flash('error', t('admin_donation_locked'));
            redirect('dons.php');
        }

        // Choisir un livreur depuis l'état initial crée directement une mission assignée.
        if ($livreur !== null && $ancien === 'en_attente' && $statut === 'en_attente') {
            $statut = 'assigne';
        }
        if (!in_array($statut, $transitions[$ancien] ?? [], true)) {
            $pdo->rollBack();
            flash('error', t('admin_donation_transition_invalid'));
            redirect('dons.php');
        }

        if ($livreur !== null) {
            $s = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'livreur' AND actif = 1 FOR UPDATE");
            $s->execute([$livreur]);
            if ($s->fetchColumn() === false) {
                $pdo->rollBack();
                flash('error', t('admin_courier_invalid'));
                redirect('dons.php');
            }
        }
        if (in_array($statut, ['assigne', 'collecte', 'recu'], true) && $livreur === null) {
            $pdo->rollBack();
            flash('error', t('admin_donation_courier_required'));
            redirect('dons.php');
        }
        if ($statut === 'refuse') $livreur = null;

        $s = $pdo->prepare('UPDATE dons SET statut = ?, livreur_id = ? WHERE id = ? AND statut = ?');
        $s->execute([$statut, $livreur, $id, $ancien]);
        $pdo->commit();
        flash('success', t('admin_donation_updated'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', t('admin_donation_update_failed'));
    }
    redirect('dons.php');
}

$livreurs = $pdo->query("SELECT id, nom FROM users WHERE role = 'livreur' AND actif = 1 ORDER BY nom")->fetchAll();
$dons = $pdo->query('SELECT d.*, u.nom AS donateur, u.telephone, l.nom AS livreur_nom FROM dons d JOIN users u ON u.id = d.user_id LEFT JOIN users l ON l.id = d.livreur_id ORDER BY d.statut = \'recu\', d.created_at DESC')->fetchAll();

$base = '../'; $titre = t('admin_donations'); $page = 'dons';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= t('admin_donations') ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>
<?php if (!$dons): ?><p class="muted"><?= t('admin_no_donations') ?></p><?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('th_date') ?></th><th><?= t('admin_th_donor') ?></th><th><?= t('admin_th_donation') ?></th><th><?= t('admin_th_collection') ?></th><th><?= t('admin_th_status_courier') ?></th></tr>
  <?php foreach ($dons as $d): ?>
  <tr>
    <td><?= e(date('d/m/Y', strtotime($d['created_at']))) ?></td>
    <td><?= e($d['donateur']) ?><br><span class="muted"><?= e($d['telephone']) ?></span></td>
    <td><span class="badge"><?= e(libelle_type_don($d['type_don'] ?? 'vetements')) ?></span><br><?= e($d['description']) ?><br><span class="muted"><?= t('th_qty_short') ?> : <?= (int)$d['quantite'] ?></span></td>
    <td><?= e($d['adresse_collecte']) ?><?php if ($d['date_souhaitee']): ?><br><span class="muted"><?= t('admin_preferred_date') ?> : <?= e(date('d/m/Y', strtotime($d['date_souhaitee']))) ?></span><?php endif; ?></td>
    <td>
      <?php if (in_array($d['statut'], ['recu', 'refuse'], true)): ?>
        <span class="badge"><?= e(libelle_statut($d['statut'])) ?></span>
        <?php if ($d['livreur_nom']): ?><br><span class="muted"><?= t('admin_courier') ?> : <?= e($d['livreur_nom']) ?></span><?php endif; ?>
      <?php else: ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <select name="statut"><?php foreach ($transitions[$d['statut']] ?? [$d['statut']] as $s): ?><option value="<?= $s ?>" <?= $d['statut'] === $s ? 'selected' : '' ?>><?= e(libelle_statut($s)) ?></option><?php endforeach; ?></select>
        <select name="livreur_id"><option value="">— <?= t('admin_courier') ?> —</option>
          <?php foreach ($livreurs as $l): ?><option value="<?= (int)$l['id'] ?>" <?= (int)$d['livreur_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['nom']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-small" type="submit"><?= t('ok') ?></button>
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
