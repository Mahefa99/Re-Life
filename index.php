<?php
// index.php : accueil, impact et deux vitrines distinctes (vêtements puis livres)
require_once __DIR__ . '/includes/functions.php';
$titre = t('home');
$bodyClass = 'home-page';

$impact = $pdo->query("SELECT
  (SELECT COUNT(*) FROM dons WHERE statut = 'recu') AS dons_recus,
  (SELECT COALESCE(SUM(quantite), 0) FROM dons WHERE statut = 'recu') AS articles_recus,
  (SELECT COUNT(DISTINCT user_id) FROM commandes WHERE statut = 'livree') AS personnes_soutenues,
  (SELECT COALESCE(SUM(stock), 0) FROM produits WHERE categorie = 'Livres' AND actif = 1 AND stock > 0) AS livres_disponibles
")->fetch();

$vetements = $pdo->query("SELECT * FROM produits WHERE actif = 1 AND stock > 0 AND categorie <> 'Livres' ORDER BY created_at DESC LIMIT 6")->fetchAll();
$livres = $pdo->query("SELECT * FROM produits WHERE actif = 1 AND stock > 0 AND categorie = 'Livres' ORDER BY created_at DESC LIMIT 6")->fetchAll();

require __DIR__ . '/includes/header.php';

function carte_accueil(array $p): void {
    $estLivre = $p['categorie'] === 'Livres';
    ?>
    <a class="carte <?= $estLivre ? 'carte-livre' : 'carte-vetement' ?>" href="produit.php?id=<?= (int)$p['id'] ?>">
      <div class="photo">
        <?php if (!empty($p['image'])): ?>
          <img src="uploads/<?= e(basename($p['image'])) ?>" alt="<?= e($p['nom']) ?>" loading="lazy">
        <?php else: ?>
          <span class="product-art <?= e(classe_visuel_produit($p)) ?>" role="img" aria-label="<?= e($p['nom']) ?>"></span>
        <?php endif; ?>
        <span class="favorite-mark" aria-hidden="true"><?= rewear_icon('heart') ?></span>
      </div>
      <div class="corps">
        <h3><?= e($p['nom']) ?></h3>
        <?php if ($estLivre): ?>
          <p class="card-author"><?= e($p['auteur'] ?: t('book_author_unknown')) ?></p>
          <p class="card-book-meta"><?= e(implode(' · ', array_filter([$p['genre_livre'] ?? '', $p['taille'] ?? '']))) ?></p>
        <?php endif; ?>
        <div class="product-bottom">
          <span class="prix"><?= prix($p['prix']) ?></span>
          <span class="reuse-mark" aria-hidden="true"><?= rewear_icon($estLivre ? 'book' : 'leaf') ?></span>
        </div>
      </div>
    </a>
    <?php
}
?>

<section class="hero" aria-labelledby="hero-title">
  <div class="hero-content">
    <h1 id="hero-title"><span><?= t('hero_title_top') ?></span><span><?= t('hero_title_bottom') ?></span></h1>
    <span class="title-accent" aria-hidden="true"></span>
    <p><?= t('hero_short') ?></p>
    <div class="actions">
      <a class="btn" href="don.php"><?= rewear_icon('heart') ?> <?= t('btn_donate') ?></a>
      <a class="btn btn-outline" href="catalogue.php"><?= rewear_icon('bag') ?> <?= t('btn_shop') ?></a>
    </div>
  </div>
  <div class="hero-media">
    <img src="assets/images/rewear-hero-books.png" alt="<?= e(t('hero_image_alt')) ?>" fetchpriority="high">
  </div>
</section>

<section class="impact-band" aria-labelledby="impact-title">
  <div class="impact-intro">
    <h2 id="impact-title"><?= t('impact_title') ?></h2>
    <?= rewear_icon('leaf', 'impact-leaf') ?>
  </div>
  <div class="impact-stat"><?= rewear_icon('shirt') ?><div><strong><?= number_format((int)$impact['articles_recus'], 0, ',', ' ') ?></strong><span><?= t('impact_items') ?></span></div></div>
  <div class="impact-stat"><?= rewear_icon('users') ?><div><strong><?= number_format((int)$impact['personnes_soutenues'], 0, ',', ' ') ?></strong><span><?= t('impact_people') ?></span></div></div>
  <div class="impact-stat"><?= rewear_icon('book') ?><div><strong><?= number_format((int)$impact['livres_disponibles'], 0, ',', ' ') ?></strong><span><?= t('impact_books') ?></span></div></div>
  <div class="impact-stat impact-message"><?= rewear_icon('heart') ?><div><strong><?= t('impact_island') ?></strong><span><?= t('impact_island_note') ?></span></div></div>
</section>

<section class="process-section" aria-labelledby="process-title">
  <div class="section-heading">
    <h2 id="process-title"><?= t('how_title') ?></h2>
  </div>
  <div class="process-grid">
    <article class="process-step">
      <span class="step-number">1</span><?= rewear_icon('box', 'step-icon') ?>
      <div><h3><?= t('step_donate_title') ?></h3><p><?= t('step_donate_text') ?></p></div>
    </article>
    <span class="step-arrow" aria-hidden="true"><?= rewear_icon('arrow') ?></span>
    <article class="process-step">
      <span class="step-number">2</span><?= rewear_icon('hanger', 'step-icon') ?>
      <div><h3><?= t('step_sort_title') ?></h3><p><?= t('step_sort_text') ?></p></div>
    </article>
    <span class="step-arrow" aria-hidden="true"><?= rewear_icon('arrow') ?></span>
    <article class="process-step">
      <span class="step-number">3</span><?= rewear_icon('heart', 'step-icon') ?>
      <div><h3><?= t('step_reuse_title') ?></h3><p><?= t('step_reuse_text') ?></p></div>
    </article>
  </div>
</section>

<section class="products-section clothing-section" aria-labelledby="clothing-title">
  <div class="section-heading row-heading">
    <h2 id="clothing-title"><?= t('clothing_news') ?></h2>
    <a class="text-link" href="catalogue.php"><?= t('see_all_short') ?> <?= rewear_icon('arrow') ?></a>
  </div>
  <?php if ($vetements): ?>
    <div class="grille home-product-row">
      <?php foreach ($vetements as $p) carte_accueil($p); ?>
    </div>
  <?php else: ?>
    <p class="muted"><?= t('no_clothes') ?></p>
  <?php endif; ?>
</section>

<section class="products-section books-section" aria-labelledby="books-title">
  <div class="section-heading row-heading">
    <div><h2 id="books-title"><?= t('secondhand_books') ?></h2><p><?= t('books_home_sub') ?></p></div>
    <a class="text-link" href="catalogue.php?cat=Livres"><?= t('see_all_short') ?> <?= rewear_icon('arrow') ?></a>
  </div>
  <?php if ($livres): ?>
    <div class="grille home-product-row book-product-row">
      <?php foreach ($livres as $p) carte_accueil($p); ?>
    </div>
  <?php else: ?>
    <div class="empty-showcase">
      <?= rewear_icon('book') ?>
      <div><h3><?= t('books_coming') ?></h3><p><?= t('books_coming_text') ?></p></div>
      <a class="btn btn-outline btn-small" href="don.php"><?= t('donate_books') ?></a>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
