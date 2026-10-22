<?php
// admin/utilisateurs.php : gérer les comptes (créer un livreur, changer le rôle, activer/désactiver)
require_once __DIR__ . '/../includes/functions.php';
exiger_role('admin');

$roles = ['utilisateur', 'livreur', 'admin'];
$rolesEquipe = ['livreur', 'admin'];

function compte_avec_historique(int $id): bool {
    global $pdo;
    $s = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM commandes WHERE user_id = ? OR livreur_id = ?) OR EXISTS(SELECT 1 FROM dons WHERE user_id = ? OR livreur_id = ?)');
    $s->execute([$id, $id, $id, $id]);
    return (bool)$s->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verifier();
    $action = $_POST['action'] ?? '';
    $moi = user()['id'];

    if ($action === 'creer') {
        $nom = trim(champ_texte($_POST, 'nom')); $email = trim(champ_texte($_POST, 'email')); $mdp = champ_texte($_POST, 'mot_de_passe');
        $role = in_array($_POST['role'] ?? '', $rolesEquipe, true) ? $_POST['role'] : 'livreur';
        $existe = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $existe->execute([$email]);
        if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !mot_de_passe_valide($mdp) || mb_strlen($nom) > 100 || mb_strlen($email) > 150 || mb_strlen(champ_texte($_POST, 'telephone')) > 30) {
            flash('error', t('admin_user_required_fields'));
        } elseif ($existe->fetch()) {
            flash('error', t('err_email_used'));
        } else {
            $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe, telephone, role) VALUES (?,?,?,?,?)')
                ->execute([$nom, $email, password_hash($mdp, PASSWORD_DEFAULT), trim(champ_texte($_POST, 'telephone')), $role]);
            flash('success', t('admin_account_created'));
        }
    } elseif (in_array($action, ['role', 'actif', 'supprimer'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $nouveauRole = $_POST['role'] ?? '';

        if ($id === $moi) {
            flash('error', t('admin_self_protected'));
        } elseif ($action === 'role' && !in_array($nouveauRole, $roles, true)) {
            flash('error', t('admin_invalid_role'));
        } else {
            $pdo->beginTransaction();
            try {
                // Verrouiller les administrateurs actifs empêche deux actions simultanées
                // de supprimer accidentellement le dernier accès à l'administration.
                $admins = $pdo->query("SELECT id, actif FROM users WHERE role = 'admin' ORDER BY id FOR UPDATE")->fetchAll();
                $requeteCible = $pdo->prepare('SELECT id, role, actif FROM users WHERE id = ? FOR UPDATE');
                $requeteCible->execute([$id]);
                $cible = $requeteCible->fetch();

                if (!$cible) {
                    flash('error', t('admin_account_not_found'));
                } else {
                    $adminsActifs = count(array_filter($admins, static fn($admin) => (int)$admin['actif'] === 1));
                    $retireDernierAdmin = $cible['role'] === 'admin'
                        && (int)$cible['actif'] === 1
                        && $adminsActifs <= 1
                        && (($action === 'role' && $nouveauRole !== 'admin') || in_array($action, ['actif', 'supprimer'], true));

                    if ($retireDernierAdmin) {
                        flash('error', t('admin_keep_one_active'));
                    } elseif ($action === 'supprimer') {
                        if (compte_avec_historique($id)) {
                            flash('error', t('admin_user_delete_history'));
                        } else {
                            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                            flash('success', t('admin_user_deleted'));
                        }
                    } elseif ($action === 'role') {
                        $pdo->prepare('UPDATE users SET role = ?, session_version = session_version + 1 WHERE id = ?')->execute([$nouveauRole, $id]);
                        flash('success', t('admin_role_updated'));
                    } else {
                        $nouvelEtat = (int)$cible['actif'] === 1 ? 0 : 1;
                        $pdo->prepare('UPDATE users SET actif = ?, session_version = session_version + 1 WHERE id = ?')->execute([$nouvelEtat, $id]);
                        flash('success', t('admin_account_status_updated'));
                    }
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }
    redirect('utilisateurs.php');
}

$users = $pdo->query('SELECT * FROM users ORDER BY role, nom')->fetchAll();
$suppression = null;
if (isset($_GET['supprimer'])) {
    $s = $pdo->prepare('SELECT id, nom, email FROM users WHERE id = ?');
    $s->execute([(int)$_GET['supprimer']]);
    $suppression = $s->fetch();
    if (!$suppression) {
        flash('error', t('admin_account_not_found'));
        redirect('utilisateurs.php');
    }
    $suppressionBloquee = (int)$suppression['id'] === user()['id']
        ? 'admin_self_protected'
        : (compte_avec_historique((int)$suppression['id']) ? 'admin_user_delete_history' : null);
}

$base = '../'; $titre = t('admin_users'); $page = 'utilisateurs';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= t('admin_users') ?></h1>
<?php require __DIR__ . '/_menu.php'; ?>

<?php if ($suppression): ?>
<section class="form large" aria-labelledby="suppression-titre">
  <h2 id="suppression-titre"><?= t('admin_user_delete_title') ?></h2>
  <p><strong><?= e($suppression['nom']) ?></strong> — <?= e($suppression['email']) ?></p>
  <p><?= t($suppressionBloquee ?: 'admin_user_delete_confirm') ?></p>
  <form method="post" action="utilisateurs.php">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$suppression['id'] ?>">
    <?php if (!$suppressionBloquee): ?>
      <button class="btn btn-danger" type="submit" name="action" value="supprimer"><?= t('admin_delete_permanently') ?></button>
    <?php endif; ?>
    <a class="btn btn-outline" href="utilisateurs.php"><?= t('admin_cancel') ?></a>
  </form>
</section>
<?php else: ?>
<h2><?= t('admin_create_account_title') ?></h2>
<p class="muted"><?= t('admin_team_access_help') ?></p>
<form class="form large" method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="creer">
  <label><?= t('admin_name') ?></label><input type="text" name="nom" required>
  <label><?= t('email') ?></label><input type="email" name="email" required>
  <label><?= t('phone') ?></label><input type="tel" name="telephone">
  <label><?= t('admin_temp_password') ?></label><input type="password" name="mot_de_passe" required>
  <label><?= t('admin_role') ?></label>
  <select name="role"><?php foreach ($rolesEquipe as $r): ?><option value="<?= e($r) ?>" <?= $r === 'livreur' ? 'selected' : '' ?>><?= t('role_' . $r) ?></option><?php endforeach; ?></select>
  <button class="btn" type="submit"><?= t('admin_create') ?></button>
</form>

<h2><?= t('admin_all_accounts', count($users)) ?></h2>
<div class="table-wrap"><table>
  <tr><th><?= t('admin_name') ?></th><th><?= t('email') ?></th><th><?= t('phone') ?></th><th><?= t('admin_role') ?></th><th><?= t('admin_active') ?></th><th><?= t('admin_delete') ?></th></tr>
  <?php foreach ($users as $u): $moiMeme = (int)$u['id'] === user()['id']; ?>
  <tr>
    <td><?= e($u['nom']) ?></td><td><?= e($u['email']) ?></td><td><?= e($u['telephone']) ?></td>
    <td>
      <?php if ($moiMeme): ?><?= t('role_' . $u['role']) ?> (<?= t('admin_you') ?>)<?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <select name="role" data-submit-on-change><?php foreach ($roles as $r): ?><option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= t('role_' . $r) ?></option><?php endforeach; ?></select></form>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($moiMeme): ?><?= t('admin_yes') ?><?php else: ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="actif"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <button class="btn btn-small <?= $u['actif'] ? 'btn-danger' : '' ?>" type="submit"><?= $u['actif'] ? t('admin_disable') : t('admin_reactivate') ?></button></form>
      <?php endif; ?>
    </td>
    <td><?php if (!$moiMeme): ?><a class="btn btn-small btn-danger" href="utilisateurs.php?supprimer=<?= (int)$u['id'] ?>"><?= t('admin_delete') ?></a><?php else: ?>—<?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
