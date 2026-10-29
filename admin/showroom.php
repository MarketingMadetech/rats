<?php
/**
 * Admin - Máquinas Showroom
 * Wrapper que configura o contexto admin e inclui a página compartilhada do Showroom.
 */
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/auth-admin.php';

verificarAdmin();

$_showroom_admin_mode = true;
include __DIR__ . '/../tecnico/showroom.php';

