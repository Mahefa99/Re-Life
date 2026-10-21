<?php
// header.php : en-tête commun. Variables optionnelles : $titre, $base, $bodyClass
require_once __DIR__ . '/functions.php';
$base  = $base  ?? '';
$titre = $titre ?? 'ReWear';
$u = user();
$pageCourante = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$estAccueil = $base === '' && in_array($pageCourante, ['', 'index.php'], true);
$comptePanier = panier_compte();
$lienCompte = $u && $u['role'] === 'utilisateur' ? $base . 'mon-compte.php' : ($u ? $base . $u['role'] . '/index.php' : $base . 'login.php');
$peutUtiliserServices = !$u || $u['role'] === 'utilisateur';
?>
<!DOCTYPE html>
<html lang="<?= langue() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#173f30">
  <title><?= e($titre) ?> · ReWear</title>
  <link rel="stylesheet" href="<?= $base ?>assets/style.css">
  <script src="<?= $base ?>assets/app.js" defer></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#contenu-principal"><?= t('skip_content') ?></a>
<header class="site-header">
  <div class="container nav">
    <a class="logo" href="<?= $base ?>index.php" aria-label="ReWear — <?= t('home') ?>">
      <?= rewear_icon('leaf', 'logo-leaf') ?><span class="logo-re">Re</span><strong>Wear</strong>
    </a>

    <div class="nav-panel" id="site-menu">
      <nav class="main-nav" aria-label="<?= t('main_navigation') ?>">
        <a href="<?= $base ?>index.php" <?= $estAccueil ? 'aria-current="page"' : '' ?>><?= t('home') ?></a>
        <a href="<?= $base ?>catalogue.php" <?= in_array($pageCourante, ['catalogue.php', 'produit.php'], true) ? 'aria-current="page"' : '' ?>><?= t('menu_shop') ?></a>
        <?php if ($peutUtiliserServices): ?><a href="<?= $base ?>don.php" <?= $pageCourante === 'don.php' ? 'aria-current="page"' : '' ?>><?= t('menu_donate') ?></a><?php endif; ?>
        <?php if ($u && $u['role'] === 'admin'): ?>
          <a href="<?= $base ?>admin/index.php" <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/') ? 'aria-current="page"' : '' ?>><?= t('menu_admin') ?></a>
        <?php elseif ($u && $u['role'] === 'livreur'): ?>
          <a href="<?= $base ?>livreur/index.php" <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/livreur/') ? 'aria-current="page"' : '' ?>><?= t('menu_missions') ?></a>
        <?php endif; ?>
      </nav>

      <div class="nav-right">
        <a class="language-link" href="<?= e(url_langue(langue() === 'fr' ? 'en' : 'fr')) ?>" aria-label="<?= t('language') ?>">
          <?= rewear_icon('globe') ?><span><?= strtoupper(langue()) ?></span><?= rewear_icon('chevron', 'chevron-icon') ?>
        </a>
        <a class="nav-account" href="<?= e($lienCompte) ?>"><?= rewear_icon('user') ?><span><?= t('account_title') ?></span></a>
        <?php if ($peutUtiliserServices): ?>
          <a class="nav-cart" href="<?= $base ?>panier.php" aria-label="<?= t('cart') ?>, <?= $comptePanier ?>">
            <?= rewear_icon('cart') ?><span><?= t('cart') ?></span>
            <?php if ($comptePanier > 0): ?><b><?= $comptePanier ?></b><?php endif; ?>
          </a>
        <?php endif; ?>
        <?php if ($u): ?>
          <form class="mobile-session-form" method="post" action="<?= $base ?>logout.php"><?= csrf_field() ?><button class="mobile-session-link" type="submit"><?= t('logout') ?></button></form>
        <?php else: ?><a class="mobile-session-link" href="<?= $base ?>register.php"><?= t('register') ?></a><?php endif; ?>
      </div>
    </div>

    <div class="nav-mobile-actions">
      <?php if ($peutUtiliserServices): ?>
        <a class="mobile-cart" href="<?= $base ?>panier.php" aria-label="<?= t('cart') ?>, <?= $comptePanier ?>">
          <?= rewear_icon('cart') ?><?php if ($comptePanier > 0): ?><b><?= $comptePanier ?></b><?php endif; ?>
        </a>
      <?php endif; ?>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu">
        <span class="sr-only"><?= t('menu_open') ?></span>
        <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
      </button>
    </div>
  </div>
</header>
<main class="container" id="contenu-principal">
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="alert alert-<?= e($_SESSION['flash']['type']) ?>" role="status"><?= e($_SESSION['flash']['msg']) ?></div>
  <?php unset($_SESSION['flash']); ?>
<?php endif; ?>
