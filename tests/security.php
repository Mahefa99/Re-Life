<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Tests HTTP sur une base jetable ; aucun compte réel n'est utilisé.
$root = dirname(__DIR__);
$host = getenv('REWEAR_DB_HOST') ?: '127.0.0.1';
$port = getenv('REWEAR_DB_PORT') ?: '3306';
$user = getenv('REWEAR_DB_USER') ?: 'root';
$password = getenv('REWEAR_DB_PASS') ?: '';
$pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db = 'rewear_security_test_' . bin2hex(random_bytes(6));
$dir = sys_get_temp_dir() . '/rewear-security-' . bin2hex(random_bytes(6));
mkdir($dir); mkdir($dir . '/sessions');
$server = null; $cookies = []; $checks = 0; $createdImages = [];
function check($ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++; echo "OK: $message\n";
}
function request(string $path, ?array $post = null, ?array $upload = null): array {
    global $baseUrl, $cookies;
    $responseHeaders = [];
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIE => http_build_query($cookies, '', '; '),
        CURLOPT_HEADERFUNCTION => static function ($curl, $header) use (&$responseHeaders, &$cookies) {
            $responseHeaders[] = trim($header);
            if (preg_match('/^Set-Cookie: ([^=]+)=([^;]+)/i', $header, $m)) $cookies[$m[1]] = $m[2];
            return strlen($header);
        }]);
    if ($post !== null) {
        if ($upload) $post['image'] = new CURLFile($upload[0], $upload[1], $upload[2]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $upload ? $post : http_build_query($post));
    }
    $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl);
    if ($status >= 500) throw new RuntimeException("HTTP $status : $body");
    return [$status, $body, implode("\n", $responseHeaders)];
}
function token(string $path): string {
    [, $body] = request($path);
    if (!preg_match('/name="csrf" value="([^"]+)"/', $body, $m)) throw new RuntimeException('Jeton CSRF absent : ' . $path);
    return $m[1];
}
function login(string $email, string $password): array {
    global $cookies;
    $cookies = [];
    $csrf = token('/login.php');
    $response = request('/login.php', ['csrf' => $csrf, 'email' => $email, 'mot_de_passe' => $password]);
    check($response[0] === 302, 'Connexion de test');
    return $cookies;
}
try {
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS rewear[^;]*;|USE rewear;/', '', file_get_contents($root . '/database.sql'));
    $pdo->exec($schema);
    $migration = str_replace('USE rewear;', '', file_get_contents($root . '/update_v6_security.sql'));
    $pdo->exec($migration); $pdo->exec($migration);
    check(true, 'Migration de sécurité réexécutable');
    $secret = 'Phrase-test-' . bin2hex(random_bytes(10));
    $pdo->prepare('UPDATE users SET mot_de_passe = ? WHERE id = 1')->execute([password_hash($secret, PASSWORD_DEFAULT)]);
    $pdo->prepare('INSERT INTO users (nom,email,mot_de_passe) VALUES (?,?,?)')->execute(['Test sécurité', 'client@example.test', password_hash($secret, PASSWORD_DEFAULT)]);
    $uid = (int)$pdo->lastInsertId();
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $address = stream_socket_get_name($socket, false); fclose($socket); $baseUrl = 'http://' . $address;
    $env = getenv();
    foreach (['HOST' => $host, 'PORT' => $port, 'NAME' => $db, 'USER' => $user, 'PASS' => $password] as $key => $value) $env['REWEAR_DB_' . $key] = $value;
    $env['REWEAR_SITE_URL'] = $baseUrl; $env['REWEAR_MODE_DEV'] = '0'; $env['REWEAR_REQUIRE_HTTPS'] = '0';
    $args = [PHP_BINARY];
    if (!extension_loaded('gd')) { $args[] = '-d'; $args[] = 'extension=gd'; }
    $args = array_merge($args, ['-d', 'session.save_path="' . str_replace(chr(92), '/', $dir) . '/sessions"', '-d', 'disable_functions=mail', '-S', $address, '-t', $root]);
    $server = proc_open($args, [['pipe','r'], ['file',$dir . '/server.log','a'], ['file',$dir . '/server.log','a']], $pipes, $root, $env);
    fclose($pipes[0]);
    for ($i=0; $i<50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, .1);
        if ($ready) { fclose($ready); break; } usleep(100000);
    }
    // Vérifier le vrai bootstrap, avec 0 puis sans variable.
    foreach (['0', false] as $mode) {
        $probeEnv = $env;
        if ($mode === false) unset($probeEnv['REWEAR_MODE_DEV']);
        $probe = proc_open([PHP_BINARY, '-r', 'require "includes/bootstrap.php"; echo MODE_DEV ? "ON" : "OFF";'], [['pipe','r'], ['pipe','w'], ['pipe','w']], $probePipes, $root, $probeEnv);
        fclose($probePipes[0]); $output = stream_get_contents($probePipes[1]); fclose($probePipes[1]); fclose($probePipes[2]); proc_close($probe);
        check($output === 'OFF', 'Mode développement désactivé : ' . ($mode === false ? 'valeur absente' : '0'));
    }
    [$status, , $headers] = request('/login.php');
    check($status === 200 && stripos($headers,'HttpOnly') !== false && stripos($headers,'SameSite=Lax') !== false, 'Cookie HttpOnly et SameSite');
    check(str_contains($headers, "script-src 'self'") && str_contains($headers, 'nosniff') && str_contains($headers, 'no-referrer'), 'En-têtes de sécurité');
    $csrf = token('/login.php');
    check(request('/login.php', ['csrf' => ['incorrect'], 'email' => 'x'])[0] === 400, 'Jeton CSRF de type tableau refusé');
    $sqlResult = request('/login.php', ['csrf'=>$csrf,'email'=>"' OR 1=1 -- ",'mot_de_passe'=>'incorrect']);
    check($sqlResult[0] === 200, 'Injection SQL sans authentification (HTTP ' . $sqlResult[0] . ')');
    [, $search] = request('/catalogue.php?q=' . urlencode("' OR 1=1 -- "));
    check(!str_contains($search,'Jean droit bleu'), 'Recherche SQL traitée comme une valeur');
    [, $search] = request('/catalogue.php?q=' . urlencode('<script>alert(1)</script>'));
    check(!str_contains($search,'<script>alert(1)</script>') && str_contains($search,'&lt;script&gt;'), 'Recherche échappée contre XSS');
    // Parcours clients et séparation des données après les changements de session.
    $cookies=[]; $registerToken=token('/register.php');
    $registration=['csrf'=>$registerToken,'nom'=>'Second client','email'=>'second@example.test','telephone'=>'','adresse'=>'Adresse de test','mot_de_passe'=>$secret,'mot_de_passe2'=>$secret];
    check(request('/register.php',$registration)[0]===302,'Inscription avec mot de passe long');
    $otherId=(int)$pdo->query("SELECT id FROM users WHERE email='second@example.test'")->fetchColumn();
    $pdo->prepare('INSERT INTO dons(user_id,type_don,description,quantite,adresse_collecte) VALUES (?,?,?,?,?)')->execute([$otherId,'livres','DON_PRIVE_AUTRE_COMPTE',1,'Adresse']);
    $first = login('client@example.test', $secret);
    [, $ownAccount]=request('/mon-compte.php');
    check(!str_contains($ownAccount,'DON_PRIVE_AUTRE_COMPTE'),'Dons des autres comptes invisibles');
    $donToken=token('/don.php');
    check(request('/don.php',['csrf'=>$donToken,'types_don'=>['vetements','livres'],'description'=>'Don mixte de test','quantite'=>'2','adresse_collecte'=>'Adresse de test','date_souhaitee'=>''])[0]===302,'Don mixte enregistré');
    $buyToken=token('/produit.php?id=1');
    check(request('/produit.php?id=1',['csrf'=>$buyToken,'quantite'=>'1'])[0]===302,'Ajout au panier');
    $stockBefore=(int)$pdo->query('SELECT stock FROM produits WHERE id=1')->fetchColumn();
    $checkoutToken=token('/commande.php');
    check(request('/commande.php',['csrf'=>$checkoutToken,'adresse'=>'Adresse de test','mode_paiement'=>'livraison'])[0]===302,'Commande client validée');
    check((int)$pdo->query('SELECT stock FROM produits WHERE id=1')->fetchColumn()===$stockBefore-1,'Stock décrémenté une seule fois');
    $csrf = token('/mon-compte.php');
    $profile = ['csrf'=>$csrf,'action'=>'profil','nom'=>'Test sécurité','email'=>'autre@example.test','telephone'=>'','adresse'=>''];
    check(request('/mon-compte.php',$profile)[0]===200 && $pdo->query("SELECT email FROM users WHERE id=$uid")->fetchColumn()==='client@example.test','Changement e-mail exige le mot de passe actuel');
    check(request('/admin/produits.php')[0]===403, 'Client interdit dans administration');
    $second = login('client@example.test', $secret);
    $newSecret = 'Nouveau-test-' . bin2hex(random_bytes(10));
    $csrf = token('/mon-compte.php');
    request('/mon-compte.php',['csrf'=>$csrf,'action'=>'mot_de_passe','mot_de_passe_actuel'=>$secret,'nouveau_mot_de_passe'=>$newSecret,'confirmation_mot_de_passe'=>$newSecret]);
    check(request('/mon-compte.php')[0]===302, 'Session courante fermée après changement de mot de passe');
    $cookies=$first;
    check(request('/mon-compte.php')[0]===302, 'Autre session révoquée après changement de mot de passe');
    // Les mots de passe trop longs ne sont jamais tronqués par bcrypt.
    $cookies=[]; $registerToken=token('/register.php');
    $longPassword=str_repeat('a',73);
    check(request('/register.php',['csrf'=>$registerToken,'nom'=>'Long','email'=>'long@example.test','mot_de_passe'=>$longPassword,'mot_de_passe2'=>$longPassword])[0]===200 && (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email='long@example.test'")->fetchColumn()===0,'Mot de passe au-delà de 72 octets refusé');
    $beforeReset=login('client@example.test',$newSecret);
    $cookies=[]; $csrf=token('/mot-de-passe-oublie.php');
    [, $response] = request('/mot-de-passe-oublie.php',['csrf'=>$csrf,'email'=>'client@example.test']);
    check(!str_contains($response,'reinitialiser.php?token='), 'Aucun lien de récupération dans la réponse');
    $resetToken=bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE password_resets SET token_hash=? WHERE user_id=?')->execute([hash('sha256',$resetToken),$uid]);
    $resetPath='/reinitialiser.php?token='.$resetToken;
    $csrf=token($resetPath);
    check(request($resetPath,['csrf'=>$csrf,'mot_de_passe'=>'court','mot_de_passe2'=>'court'])[0]===200, 'Mot de passe trop court refusé');
    $finalSecret='Dernier-test-'.bin2hex(random_bytes(10));
    check(request($resetPath,['csrf'=>$csrf,'mot_de_passe'=>$finalSecret,'mot_de_passe2'=>$finalSecret])[0]===302,'Réinitialisation valide');
    check((int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE user_id=$uid")->fetchColumn()===0, 'Tous les jetons consommés après récupération');
    [, $invalidReset]=request($resetPath);
    check(!str_contains($invalidReset,'name="mot_de_passe"'), 'Jeton non réutilisable');
    $cookies=$beforeReset; check(request('/mon-compte.php')[0]===302,'Sessions révoquées après récupération');
    $expired=bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES (?,?,DATE_SUB(NOW(),INTERVAL 1 SECOND))')->execute([$uid,hash('sha256',$expired)]);
    [, $expiredBody]=request('/reinitialiser.php?token='.$expired);
    check(!str_contains($expiredBody,'name="mot_de_passe"'), 'Jeton expiré refusé');
    foreach (['auth_last_seen'=>1801,'auth_started'=>28801] as $field=>$age) {
        login('client@example.test',$finalSecret);
        $sessionFile=$dir.'/sessions/sess_'.$cookies['rewear_session'];
        $session=file_get_contents($sessionFile);
        $session=preg_replace('/'. $field .'\|i:\d+;/', $field.'|i:'.(time()-$age).';', $session);
        file_put_contents($sessionFile,$session);
        check(request('/mon-compte.php')[0]===302, 'Expiration de session : '.$field);
    }
    $logoutSession=login('client@example.test',$finalSecret); $csrf=token('/mon-compte.php');
    check(request('/logout.php',['csrf'=>$csrf])[0]===302,'Déconnexion');
    $cookies=$logoutSession; check(request('/mon-compte.php')[0]===302,'Ancien cookie inutilisable après déconnexion');
    $pdo->exec('DELETE FROM auth_throttles');
    for($i=0;$i<11;$i++) { $cookies=[]; $csrf=token('/login.php'); $result=request('/login.php',['csrf'=>$csrf,'email'=>'client@example.test','mot_de_passe'=>'incorrect']); }
    check($result[0]===429 && str_contains($result[2],'Retry-After:'),'Limitation connexion malgré nouveaux cookies');
    $pdo->exec('DELETE FROM auth_throttles');
    for($i=0;$i<4;$i++) { $cookies=[]; $csrf=token('/mot-de-passe-oublie.php'); $result=request('/mot-de-passe-oublie.php',['csrf'=>$csrf,'email'=>'absent@example.test']); }
    check($result[0]===429,'Limitation récupération même pour compte absent');
    $pdo->exec('DELETE FROM auth_throttles');
    login('admin@rewear.mu',$secret); $csrf=token('/admin/produits.php');
    $product=['csrf'=>$csrf,'action'=>'sauver','id'=>'0','nom'=>'Photo de test','description'=>'Test','categorie'=>'Homme','taille'=>'M','etat'=>'Bon état','prix'=>'10','stock'=>'1'];
    $count=(int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
    file_put_contents($dir.'/fake.jpg','<?php echo "UNTRUSTED"; ?>');
    $result=request('/admin/produits.php',$product,[$dir.'/fake.jpg','image/jpeg','fake.jpg']);
    check($result[0]===200 && (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn()===$count,'Faux JPEG refusé malgré extension et MIME client');
    file_put_contents($dir.'/large.jpg',str_repeat('A',3*1024*1024+1));
    request('/admin/produits.php',$product,[$dir.'/large.jpg','image/jpeg','large.jpg']);
    check((int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn()===$count, 'Fichier de plus de 3 Mo refusé');
    // Une image minuscule avec contenu ajouté doit être réencodée, pas recopiée.
    // Fixture PNG générée avec GD pour éviter une dépendance aux métadonnées externes.
    $imageArgs = extension_loaded('gd') ? [PHP_BINARY] : [PHP_BINARY, '-d', 'extension=gd'];
    $fixture=proc_open(array_merge($imageArgs,['-r','$im=imagecreatetruecolor(2,2); imagepng($im,$argv[1]); imagedestroy($im);',$dir.'/valid.png']),[['pipe','r'],['pipe','w'],['pipe','w']],$fixturePipes);
    fclose($fixturePipes[0]); stream_get_contents($fixturePipes[1]); fclose($fixturePipes[1]); fclose($fixturePipes[2]); proc_close($fixture);
    file_put_contents($dir.'/valid.png','<?php echo "UNTRUSTED"; ?>',FILE_APPEND);
    check(request('/admin/produits.php',$product,[$dir.'/valid.png','image/png','image.php'])[0]===302,'Image valide acceptée et renommée avec extension sûre');
    $image=$pdo->query('SELECT image FROM produits ORDER BY id DESC LIMIT 1')->fetchColumn(); $createdImages[]=$root.'/uploads/'.$image;
    check(preg_match('/^[a-f0-9]{32}\.png$/D',$image) && !str_contains(file_get_contents($root.'/uploads/'.$image),'UNTRUSTED'),'Réencodage supprime le contenu ajouté');
    // Dimensions excessives : petit fichier compressé mais grande image.
    $wideFixture=proc_open(array_merge($imageArgs,['-r','$im=imagecreatetruecolor(6001,1); imagepng($im,$argv[1]); imagedestroy($im);',$dir.'/wide.png']),[['pipe','r'],['pipe','w'],['pipe','w']],$widePipes);
    fclose($widePipes[0]); stream_get_contents($widePipes[1]); fclose($widePipes[1]); fclose($widePipes[2]); proc_close($wideFixture);
    $beforeWide=(int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn();
    check(request('/admin/produits.php',$product,[$dir.'/wide.png','image/png','wide.png'])[0]===200 && (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn()===$beforeWide,'Image aux dimensions excessives refusée');
    [, $details]=request('/admin/produits.php?voir=1');
    check(str_contains($details,'Jean droit bleu'), 'Consultation administrateur toujours disponible');
    $logText=file_get_contents($dir.'/server.log');
    check(!preg_match('/PHP (Warning|Fatal error|Notice)/',$logText),'Aucune erreur PHP dans les parcours testés');
    echo "$checks vérifications de sécurité réussies.\n";
} finally {
    if(is_resource($server)) {proc_terminate($server);proc_close($server);}
    $pdo->exec("DROP DATABASE `$db`");
    foreach($createdImages as $image) if(is_file($image)) unlink($image);
    foreach(glob($dir.'/sessions/*') as $file) unlink($file);
    rmdir($dir.'/sessions');
    foreach(glob($dir.'/*') as $file) if(is_file($file)) unlink($file);
    rmdir($dir);
}
