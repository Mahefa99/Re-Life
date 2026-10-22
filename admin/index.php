<?php
// admin/index.php : tableau de bord administrateur
require_once __DIR__ . '/../includes/functions.php';
exiger_role('admin');
$base = '../'; $titre = t('admin_title'); $page = 'index';
require __DIR__ . '/../includes/header.php';

// Petite fonction pour compter des lignes
function compter($pdo, $sql) { return (int)$pdo->query($sql)->fetchColumn(); }

$stats = [
    'admin_stat_users'          => compter($pdo, "SELECT COUNT(*) FROM users WHERE role = 'utilisateur'"),
    'admin_stat_products'       => compter($pdo, "SELECT COUNT(*) FROM produits WHERE actif = 1 AND stock > 0"),
    'admin_stat_orders'         => compter($pdo, "SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','confirmee')"),
    'admin_stat_donations'      => compter($pdo, "SELECT COUNT(*) FROM dons WHERE statut = 'en_attente'"),
    'admin_stat_received_items' => compter($pdo, "SELECT COALESCE(SUM(quantite),0) FROM dons WHERE statut = 'recu'"),
];
$liens = [
    'admin_stat_users' => 'utilisateurs.php',
    'admin_stat_products' => 'produits.php',
    'admin_stat_orders' => 'commandes.php?filtre=a_traiter',
    'admin_stat_donations' => 'dons.php?statut=en_attente',
    'admin_stat_received_items' => 'dons.php?statut=recu',
];
?>
<h1><?= t('admin_title') ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>
<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <a class="stat stat-link" href="<?= e($liens[$label]) ?>"><strong><?= $n ?></strong><span class="muted"><?= t($label) ?></span></a>
  <?php endforeach; ?>
</div>
<p><?= t('admin_intro') ?></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
