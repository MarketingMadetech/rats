<?php
/**
 * Admin Editor de RAT
 * Wrapper que configura o contexto admin e inclui o editor compartilhado do técnico.
 * O tecnico/editar-rat.php detecta $_SESSION['admin_id'] e ajusta seu comportamento.
 */
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/auth-admin.php';

verificarAdmin();

// Sinalizar que estamos no modo admin
$_SESSION['_admin_edit_mode'] = true;

// Incluir o editor do técnico (que agora suporta modo admin)
include __DIR__ . '/../tecnico/editar-rat.php';
