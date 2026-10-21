</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="logo" href="<?= $base ?>index.php"><?= rewear_icon('leaf', 'logo-leaf') ?><span class="logo-re">Re</span><strong>Wear</strong></a>
      <p><?= t('footer_text') ?></p>
    </div>
    <nav aria-label="<?= t('footer_navigation') ?>">
      <strong><?= t('explore') ?></strong>
      <a href="<?= $base ?>catalogue.php"><?= t('menu_shop') ?></a>
      <?php if (!user() || user()['role'] === 'utilisateur'): ?><a href="<?= $base ?>don.php"><?= t('menu_donate') ?></a><?php endif; ?>
    </nav>
    <div class="footer-commitment">
      <strong><?= t('our_commitment') ?></strong>
      <p><?= t('footer_commitment') ?></p>
    </div>
  </div>
  <div class="container footer-bottom">© <?= date('Y') ?> ReWear · <?= t('footer_local') ?></div>
</footer>
</body>
</html>
