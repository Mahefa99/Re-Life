<?php
// ============================================================
// functions.php : fonctions utilitaires partagées par toutes les pages
// ============================================================
require_once __DIR__ . '/config.php';

// --- Langue : choisie via ?lang=fr ou ?lang=en, mémorisée en session ---
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
}
function langue() {
    return $_SESSION['lang'] ?? 'fr';
}

// t('cle') renvoie le texte dans la langue choisie.
// t('cle', 5) remplace %d / %s dans le texte. Si la clé manque en anglais, on prend le français.
function t($cle, ...$args) {
    static $chargees = [];
    foreach (['fr', langue()] as $l) {
        if (!isset($chargees[$l])) {
            $fichier = __DIR__ . '/lang/' . $l . '.php';
            $chargees[$l] = is_file($fichier) ? require $fichier : [];
        }
    }
    $txt = $chargees[langue()][$cle] ?? $chargees['fr'][$cle] ?? $cle;
    return $args ? vsprintf($txt, $args) : $txt;
}

// Lien de changement de langue qui garde les autres paramètres de l'URL (ex. ?id=3)
function url_langue($l) {
    return '?' . http_build_query(array_merge($_GET, ['lang' => $l]));
}

// Échapper le texte avant de l'afficher (protège contre les attaques XSS)
function e($texte) {
    return htmlspecialchars((string)$texte, ENT_QUOTES, 'UTF-8');
}

// Icônes vectorielles communes : leur dessin reste identique sur tous les appareils.
function rewear_icon($nom, $classe = 'ui-icon') {
    $icones = [
        'leaf' => '<path d="M20.7 3.3C13.5 3 7.2 5.6 4.7 10.2c-1.6 3-.8 5.8 1.2 7.1 2.2 1.5 5.4.6 7.4-1.7 2.2-2.5 2.6-6.2 7.4-12.3Z" fill="currentColor" stroke="none"/><path d="M3.2 21c2.9-5.2 6.6-8.8 11.8-11.8" fill="none"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'user' => '<circle cx="12" cy="7.5" r="3.5"/><path d="M4.5 21v-2.2A6.5 6.5 0 0 1 11 12.3h2a6.5 6.5 0 0 1 6.5 6.5V21"/>',
        'cart' => '<path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 1.9-1.4L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>',
        'chevron' => '<path d="m8 10 4 4 4-4"/>',
        'heart' => '<path d="M20.8 4.7a5.4 5.4 0 0 0-7.6 0L12 5.9l-1.2-1.2a5.4 5.4 0 0 0-7.6 7.6L12 21l8.8-8.7a5.4 5.4 0 0 0 0-7.6Z"/>',
        'bag' => '<path d="M5 8h14l-1 13H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'shirt' => '<path d="m8 4 4 2 4-2 5 3-2 5-3-1v10H8V11l-3 1-2-5 5-3Z"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2M15 14h1.5a4.5 4.5 0 0 1 4.5 4.5V20"/>',
        'box' => '<path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7ZM12 11v10"/>',
        'hanger' => '<path d="M9 6a3 3 0 1 1 3 3v2l9 6H3l9-6"/>',
        'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H11v18H6.5A2.5 2.5 0 0 0 4 22V4.5ZM20 4.5A2.5 2.5 0 0 0 17.5 2H13v18h4.5A2.5 2.5 0 0 1 20 22V4.5Z"/>',
        'arrow' => '<path d="M5 12h14M14 7l5 5-5 5"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
    if (!isset($icones[$nom])) return '';
    return '<svg class="' . e($classe) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icones[$nom] . '</svg>';
}

// Vignettes cohérentes quand un produit n'a pas encore de photo envoyée.
function classe_visuel_produit($produit) {
    $categorie = $produit['categorie'] ?? '';
    $nom = mb_strtolower((string)($produit['nom'] ?? ''), 'UTF-8');
    if ($categorie === 'Livres') return ((int)($produit['id'] ?? 0) % 2) ? 'sprite-books book-blue' : 'sprite-books book-terracotta';
    if (str_contains($nom, 'chemise')) return 'sprite-fashion shirt';
    if (str_contains($nom, 'pull')) return 'sprite-fashion sweater';
    if (str_contains($nom, 'robe')) return 'sprite-fashion dress';
    if (str_contains($nom, 'veste')) return 'sprite-fashion jacket';
    if ($categorie === 'Enfant') return 'sprite-books child-shirt';
    if ($categorie === 'Chaussures') return 'sprite-books sneakers';
    if ($categorie === 'Accessoires') return str_contains($nom, 'sac') ? 'sprite-books tote' : 'sprite-books scarf';
    if (str_contains($nom, 'jean') || str_contains($nom, 'pantalon')) return 'sprite-fashion jeans';
    if ($categorie === 'Femme') return 'sprite-fashion dress';
    return ((int)($produit['id'] ?? 0) % 2) ? 'sprite-fashion shirt' : 'sprite-fashion sweater';
}

// Formater un prix en roupies
function prix($montant) {
    return 'Rs ' . number_format((float)$montant, 2, ',', ' ');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function est_connecte() {
    synchroniser_session_utilisateur();
    return isset($_SESSION['user']);
}

function user() {
    synchroniser_session_utilisateur();
    return $_SESSION['user'] ?? null;
}

// Le rôle et l'état actif sont relus une fois par requête. Ainsi, une
// désactivation ou un changement de rôle décidé par l'admin prend effet tout
// de suite, même si la personne possédait déjà une session ouverte.
function synchroniser_session_utilisateur() {
    static $dejaFait = false;
    if ($dejaFait || empty($_SESSION['user']['id'])) return;
    $dejaFait = true;

    global $pdo;
    $stmt = $pdo->prepare('SELECT id, nom, email, role, actif FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user']['id']]);
    $compte = $stmt->fetch();

    if (!$compte || !(int)$compte['actif']) {
        unset($_SESSION['user'], $_SESSION['panier']);
        return;
    }

    $_SESSION['user'] = [
        'id' => (int)$compte['id'],
        'nom' => $compte['nom'],
        'email' => $compte['email'],
        'role' => $compte['role'],
    ];
}

