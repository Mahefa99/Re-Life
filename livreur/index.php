<?php
// livreur/index.php : missions du livreur (collectes de dons + livraisons de commandes)
require_once __DIR__ . '/../includes/functions.php';
exiger_role('livreur');
$moi = user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $id = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? '';

    $statut = $_POST['statut'] ?? '';

    // Chaque action indique aussi l'état attendu : impossible de sauter ou rejouer une étape.
    $etapesDons = ['collecte' => 'assigne', 'recu' => 'collecte'];
    $etapesCommandes = ['en_livraison' => 'confirmee', 'livree' => 'en_livraison'];

    if ($id > 0 && $type === 'don' && isset($etapesDons[$statut])) {
        $s = $pdo->prepare('UPDATE dons SET statut = ? WHERE id = ? AND livreur_id = ? AND statut = ?');
        $s->execute([$statut, $id, $moi, $etapesDons[$statut]]);
        if ($s->rowCount() === 1) {
            flash('success', $statut === 'collecte' ? t('courier_donation_collected') : t('courier_donation_warehouse'));
        } else {
            flash('error', t('courier_collection_unavailable'));
        }
    } elseif ($id > 0 && $type === 'commande' && isset($etapesCommandes[$statut])) {
        $s = $pdo->prepare('UPDATE commandes SET statut = ? WHERE id = ? AND livreur_id = ? AND statut = ?');
        $s->execute([$statut, $id, $moi, $etapesCommandes[$statut]]);
        if ($s->rowCount() === 1) {
            flash('success', $statut === 'en_livraison' ? t('courier_delivery_started') : t('courier_order_delivered'));
        } else {
            flash('error', t('courier_delivery_unavailable'));
        }
    } else {
        flash('error', t('courier_action_invalid'));
    }
    redirect('index.php');
}

$collectes = $pdo->prepare("SELECT d.*, u.nom AS donateur, u.telephone FROM dons d JOIN users u ON u.id = d.user_id
                            WHERE d.livreur_id = ? AND d.statut IN ('assigne','collecte') ORDER BY d.date_souhaitee IS NULL, d.date_souhaitee");
$collectes->execute([$moi]);
$collectes = $collectes->fetchAll();

$livraisons = $pdo->prepare("SELECT c.*, u.nom AS client, u.telephone FROM commandes c JOIN users u ON u.id = c.user_id
                             WHERE c.livreur_id = ? AND c.statut IN ('confirmee','en_livraison') ORDER BY c.created_at");
$livraisons->execute([$moi]);
$livraisons = $livraisons->fetchAll();

$base = '../'; $titre = t('menu_missions');
require __DIR__ . '/../includes/header.php';
?>
<h1><?= t('menu_missions') ?></h1>
<form class="role-logout" method="post" action="../logout.php"><?= csrf_field() ?><button class="btn btn-outline btn-small" type="submit"><?= t('logout') ?></button></form>

<h2><?= t('courier_donation_collections', count($collectes)) ?></h2>
<?php if (!$collectes): ?><p class="muted"><?= t('courier_no_collection') ?></p><?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('admin_th_donor') ?></th><th><?= t('admin_th_donation') ?></th><th><?= t('address') ?></th><th><?= t('th_status') ?></th><th><?= t('courier_action') ?></th></tr>
  <?php foreach ($collectes as $d): ?>
  <tr>
    <td><?= e($d['donateur']) ?><br><span class="muted"><?= e($d['telephone']) ?></span></td>
    <td><span class="badge"><?= e(libelle_type_don($d['type_don'] ?? 'vetements')) ?></span><br><?= e($d['description']) ?> (<?= (int)$d['quantite'] ?>)</td>
    <td><?= e($d['adresse_collecte']) ?><?php if ($d['date_souhaitee']): ?><br><span class="muted"><?= t('courier_on_date', e(date('d/m/Y', strtotime($d['date_souhaitee'])))) ?></span><?php endif; ?></td>
    <td><span class="badge"><?= e(libelle_statut($d['statut'])) ?></span></td>
    <td>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="don"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <?php if ($d['statut'] === 'assigne'): ?><button class="btn btn-small" name="statut" value="collecte"><?= t('courier_mark_collected') ?></button>
        <?php else: ?><button class="btn btn-small" name="statut" value="recu"><?= t('courier_mark_warehouse') ?></button><?php endif; ?>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>

<h2><?= t('courier_order_deliveries', count($livraisons)) ?></h2>
<?php if (!$livraisons): ?><p class="muted"><?= t('courier_no_delivery') ?></p><?php else: ?>
<div class="table-wrap"><table>
  <tr><th><?= t('th_no') ?></th><th><?= t('admin_th_client') ?></th><th><?= t('address') ?></th><th><?= t('courier_amount_due') ?></th><th><?= t('th_status') ?></th><th><?= t('courier_action') ?></th></tr>
  <?php foreach ($livraisons as $c): ?>
  <tr>
    <td>#<?= (int)$c['id'] ?></td>
    <td><?= e($c['client']) ?><br><span class="muted"><?= e($c['telephone']) ?></span></td>
    <td><?= e($c['adresse_livraison']) ?></td>
    <td><?= $c['mode_paiement'] === 'livraison' ? prix($c['total']) . ' (' . t('pay_cash_short') . ')' : t('courier_paid_online') ?></td>
    <td><span class="badge"><?= e(libelle_statut($c['statut'])) ?></span></td>
    <td>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="type" value="commande"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <?php if ($c['statut'] === 'confirmee'): ?><button class="btn btn-small" name="statut" value="en_livraison"><?= t('courier_take_delivery') ?></button>
        <?php else: ?><button class="btn btn-small" name="statut" value="livree"><?= t('courier_mark_delivered') ?></button><?php endif; ?>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<p class="muted"><?= t('courier_orders_note') ?></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
