<?php
// logout.php : déconnexion protégée (on garde la langue choisie)
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_verifier();
$lang = langue();
$_SESSION = [];
session_destroy();
session_start();
$_SESSION['lang'] = $lang;
flash('info', t('logged_out'));
redirect('index.php');
