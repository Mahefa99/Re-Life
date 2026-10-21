<?php
// Exécuter avec PHP en ligne de commande. Une base temporaire indépendante
// est créée, testée via HTTP, puis supprimée, sans utiliser les données ReWear.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$host = getenv('REWEAR_DB_HOST') ?: '127.0.0.1';
$port = getenv('REWEAR_DB_PORT') ?: '3306';
$user = getenv('REWEAR_DB_USER') ?: 'root';
$password = getenv('REWEAR_DB_PASS') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'rewear_crud_test_' . bin2hex(random_bytes(6));
$server = null; $cookies = []; $checks = 0;
$log = tempnam(sys_get_temp_dir(), 'rewear-crud-');
function verifier($ok, $message) {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
    echo "OK: $message\n";
}
function requete($path, $post = null) {
    global $cookies, $baseUrl;
    $headers = 'Cookie: ' . http_build_query($cookies, '', '; ') . "\r\n";
    if ($post !== null) $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => $headers,
        'content' => $post === null ? '' : http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($baseUrl . $path, false, $context);
    $status = (int)explode(' ', $http_response_header[0])[1];
    $location = '';
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]+)/i', $header, $m)) $cookies[$m[1]] = $m[2];
        if (stripos($header, 'Location: ') === 0) $location = substr($header, 10);
    }
    if ($status >= 500 || str_contains($body, 'Fatal error') || str_contains($body, '<b>Warning</b>')) throw new RuntimeException('Erreur HTTP : ' . $body);
    return [$status, $body, $location];
}
try {
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    $schema = file_get_contents($root . '/database.sql');
    $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS rewear[^;]*;|USE rewear;/', '', $schema);
    $pdo->exec($schema);
    $testPassword = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE email = 'admin@rewear.mu'")->execute([password_hash($testPassword, PASSWORD_DEFAULT)]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $baseUrl = 'http://' . $address;
    $env = getenv();
    $env['REWEAR_DB_HOST'] = $host; $env['REWEAR_DB_PORT'] = $port;
    $env['REWEAR_DB_USER'] = $user; $env['REWEAR_DB_PASS'] = $password;
    $env['REWEAR_DB_NAME'] = $db; $env['REWEAR_MODE_DEV'] = '0'; $env['REWEAR_SITE_URL'] = $baseUrl;
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $root], [['pipe', 'r'], ['file', $log, 'a'], ['file', $log, 'a']], $pipes, $root, $env);
    if (!is_resource($server)) throw new RuntimeException('Serveur de test indisponible');
    fclose($pipes[0]);
    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    verifier(requete('/admin/produits.php')[0] === 302, 'Accès anonyme refusé');
    [, $login] = requete('/login.php');
    preg_match('/name="csrf" value="([^"]+)"/', $login, $m); $csrf = $m[1];
    verifier(requete('/login.php', ['csrf' => $csrf, 'email' => 'admin@rewear.mu', 'mot_de_passe' => $testPassword])[0] === 302, 'Connexion administrateur');
    [, $list] = requete('/admin/produits.php');
    preg_match('/name="csrf" value="([^"]+)"/', $list, $m); $csrf = $m[1];
    verifier(str_contains($list, 'Ajouter un vêtement') && str_contains($list, 'Ajouter un livre'), 'Deux boutons de création');
    verifier(requete('/admin/produits.php?voir=999999')[0] === 404, 'Fiche inconnue : 404');
    $initial = (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
    $form = ['csrf' => $csrf, 'action' => 'sauver', 'id' => '0', 'nom' => 'Test vêtement CRUD', 'description' => 'Description conservée', 'categorie' => 'Homme', 'taille' => 'M', 'etat' => 'Bon état', 'prix' => '25.50', 'stock' => '2'];
    $invalid = $form; $invalid['stock'] = '-2';
    [, $errorPage] = requete('/admin/produits.php', $invalid);
    verifier(str_contains($errorPage, 'Description conservée') && (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn() === $initial, 'Stock négatif refusé, saisie conservée');
    $invalid = $form; $invalid['prix'] = 'abc';
    requete('/admin/produits.php', $invalid);
    verifier((int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn() === $initial, 'Prix non numérique refusé');
    $invalid = $form; unset($invalid['csrf']);
    verifier(requete('/admin/produits.php', $invalid)[0] === 400, 'Protection CSRF');
    foreach (['Homme', 'Livres'] as $category) {
        $form['categorie'] = $category; $form['nom'] = 'Test CRUD ' . $category;
        if ($category === 'Livres') {
            $form += ['auteur' => 'Auteur de test', 'genre_livre' => 'Roman', 'isbn' => '9782070612758', 'editeur' => 'Éditeur', 'annee_publication' => '2020', 'langue_livre' => 'Français', 'nombre_pages' => '120'];
            $invalid = $form; $invalid['auteur'] = '';
            verifier(requete('/admin/produits.php', $invalid)[0] === 200, 'Livre sans auteur refusé');
        }
        [$status, , $location] = requete('/admin/produits.php', $form);
        verifier($status === 302 && preg_match('/voir=(\d+)/', $location, $m), "Création $category");
        $id = (int)$m[1];
        [, $detail] = requete('/admin/produits.php?voir=' . $id);
        verifier(str_contains($detail, $form['nom']) && ($category !== 'Livres' || str_contains($detail, 'Auteur de test')), "Consultation $category");
        $updated = $form; $updated['id'] = (string)$id; $updated['nom'] .= ' modifié'; $updated['stock'] = '7';
        verifier(requete('/admin/produits.php?edit=' . $id, $updated)[0] === 302 && (int)$pdo->query("SELECT stock FROM produits WHERE id = $id")->fetchColumn() === 7, "Modification $category");
        foreach ([0, 1] as $active) {
            requete('/admin/produits.php', ['csrf' => $csrf, 'action' => 'basculer', 'id' => $id]);
            verifier((int)$pdo->query("SELECT actif FROM produits WHERE id = $id")->fetchColumn() === $active, "Visibilité $category = $active");
        }
        [, $confirmation] = requete('/admin/produits.php?supprimer=' . $id);
        verifier(str_contains($confirmation, 'Confirmer la suppression') && (int)$pdo->query("SELECT COUNT(*) FROM produits WHERE id = $id")->fetchColumn() === 1, 'Confirmation sans suppression en GET');
        requete('/admin/produits.php', ['csrf' => $csrf, 'action' => 'supprimer', 'id' => $id]);
        verifier((int)$pdo->query("SELECT COUNT(*) FROM produits WHERE id = $id")->fetchColumn() === 0, "Suppression $category");
    }
    $pdo->exec("INSERT INTO commandes (user_id,total,adresse_livraison) VALUES (1,10,'Adresse de test')");
    $order = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO commande_lignes (commande_id,produit_id,quantite,prix_unitaire) VALUES ($order,1,1,10)");
    requete('/admin/produits.php', ['csrf' => $csrf, 'action' => 'supprimer', 'id' => 1]);
    verifier((int)$pdo->query('SELECT COUNT(*) FROM produits WHERE id = 1')->fetchColumn() === 1, 'Suppression refusée si commande liée');
    verifier((int)$pdo->query("SELECT COUNT(*) FROM commande_lignes WHERE commande_id = $order")->fetchColumn() === 1, 'Historique des commandes conservé');
    [, $books] = requete('/admin/produits.php?type=livres&q=Saint-Exup');
    verifier(str_contains($books, 'Le Petit Prince') && !str_contains($books, 'Jean droit bleu'), 'Filtre livres et recherche par auteur');
    [, $english] = requete('/admin/produits.php?lang=en');
    verifier(str_contains($english, 'Add a book') && str_contains($english, 'Delete'), 'Interface anglaise');
    $pdo->exec("UPDATE users SET role = 'utilisateur' WHERE id = 1");
    verifier(requete('/admin/produits.php')[0] === 403, 'Accès utilisateur refusé');
    echo "$checks vérifications réussies.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo->exec("DROP DATABASE `$db`");
    if (is_file($log)) unlink($log);
}
