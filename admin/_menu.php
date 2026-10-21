<?php
// Sous-menu commun aux pages d'administration. $page = nom de la page active.
$items = [
    'index' => ['index.php', 'admin_dashboard'],
    'produits' => ['produits.php', 'admin_products'],
    'commandes' => ['commandes.php', 'admin_orders'],
    'dons' => ['dons.php', 'admin_donations'],
    'utilisateurs' => ['utilisateurs.php', 'admin_users'],
];
?>
<div class="sous-menu">
  <?php foreach ($items as $cle => [$lien, $libelle]): ?>
    <a href="<?= $lien ?>" class="<?= ($page ?? '') === $cle ? 'actif' : '' ?>"><?= t($libelle) ?></a>
  <?php endforeach; ?>
  <form class="submenu-logout" method="post" action="../logout.php"><?= csrf_field() ?><button type="submit"><?= t('logout') ?></button></form>
</div>
