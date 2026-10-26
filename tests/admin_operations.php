<?php
// Exécuter avec PHP en ligne de commande. Une base temporaire indépendante
// est créée, testée via HTTP, puis supprimée, sans utiliser les données Re-Life.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$host = getenv('RELIFE_DB_HOST') ?: '127.0.0.1';
$port = getenv('RELIFE_DB_PORT') ?: '3306';
$user = getenv('RELIFE_DB_USER') ?: 'root';
$password = getenv('RELIFE_DB_PASS') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'relife_crud_test_' . bin2hex(random_bytes(6));
$server = null; $cookies = []; $checks = 0;
$log = tempnam(sys_get_temp_dir(), 'relife-crud-');
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
    $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS relife[^;]*;|USE relife;/', '', $schema);
    $pdo->exec($schema);
    $testPassword = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE email = 'admin@relife.mu'")->execute([password_hash($testPassword, PASSWORD_DEFAULT)]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $baseUrl = 'http://' . $address;
    $env = getenv();
    $env['RELIFE_DB_HOST'] = $host; $env['RELIFE_DB_PORT'] = $port;
    $env['RELIFE_DB_USER'] = $user; $env['RELIFE_DB_PASS'] = $password;
    $env['RELIFE_DB_NAME'] = $db; $env['RELIFE_MODE_DEV'] = '0'; $env['RELIFE_SITE_URL'] = $baseUrl;
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
    verifier(requete('/login.php', ['csrf' => $csrf, 'email' => 'admin@relife.mu', 'mot_de_passe' => $testPassword])[0] === 302, 'Connexion administrateur');
    [, $list] = requete('/admin/produits.php');
    preg_match('/name="csrf" value="([^"]+)"/', $list, $m); $csrf = $m[1];

    verifier(requete('/admin/supprimer-operation.php?type=users&id=1')[0] === 404, 'Table non autorisée refusée');
    $pdo->exec("INSERT INTO users (nom,email,mot_de_passe,role) VALUES ('Livreur test','courier@example.test','unused','livreur')");
    $courier = (int)$pdo->lastInsertId();
    foreach (['en_attente','confirmee','en_livraison','livree','annulee'] as $state) {
        $pdo->exec("INSERT INTO commandes (user_id,total,adresse_livraison,statut) VALUES (1,20,'Test','$state')");
        $id = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO commande_lignes (commande_id,produit_id,quantite,prix_unitaire) VALUES ($id,1,2,10)");
        $before = (int)$pdo->query('SELECT stock FROM produits WHERE id=1')->fetchColumn();
        $url = '/admin/supprimer-operation.php?type=commandes&id=' . $id;
        [, $html] = requete($url);
        verifier(str_contains($html, 'Test') && (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE id=$id")->fetchColumn() === 1, "GET ne supprime pas : $state");
        verifier(requete($url, [])[0] === 400, 'Suppression sans CSRF refusée');
        requete($url, ['csrf'=>$csrf]);
        $remaining = (int)$pdo->query("SELECT COUNT(*) FROM commandes WHERE id=$id")->fetchColumn();
        verifier($remaining === ($state === 'en_livraison' ? 1 : 0), "Suppression selon statut : $state");
        $expected = $before + (in_array($state, ['en_attente','confirmee']) ? 2 : 0);
        verifier((int)$pdo->query('SELECT stock FROM produits WHERE id=1')->fetchColumn() === $expected, "Stock correct : $state");
        verifier((int)$pdo->query("SELECT COUNT(*) FROM commande_lignes WHERE commande_id=$id")->fetchColumn() === $remaining, 'Lignes cohérentes');
        requete($url, ['csrf'=>$csrf]);
        verifier((int)$pdo->query('SELECT stock FROM produits WHERE id=1')->fetchColumn() === $expected, 'Répétition sans double remise en stock');
    }
    foreach (['en_attente','assigne','collecte','recu','refuse'] as $state) {
        $pdo->exec("INSERT INTO dons (user_id,type_don,description,quantite,adresse_collecte,statut) VALUES (1,'livres','Don test',2,'Test','$state')");
        $id = (int)$pdo->lastInsertId();
        $url = '/admin/supprimer-operation.php?type=dons&id=' . $id;
        verifier(requete($url)[0] === 200, "Confirmation don : $state");
        requete($url, ['csrf'=>$csrf]);
        verifier((int)$pdo->query("SELECT COUNT(*) FROM dons WHERE id=$id")->fetchColumn() === ($state === 'collecte' ? 1 : 0), "Suppression don : $state");
    }
    $pdo->exec("INSERT INTO dons (user_id,type_don,description,quantite,adresse_collecte) VALUES (1,'livres','Statut test',1,'Test')");
    $id = (int)$pdo->lastInsertId();
    foreach (['assigne','collecte','recu'] as $state) {
        requete('/admin/dons.php', ['csrf'=>$csrf,'id'=>$id,'statut'=>$state,'livreur_id'=>$courier]);
        verifier($pdo->query("SELECT statut FROM dons WHERE id=$id")->fetchColumn() === $state, "Mise à jour don : $state");
    }
    $pdo->exec("INSERT INTO commandes (user_id,total,adresse_livraison) VALUES (1,10,'Test')");
    $id = (int)$pdo->lastInsertId();
    foreach (['confirmee','en_livraison','livree'] as $state) {
        requete('/admin/commandes.php', ['csrf'=>$csrf,'id'=>$id,'statut'=>$state,'livreur_id'=>$courier]);
        verifier($pdo->query("SELECT statut FROM commandes WHERE id=$id")->fetchColumn() === $state, "Mise à jour commande : $state");
    }
    [, $html] = requete('/admin/supprimer-operation.php?type=commandes&id='.$id.'&lang=en');
    verifier(str_contains($html, 'Confirm deletion'), 'Confirmation traduite');
    $pdo->exec("UPDATE users SET role='utilisateur' WHERE id=1");
    verifier(requete('/admin/supprimer-operation.php?type=commandes&id='.$id, ['csrf'=>$csrf])[0] === 403, 'Suppression interdite au membre');
    echo "$checks vérifications réussies.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $pdo->exec("DROP DATABASE `$db`");
    if (is_file($log)) unlink($log);
}
