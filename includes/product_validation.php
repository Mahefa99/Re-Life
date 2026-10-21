<?php
// Validation partagée par les créations et les modifications de produits.
function valider_produit_admin(array $saisie): array {
    $champs = ['nom', 'description', 'categorie', 'taille', 'auteur', 'genre_livre', 'isbn', 'editeur', 'annee_publication', 'langue_livre', 'nombre_pages', 'etat', 'prix', 'stock'];
    $p = [];
    foreach ($champs as $champ) {
        $p[$champ] = is_string($saisie[$champ] ?? null) ? trim($saisie[$champ]) : '';
    }
    $erreurs = [];
    $livre = $p['categorie'] === 'Livres';
    if ($p['nom'] === '' || mb_strlen($p['nom']) > 150 || strlen($p['description']) > 60000
        || !in_array($p['categorie'], ['Homme', 'Femme', 'Enfant', 'Chaussures', 'Accessoires', 'Livres'], true)
        || !in_array($p['etat'], ['Comme neuf', 'Très bon état', 'Bon état'], true)) {
        $erreurs[] = 'admin_product_invalid';
    }
    $p['prix'] = str_replace(',', '.', $p['prix']);
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $p['prix'])) $erreurs[] = 'admin_price_invalid';
    if (filter_var($p['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]) === false) $erreurs[] = 'admin_stock_invalid';
    $limites = ['taille' => 20];
    if ($livre) {
        $limites += ['auteur' => 150, 'genre_livre' => 80, 'isbn' => 20, 'editeur' => 150, 'langue_livre' => 50];
        if ($p['auteur'] === '') $erreurs[] = 'admin_book_author_required';
        if ($p['annee_publication'] !== '' && filter_var($p['annee_publication'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1000, 'max_range' => (int)date('Y') + 1]]) === false) $erreurs[] = 'admin_year_invalid';
        if ($p['nombre_pages'] !== '' && filter_var($p['nombre_pages'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50000]]) === false) $erreurs[] = 'admin_pages_invalid';
    }
    foreach ($limites as $champ => $max) {
        if (mb_strlen($p[$champ]) > $max) $erreurs[] = 'admin_field_too_long';
    }
    foreach (['auteur', 'genre_livre', 'isbn', 'editeur', 'annee_publication', 'langue_livre', 'nombre_pages'] as $champ) {
        if (!$livre || $p[$champ] === '') $p[$champ] = null;
    }
    return [$p, array_values(array_unique($erreurs))];
}
