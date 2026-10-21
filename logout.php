<?php
require_once __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_verifier();
fermer_session_auth();
flash('info', t('logged_out'));
redirect('index.php');