// Exiger une connexion (sinon redirection vers la page de connexion)
function exiger_connexion($base = '') {
    if (!est_connecte()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => t('login_required')];
        $_SESSION['retour'] = $_SERVER['REQUEST_URI'];
        redirect($base . 'login.php');
    }
}

// Exiger un rôle précis (admin, livreur...)
function exiger_role($role, $base = '../') {
    exiger_connexion($base);
    if (user()['role'] !== $role) {
        http_response_code(403);
        die(e(t('access_denied')));
    }
}

// Les achats, les dons et l'espace personnel appartiennent au rôle utilisateur.
// Un admin ou un livreur qui saisit l'URL directement retourne vers son espace.
function exiger_utilisateur($base = '') {
    exiger_connexion($base);
    if (user()['role'] === 'utilisateur') return;

    flash('error', t('user_area_only'));
    $destination = user()['role'] === 'admin' ? 'admin/index.php' : 'livreur/index.php';
    redirect($base . $destination);
}

// Messages "flash" : affichés une seule fois après une action
function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

// --- Protection CSRF : jeton caché dans chaque formulaire POST ---
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_verifier() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(400);
        die(e(t('invalid_request')));
    }
}

// --- Panier : stocké en session sous la forme [id_produit => quantité] ---
function panier() {
    return $_SESSION['panier'] ?? [];
}
function panier_compte() {
    return array_sum(panier());
}

// Enregistrer une image envoyée via un formulaire (retourne le nom du fichier ou null)
function enregistrer_image($champ) {
    if (empty($_FILES[$champ]['name']) || $_FILES[$champ]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $autorises = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $type = mime_content_type($_FILES[$champ]['tmp_name']);
    if (!isset($autorises[$type]) || $_FILES[$champ]['size'] > 3 * 1024 * 1024) {
        return null;
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0775, true) && !is_dir(UPLOAD_DIR)) {
        return null;
    }
    $nom = uniqid('p_', true) . '.' . $autorises[$type];
    move_uploaded_file($_FILES[$champ]['tmp_name'], UPLOAD_DIR . $nom);
    return $nom;
}

// Libellé lisible d'un statut (traduit selon la langue)
function libelle_statut($s) {
    return t('st_' . $s);
}

// Libellé du mode de paiement : 'livraison' = liquide, 'carte' = en ligne
function libelle_paiement($mode) {
    return $mode === 'carte' ? t('pay_online_short') : t('pay_cash_short');
}

// Libellé du contenu d'un don, y compris lorsqu'il réunit les deux familles.
function libelle_type_don($type) {
    if ($type === 'livres') return t('donation_books');
    if ($type === 'mixte') return t('donation_mixed');
    return t('donation_clothes');
}
