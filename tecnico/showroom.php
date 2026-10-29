<?php
$_is_admin_mode = !empty($_showroom_admin_mode);

if ($_is_admin_mode) {
    require_once __DIR__ . '/../api/auth-admin.php';
    require_once __DIR__ . '/../api/config.php';
    verificarAdmin();

    $db = getDB();
    inicializarShowroom($db);

    $total_rats = (int)$db->query("SELECT COUNT(*) as total FROM rats")->fetch()['total'];
    $rascunho = (int)$db->query("SELECT COUNT(*) as total FROM rats WHERE status = 'rascunho'")->fetch()['total'];
    $enviado = (int)$db->query("SELECT COUNT(*) as total FROM rats WHERE status = 'enviado'")->fetch()['total'];
    $total_tecnicos = (int)$db->query("SELECT COUNT(*) as total FROM tecnicos")->fetch()['total'];

    $usuario_nome = $_SESSION['admin_nome'] ?? 'Administrador';
    $usuario_email = $_SESSION['admin_email'] ?? 'admin@madetech.com.br';
} else {
    require_once __DIR__ . '/../api/auth-tecnico.php';
    require_once __DIR__ . '/../api/config.php';
    verificarTecnico();

    $db = getDB();
    inicializarShowroom($db);

    $tecnico_id = $_SESSION['tecnico_id'];
    $usuario_nome = $_SESSION['tecnico_nome'] ?? 'Técnico';
    $usuario_email = $_SESSION['tecnico_email'] ?? '';

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ?");
    $stmt->execute([$tecnico_id]);
    $total_rats = (int)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ? AND status = 'rascunho'");
    $stmt->execute([$tecnico_id]);
    $rascunho = (int)$stmt->fetch()['total'];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ? AND status = 'enviado'");
    $stmt->execute([$tecnico_id]);
    $enviado = (int)$stmt->fetch()['total'];
}

$iniciais = strtoupper(substr(trim($usuario_nome), 0, 1)) ?: ($_is_admin_mode ? 'A' : 'T');
$showroom_problemas = contarProblemasShowroom($db);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Máquinas Showroom | Sistema RAT - Madetech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #034c8c; --primary-light: #0466c8; --primary-dark: #022d54;
            --secondary: #f58220; --success: #10b981; --warning: #f59e0b;
            --danger: #ef4444; --purple: #8b5cf6; --teal: #14b8a6;
            --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db;
            --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151;
            --gray-800: #1f2937; --gray-900: #111827;
            --sidebar-width: 260px; --header-height: 70px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; color: var(--gray-900); min-height: 100vh; overflow-x: hidden; }

        /* ========== SIDEBAR (igual ao painel) ========== */
        .sidebar { position: fixed; left: 0; top: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%); z-index: 200; display: flex; flex-direction: column; overflow: hidden; }
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .sidebar-brand { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand .logo-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .sidebar-brand img { height: 36px; filter: brightness(0) invert(1); }
        .sidebar-brand h2 { color: white; font-size: 18px; font-weight: 700; letter-spacing: -0.5px; }
        .sidebar-brand .version-badge { display: inline-block; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9); font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 20px; letter-spacing: 0.5px; text-transform: uppercase; }
        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-section { margin-bottom: 24px; }
        .nav-section-title { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1.5px; padding: 0 12px 8px; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 10px; font-size: 14px; font-weight: 500; transition: all 0.3s; position: relative; margin-bottom: 4px; }
        .nav-item i { width: 20px; text-align: center; font-size: 16px; }
        .nav-item:hover { background: rgba(255,255,255,0.12); color: white; transform: translateX(4px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 24px; background: white; border-radius: 0 4px 4px 0; }
        .nav-badge { margin-left: auto; background: var(--secondary); color: white; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; min-width: 24px; text-align: center; }
        .nav-badge.green { background: var(--success); }
        .sidebar-cta { margin: 0 12px 16px; padding: 14px; background: linear-gradient(135deg, var(--secondary), #ff9940); border-radius: 12px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 10px; color: white; font-weight: 700; font-size: 14px; box-shadow: 0 4px 16px rgba(245,130,32,0.4); transition: transform .3s; }
        .sidebar-cta:hover { transform: translateY(-3px); }
        .sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-user { display: flex; align-items: center; gap: 12px; }
        .sidebar-user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--teal), #0d9488); display: flex; align-items: center; justify-content: center; font-size: 16px; color: white; font-weight: 700; }
        .sidebar-user-info { flex: 1; min-width: 0; }
        .sidebar-user-info strong { display: block; color: white; font-size: 13px; font-weight: 600; }
        .sidebar-user-info small { color: rgba(255,255,255,0.5); font-size: 11px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sidebar-logout { color: rgba(255,255,255,0.5); font-size: 16px; text-decoration: none; }
        .sidebar-logout:hover { color: var(--danger); }

        /* ========== MAIN ========== */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; }
        .topbar { position: sticky; top: 0; height: var(--header-height); background: white; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .topbar-left h1 { font-size: 20px; font-weight: 700; }
        .topbar-breadcrumb { font-size: 13px; color: var(--gray-400); }
        .topbar-breadcrumb a { color: var(--primary); text-decoration: none; }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .topbar-btn { width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--gray-200); background: white; display: flex; align-items: center; justify-content: center; color: var(--gray-500); cursor: pointer; transition: all 0.3s; text-decoration: none; font-size: 14px; }
        .topbar-btn:hover { color: var(--primary); border-color: var(--primary); transform: translateY(-2px); }
        .content { padding: 32px; }

        /* Status strip */
        .status-strip { display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; margin-bottom: 24px; }
        .status-tile { background: white; border: 1px solid var(--gray-200); border-radius: 14px; padding: 16px 18px; cursor: pointer; transition: all .25s; position: relative; overflow: hidden; text-align: left; font-family: inherit; }
        .status-tile::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--c, var(--primary)); }
        .status-tile:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,.07); }
        .status-tile.selected { border-color: var(--c, var(--primary)); box-shadow: 0 0 0 2px color-mix(in srgb, var(--c, var(--primary)) 25%, transparent); }
        .status-tile .n { font-size: 28px; font-weight: 800; letter-spacing: -1px; color: var(--gray-900); line-height: 1; }
        .status-tile .l { font-size: 12px; color: var(--gray-500); font-weight: 600; margin-top: 6px; display: flex; align-items: center; gap: 6px; }
        .status-tile .l i { color: var(--c, var(--primary)); }

        /* Inventory section */
        .inventory-box { background: white; border: 1px solid var(--gray-200); border-radius: 16px; padding: 18px 20px; margin-bottom: 22px; }
        .inventory-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .inventory-title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .inventory-title-icon { width: 34px; height: 34px; border-radius: 10px; background: rgba(3,76,140,.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 15px; }
        .inventory-title h2 { font-size: 15px; font-weight: 700; color: var(--gray-900); }
        .inv-kpis { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .inv-kpi { font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px; }
        .inv-kpi.total { background: rgba(3,76,140,.1); color: var(--primary); }
        .inv-kpi.types { background: rgba(20,184,166,.12); color: #0f766e; }
        .inv-kpi.models { background: rgba(139,92,246,.12); color: #6d28d9; }
        .inv-kpi.ok { background: rgba(16,185,129,.12); color: #047857; }
        .inv-kpi.showing { background: rgba(245,130,32,.14); color: #c2410c; }
        .inv-clear { padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(239,68,68,.3); background: rgba(239,68,68,.06); color: var(--danger); font: 600 12px 'Inter', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
        .inv-clear:hover { background: var(--danger); color: white; border-color: var(--danger); }
        .inv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
        .inv-card { display: flex; flex-direction: column; gap: 10px; padding: 14px; border-radius: 12px; border: 1px solid var(--gray-200); background: var(--gray-50); cursor: pointer; text-align: left; font-family: inherit; transition: all .2s; position: relative; }
        .inv-card:hover { border-color: var(--primary); background: white; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(3,76,140,.08); }
        .inv-card.selected { background: rgba(3,76,140,.05); border-color: var(--primary); box-shadow: 0 0 0 2px rgba(3,76,140,.18); }
        .inv-card-top { display: flex; align-items: center; gap: 12px; width: 100%; }
        .inv-icon { width: 38px; height: 38px; border-radius: 10px; background: white; border: 1px solid var(--gray-200); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .inv-card.selected .inv-icon { background: var(--primary); color: white; border-color: var(--primary); }
        .inv-info { flex: 1; min-width: 0; }
        .inv-name { font-size: 13px; font-weight: 700; color: var(--gray-900); line-height: 1.25; }
        .inv-sub { font-size: 11px; color: var(--gray-500); margin-top: 3px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .inv-dot { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; }
        .inv-dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--dc, var(--gray-400)); }
        .inv-count { font-size: 24px; font-weight: 800; color: var(--gray-900); letter-spacing: -0.5px; line-height: 1; flex-shrink: 0; }
        .inv-card.selected .inv-count { color: var(--primary); }
        .inv-models { display: flex; flex-wrap: wrap; gap: 5px; padding-top: 8px; border-top: 1px dashed var(--gray-200); }
        .inv-model-chip { font: 600 11px 'Inter', sans-serif; padding: 3px 8px; border-radius: 6px; background: white; border: 1px solid var(--gray-200); color: var(--gray-700); cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all .15s; }
        .inv-model-chip:hover { border-color: var(--primary); color: var(--primary); }
        .inv-model-chip.on { background: var(--primary); border-color: var(--primary); color: white; }
        .inv-model-chip b { font-weight: 800; opacity: .85; }
        .inv-empty-types { margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--gray-100); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 11px; color: var(--gray-500); }
        .inv-empty-chip { font: 600 11px 'Inter', sans-serif; padding: 3px 9px; border-radius: 20px; border: 1px dashed var(--gray-300); background: var(--gray-50); color: var(--gray-600); cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all .15s; }
        .inv-empty-chip:hover { border-color: var(--secondary); color: var(--secondary); background: white; border-style: solid; }
        .machine-extra { font-size: 12px; color: var(--gray-700); background: rgba(3,76,140,.05); border: 1px solid rgba(3,76,140,.12); border-radius: 8px; padding: 7px 10px; display: flex; align-items: flex-start; gap: 7px; line-height: 1.4; }
        .machine-extra i { color: var(--primary); margin-top: 2px; font-size: 12px; flex-shrink: 0; }
        .field-hint { font-size: 11px; color: var(--gray-400); margin-top: 4px; }
        .badges-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
        .badges-row .state-badge { margin-top: 0; }
        .qtd-stepper { display: inline-flex; align-items: center; border: 1px solid var(--gray-200); border-radius: 20px; background: var(--gray-50); overflow: hidden; height: 26px; }
        .qtd-stepper.multi { border-color: rgba(3,76,140,.35); background: rgba(3,76,140,.07); }
        .qtd-stepper button { width: 24px; height: 26px; border: none; background: transparent; color: var(--gray-600); font: 700 13px 'Inter', sans-serif; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .15s; }
        .qtd-stepper button:hover { background: var(--primary); color: white; }
        .qtd-stepper span { padding: 0 7px; font: 700 12px 'Inter', sans-serif; color: var(--gray-800); min-width: 38px; text-align: center; }
        .qtd-stepper.multi span { color: var(--primary); }
        .qtd-input-group { display: flex; align-items: center; gap: 6px; }
        .qtd-input-group input { text-align: center; font-weight: 700; }
        .qtd-step-btn { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--gray-200); background: var(--gray-50); color: var(--gray-700); font: 700 16px 'Inter', sans-serif; cursor: pointer; flex-shrink: 0; display: flex; align-items: center; justify-content: center; transition: all .15s; }
        .qtd-step-btn:hover { border-color: var(--primary); color: var(--primary); background: white; }

        /* Toolbar */
        .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; }
        .search { flex: 1; min-width: 200px; position: relative; }
        .search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 13px; }
        .search input { width: 100%; padding: 10px 12px 10px 34px; border-radius: 10px; border: 1px solid var(--gray-200); font: 500 14px 'Inter', sans-serif; background: white; }
        .filter-select { padding: 10px 12px; border-radius: 10px; border: 1px solid var(--gray-200); font: 600 13px 'Inter', sans-serif; background: white; color: var(--gray-700); cursor: pointer; min-width: 190px; }
        .search input:focus, .filter-select:focus, .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(3,76,140,.1); }
        .btn { padding: 10px 16px; border-radius: 10px; border: 1px solid var(--gray-200); background: white; color: var(--gray-700); font: 600 13px 'Inter', sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all .25s; white-space: nowrap; }
        .btn:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: linear-gradient(135deg, var(--secondary), #ff9940); color: white; border: none; box-shadow: 0 4px 12px rgba(245,130,32,.3); }
        .btn-primary:hover { color: white; transform: translateY(-2px); }
        .btn-blue { background: var(--primary); color: white; border-color: var(--primary); }
        .btn-blue:hover { background: var(--primary-light); color: white; }
        .btn-danger { color: var(--danger); border-color: rgba(239,68,68,.3); }
        .btn-danger:hover { background: var(--danger); color: white; border-color: var(--danger); }
        .mode-pill { font-size: 11px; font-weight: 700; padding: 5px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px; }
        .mode-pill.server { background: #d1fae5; color: #065f46; }
        .mode-pill.local { background: #fef3c7; color: #b45309; }

        /* Machine grid */
        .machine-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px; }
        .machine { background: white; border: 1px solid var(--gray-200); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .25s; }
        .machine:hover { box-shadow: 0 12px 32px rgba(0,0,0,.07); }
        .machine-head { padding: 18px 20px 14px; border-top: 4px solid var(--c); display: flex; gap: 14px; align-items: flex-start; }
        .machine-icon { width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--c); background: color-mix(in srgb, var(--c) 12%, white); }
        .machine-title { flex: 1; min-width: 0; }
        .machine-title h3 { font-size: 16px; font-weight: 700; color: var(--gray-900); line-height: 1.25; }
        .machine-title .meta { font-size: 12px; color: var(--gray-500); margin-top: 3px; }
        .machine-title .meta span + span::before { content: ' · '; }
        .state-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 20px; font-size: 12px; font-weight: 700; color: var(--c); background: color-mix(in srgb, var(--c) 12%, white); margin-top: 10px; }
        .state-badge::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--c); }
        .icon-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--gray-200); background: white; color: var(--gray-400); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all .2s; flex-shrink: 0; }
        .icon-btn:hover { color: var(--primary); border-color: var(--primary); }
        .machine-body { padding: 0 20px 16px; display: flex; flex-direction: column; gap: 12px; flex: 1; }
        .state-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
        .state-opt { padding: 8px 4px; border-radius: 8px; border: 1px solid var(--gray-200); background: var(--gray-50); font: 600 11px 'Inter', sans-serif; color: var(--gray-600); cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all .2s; line-height: 1.2; text-align: center; }
        .state-opt i { font-size: 14px; color: var(--sc); }
        .state-opt:hover { border-color: var(--sc); }
        .state-opt.on { background: var(--sc); border-color: var(--sc); color: white; }
        .state-opt.on i { color: white; }
        .machine-body textarea { width: 100%; min-height: 76px; resize: vertical; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--gray-200); font: 400 13px/1.45 'Inter', sans-serif; color: var(--gray-800); background: var(--gray-50); }
        .machine-body textarea:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 3px rgba(3,76,140,.1); }
        .machine-foot { padding: 12px 20px; border-top: 1px solid var(--gray-100); display: flex; align-items: center; justify-content: space-between; gap: 10px; background: var(--gray-50); }
        .updated { font-size: 11px; color: var(--gray-500); line-height: 1.4; }
        .updated strong { color: var(--gray-700); font-weight: 600; }
        .save-btn { padding: 7px 14px; font-size: 12px; }
        .save-btn[disabled] { opacity: .45; cursor: default; transform: none !important; box-shadow: none; }
        .dirty .save-btn { animation: nudge 1.6s ease-in-out infinite; }
        @keyframes nudge { 50% { box-shadow: 0 0 0 4px rgba(245,130,32,.25); } }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--gray-400); background: white; border-radius: 16px; border: 1px dashed var(--gray-300); grid-column: 1 / -1; }
        .empty-state i { font-size: 44px; margin-bottom: 14px; opacity: .5; }
        .empty-state h3 { font-size: 17px; color: var(--gray-700); margin-bottom: 6px; }

        /* Modal */
        .modal-bg { position: fixed; inset: 0; background: rgba(17,24,39,.5); backdrop-filter: blur(3px); z-index: 400; display: none; align-items: center; justify-content: center; padding: 16px; }
        .modal-bg.open { display: flex; }
        .modal { background: white; border-radius: 18px; width: 100%; max-width: 480px; max-height: 92vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(0,0,0,.25); animation: pop .3s cubic-bezier(.34,1.56,.64,1); }
        @keyframes pop { from { opacity: 0; transform: scale(.94) translateY(10px); } }
        .modal-head { padding: 20px 24px; border-bottom: 1px solid var(--gray-100); display: flex; align-items: center; justify-content: space-between; }
        .modal-head h3 { font-size: 17px; font-weight: 700; }
        .modal-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; }
        .field input, .field select, .field textarea { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--gray-200); font: 500 14px 'Inter', sans-serif; background: white; color: var(--gray-900); }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-foot { padding: 16px 24px; border-top: 1px solid var(--gray-100); display: flex; justify-content: space-between; gap: 10px; }

        .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(120px); background: var(--gray-900); color: white; padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; z-index: 500; transition: transform .35s cubic-bezier(.34,1.56,.64,1); display: flex; align-items: center; gap: 10px; }
        .toast.show { transform: translateX(-50%) translateY(0); }
        .toast.err { background: var(--danger); }

        /* Mobile */
        .mobile-toggle { display: none; position: fixed; top: 15px; left: 14px; z-index: 300; width: 40px; height: 40px; border-radius: 10px; background: var(--primary); color: white; border: none; font-size: 18px; cursor: pointer; }
        .sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 199; }
        .sidebar-backdrop.active { display: block; }
        @media (max-width: 1280px) {
            .status-strip { grid-template-columns: repeat(3, 1fr); }
            .inv-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; }
            .topbar { padding: 0 20px 0 68px; }
            .content { padding: 24px 20px; }
        }
        @media (max-width: 768px) {
            .content { padding: 16px 12px; }
            .topbar { height: 60px; padding: 0 12px 0 68px; }
            .mobile-toggle { top: 10px; }
            .topbar-left h1 { font-size: 15px; }
            .topbar-breadcrumb { display: none; }
            .status-strip { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .inv-grid { grid-template-columns: 1fr; }
            .filter-select { width: 100%; min-width: 0; }
            .machine-grid { grid-template-columns: 1fr; }
            .toolbar .btn-text { display: none; }
            .field-row { grid-template-columns: 1fr; }
        }
        @media print {
            .sidebar, .topbar, .mobile-toggle, .toolbar, .save-btn, .icon-btn, .inv-clear, .qtd-stepper button, .sidebar-backdrop { display: none !important; }
            .main-content { margin-left: 0; }
            .machine, .inventory-box { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
    <button class="mobile-toggle" id="mobileToggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-row">
                <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech">
                <h2>Sistema RAT</h2>
            </div>
            <span class="version-badge"><?= $_is_admin_mode ? 'Admin v1.0' : 'Técnico v1.0' ?></span>
        </div>

        <nav class="sidebar-nav">
            <?php if ($_is_admin_mode): ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    Dashboard
                </a>
                <a href="tecnicos.php" class="nav-item">
                    <i class="fas fa-users-cog"></i>
                    Técnicos
                    <span class="nav-badge"><?= $total_tecnicos ?></span>
                </a>
                <a href="criar-rat.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    Criar RAT
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">RATs</div>
                <a href="index.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    Todos os RATs
                    <span class="nav-badge"><?= $total_rats ?></span>
                </a>
                <a href="index.php?status=rascunho" class="nav-item">
                    <i class="fas fa-pencil-alt"></i>
                    Rascunhos
                    <span class="nav-badge"><?= $rascunho ?></span>
                </a>
                <a href="index.php?status=enviado" class="nav-item">
                    <i class="fas fa-paper-plane"></i>
                    Enviados
                    <span class="nav-badge green"><?= $enviado ?></span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Showroom</div>
                <a href="showroom.php" class="nav-item active">
                    <i class="fas fa-industry"></i>
                    Máquinas Showroom
                    <span class="nav-badge <?= $showroom_problemas > 0 ? '' : 'green' ?>" id="navBadge" title="Máquinas com problema"><?= $showroom_problemas ?></span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Acesso</div>
                <a href="../tecnico/login.php" class="nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    Painel Técnico
                </a>
                <a href="https://madetech.com.br/checklist/" target="_blank" class="nav-item">
                    <i class="fas fa-clipboard-check"></i>
                    Checklist
                </a>
                <a href="https://madetech.com.br/checklist-maquina/" target="_blank" class="nav-item">
                    <i class="fas fa-cogs"></i>
                    Máquinas Checklist
                </a>
            </div>
            <?php else: ?>
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item"><i class="fas fa-chart-pie"></i> Meu Painel</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Meus RATs</div>
                <a href="index.php?status=todos" class="nav-item">
                    <i class="fas fa-clipboard-list"></i> Todos
                    <span class="nav-badge"><?= $total_rats ?></span>
                </a>
                <a href="index.php?status=rascunho" class="nav-item">
                    <i class="fas fa-pencil-alt"></i> Rascunhos
                    <span class="nav-badge"><?= $rascunho ?></span>
                </a>
                <a href="index.php?status=enviado" class="nav-item">
                    <i class="fas fa-paper-plane"></i> Enviados
                    <span class="nav-badge green"><?= $enviado ?></span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Showroom</div>
                <a href="showroom.php" class="nav-item active">
                    <i class="fas fa-industry"></i> Máquinas Showroom
                    <span class="nav-badge <?= $showroom_problemas > 0 ? '' : 'green' ?>" id="navBadge" title="Máquinas com problema"><?= $showroom_problemas ?></span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Acesso</div>
                <a href="https://madetech.com.br/checklist/" target="_blank" class="nav-item">
                    <i class="fas fa-clipboard-check"></i> Checklist
                </a>
                <a href="https://madetech.com.br/checklist-maquina/" target="_blank" class="nav-item">
                    <i class="fas fa-cogs"></i> Máquinas Checklist
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <?php if (!$_is_admin_mode): ?>
        <a href="criar-rat.php" class="sidebar-cta"><i class="fas fa-plus-circle"></i> Criar Novo RAT</a>
        <?php endif; ?>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar" id="userAvatar"><?= htmlspecialchars($iniciais) ?></div>
                <div class="sidebar-user-info">
                    <strong id="userName"><?= htmlspecialchars($usuario_nome) ?></strong>
                    <small><?= htmlspecialchars($usuario_email) ?></small>
                </div>
                <a href="../api/logout.php" class="sidebar-logout" title="Sair"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1>Máquinas Showroom</h1>
                <div class="topbar-breadcrumb">
                    <a href="index.php"><?= $_is_admin_mode ? 'Admin' : 'Técnico' ?></a> / Showroom
                </div>
            </div>
            <div class="topbar-right">
                <button class="topbar-btn" title="Imprimir" onclick="window.print()"><i class="fas fa-print"></i></button>
                <button class="topbar-btn" title="Atualizar" onclick="load()"><i class="fas fa-sync-alt"></i></button>
                <a href="../api/logout.php" class="topbar-btn" title="Sair"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="content">
            <div class="status-strip" id="statusStrip"></div>

            <div class="inventory-box" id="inventoryBox"></div>

            <div class="toolbar">
                <div class="search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Buscar máquina, tipo, modelo, série ou informação..." oninput="render()">
                </div>
                <select id="typeSelect" class="filter-select" title="Filtrar por tipo / família" onchange="setFilterTipo(this.value || null, true)"></select>
                <select id="modelSelect" class="filter-select" title="Filtrar por modelo" onchange="setFilterModelo(this.value || null, true)"></select>
                <span class="mode-pill" id="modePill"></span>
                <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i><span class="btn-text">Nova Máquina</span></button>
            </div>

            <div class="machine-grid" id="grid"></div>
        </div>
    </main>

    <!-- MODAL -->
    <div class="modal-bg" id="modalBg" onclick="if(event.target===this) closeModal()">
        <div class="modal">
            <div class="modal-head">
                <h3 id="modalTitle">Nova Máquina</h3>
                <button class="icon-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="field-row">
                    <div class="field">
                        <label>Tipo / Família *</label>
                        <select id="fTipo" onchange="onTipoModalChange()"></select>
                    </div>
                    <div class="field">
                        <label>Estado</label>
                        <select id="fEstado"></select>
                    </div>
                </div>
                <div class="field" id="fTipoNovoWrap" style="display:none">
                    <label>Nome do novo tipo / categoria *</label>
                    <input id="fTipoNovo" placeholder="Ex.: Solda a Laser, Lixadeira, Coletor de Pó..." oninput="updateNomePlaceholder()">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label>Modelo / Versão</label>
                        <input id="fModelo" list="modelosDatalist" placeholder="Ex.: MaxDrill M6.5, Contour S-Line..." oninput="updateNomePlaceholder()">
                        <datalist id="modelosDatalist"></datalist>
                    </div>
                    <div class="field">
                        <label>Quantidade de máquinas *</label>
                        <div class="qtd-input-group">
                            <button type="button" class="qtd-step-btn" onclick="stepModalQtd(-1)" title="Diminuir">−</button>
                            <input type="number" id="fQuantidade" min="1" max="9999" step="1" value="1">
                            <button type="button" class="qtd-step-btn" onclick="stepModalQtd(1)" title="Aumentar">+</button>
                        </div>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label>Nome de exibição no card</label>
                        <input id="fNome" placeholder="Ex.: Centro de Furação MaxDrill M6.5">
                        <div class="field-hint">Em branco = Tipo + Modelo automático.</div>
                    </div>
                    <div class="field">
                        <label>Nº de série / Matrícula</label>
                        <input id="fSerie" placeholder="Opcional (ou vários separados por vírgula)">
                    </div>
                </div>
                <div class="field">
                    <label>Informações adicionais / Especificações (opcional)</label>
                    <input id="fInfoExtra" placeholder="Ex.: 220V/380V, mesa de descarga, reservada comercial, setor...">
                </div>
                <div class="field">
                    <label>Observação técnica / Situação</label>
                    <textarea id="fObs" rows="3" placeholder="Situação atual, peças pendentes, o que foi feito..."></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-danger" id="btnDelete" onclick="deleteMachine()"><i class="fas fa-trash"></i> Excluir</button>
                <div style="display:flex;gap:10px;margin-left:auto">
                    <button class="btn" onclick="closeModal()">Cancelar</button>
                    <button class="btn btn-blue" onclick="saveModal()"><i class="fas fa-check"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

<script>
/* ================= CONFIG ================= */
const API_URL = '../api/showroom.php';
const LS_KEY  = 'madetech_showroom_v1';
const PANEL_CONTEXT = <?= json_encode($_is_admin_mode ? 'admin' : 'tecnico') ?>;

const ESTADOS = {
    operacional: { label: 'Operacional',     icon: 'fa-circle-check',          color: '#10b981' },
    restricao:   { label: 'Com restrição',   icon: 'fa-triangle-exclamation',  color: '#f59e0b' },
    manutencao:  { label: 'Em manutenção',   icon: 'fa-screwdriver-wrench',    color: '#f58220' },
    aguardando:  { label: 'Aguardando peça', icon: 'fa-box-open',              color: '#8b5cf6' },
    parada:      { label: 'Parada',          icon: 'fa-circle-xmark',          color: '#ef4444' },
    sem_info:    { label: 'Sem informação',  icon: 'fa-circle-question',       color: '#9ca3af' }
};
const ESTADOS_PROBLEMA = ['restricao', 'manutencao', 'aguardando', 'parada'];

// Famílias base sugeridas (o sistema aceita livremente qualquer novo tipo criado no painel)
const TIPOS_SUGERIDOS = [
    'Centro Nesting CNC',
    'Linha Nesting',
    'Coladeira de Bordos',
    'Centro de Furação',
    'Seccionadora Vertical',
    'Esquadrejadeira',
    'Guilhotina Hidráulica'
];

const LEGACY_TIPO_MAP = {
    router:          'Centro Nesting CNC',
    coladeira:       'Coladeira de Bordos',
    seccionadora:    'Seccionadora Vertical',
    furadeira:       'Centro de Furação',
    esquadrejadeira: 'Esquadrejadeira',
    laser:           'Solda a Laser',
    prensa:          'Guilhotina Hidráulica',
    outro:           'Outro'
};

function normalizeTipo(t) {
    const raw = String(t ?? '').trim();
    if (!raw) return 'Outro';
    const mapped = LEGACY_TIPO_MAP[raw.toLowerCase()];
    return mapped || raw;
}

function iconForTipo(tipo) {
    const s = String(tipo ?? '').toLowerCase();
    if (/nesting|router|cnc|usinagem/.test(s)) return 'fa-gears';
    if (/coladeira|bordo|borda/.test(s)) return 'fa-tape';
    if (/seccionadora/.test(s)) return 'fa-ruler-horizontal';
    if (/fura[cç][aã]o|furadeira|maxdrill|drill/.test(s)) return 'fa-bore-hole';
    if (/esquadrejadeira|serra/.test(s)) return 'fa-table-cells-large';
    if (/guilhotina|prensa|emendadora/.test(s)) return 'fa-compress';
    if (/laser|solda/.test(s)) return 'fa-bolt';
    if (/lixadeira/.test(s)) return 'fa-sliders';
    if (/exaust|coletor/.test(s)) return 'fa-wind';
    return 'fa-industry';
}

const SEED = [
    { nome: 'Centro Nesting CNC',                 modelo: 'CutPro',         tipo: 'Centro Nesting CNC' },
    { nome: 'Coladeira de Bordos Contour S-Line', modelo: 'Contour S-Line', tipo: 'Coladeira de Bordos' },
    { nome: 'Seccionadora Vertical',              modelo: 'SVP 145 Plus',   tipo: 'Seccionadora Vertical' },
    { nome: 'Centro de Furação MaxDrill',         modelo: 'MaxDrill M6.5',  tipo: 'Centro de Furação' },
    { nome: 'Esquadrejadeira MJ1132C',            modelo: 'MJ1132C',        tipo: 'Esquadrejadeira' }
];

/* ================= STATE ================= */
let machines = [];
let mode = 'server';
let filterEstado = null;
let filterTipo = null;
let filterModelo = null;
let editingId = null;
const drafts = {};

const userName    = document.getElementById('userName').textContent.trim();
const fNome       = document.getElementById('fNome');
const fModelo     = document.getElementById('fModelo');
const fSerie      = document.getElementById('fSerie');
const fTipo       = document.getElementById('fTipo');
const fTipoNovo   = document.getElementById('fTipoNovo');
const fTipoNovoWrap = document.getElementById('fTipoNovoWrap');
const fQuantidade = document.getElementById('fQuantidade');
const fEstado     = document.getElementById('fEstado');
const fInfoExtra  = document.getElementById('fInfoExtra');
const fObs        = document.getElementById('fObs');

function getQtd(m) {
    const q = parseInt(m?.quantidade ?? 1, 10);
    return Number.isFinite(q) && q >= 1 ? Math.min(9999, q) : 1;
}

/* ================= DATA LAYER ================= */
async function api(method, body) {
    const payload = body ? { ...body, context: PANEL_CONTEXT } : undefined;
    const res = await fetch(API_URL, {
        method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: payload ? JSON.stringify(payload) : undefined
    });
    if (res.status === 401) {
        window.location.href = 'login.php';
        throw new Error('Sessão expirada');
    }
    const data = await res.json();
    if (!res.ok || data.ok === false) throw new Error(data.error || 'Erro no servidor');
    return data;
}

function seedList() {
    const now = new Date().toISOString();
    return SEED.map((m, i) => ({
        id: 'm' + Date.now() + i, nome: m.nome, modelo: m.modelo, serie: '', tipo: m.tipo,
        quantidade: 1, estado: 'sem_info', obs: '', info_extra: '', atualizado_em: now, atualizado_por: ''
    }));
}

async function load() {
    try {
        const data = await api('GET');
        mode = 'server';
        machines = (data.machines || []).map(m => ({
            ...m,
            tipo: normalizeTipo(m.tipo),
            quantidade: getQtd(m),
            info_extra: m.info_extra || ''
        }));
    } catch (e) {
        if (e.message === 'Sessão expirada') return;
        mode = 'local';
        try { machines = JSON.parse(localStorage.getItem(LS_KEY)) || null; } catch (_) { machines = null; }
        if (!machines) { machines = seedList(); persistLocal(); }
        machines = machines.map(m => ({
            ...m,
            tipo: normalizeTipo(m.tipo),
            quantidade: getQtd(m),
            info_extra: m.info_extra || ''
        }));
    }
    render();
}

function persistLocal() {
    try { localStorage.setItem(LS_KEY, JSON.stringify(machines)); } catch (_) {}
}

async function saveMachine(m) {
    m.tipo = normalizeTipo(m.tipo);
    m.quantidade = getQtd(m);
    m.atualizado_em = new Date().toISOString();
    m.atualizado_por = userName;
    if (mode === 'server') {
        const data = await api('POST', { action: 'save', machine: m, user: userName });
        Object.assign(m, data.machine || {});
        m.quantidade = getQtd(m);
    }
    const i = machines.findIndex(x => x.id === m.id);
    if (i >= 0) machines[i] = m; else machines.push(m);
    if (mode === 'local') persistLocal();
}

async function removeMachine(id) {
    if (mode === 'server') await api('POST', { action: 'delete', id });
    machines = machines.filter(m => m.id !== id);
    if (mode === 'local') persistLocal();
}

/* ================= RENDER ================= */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const jsStr = s => JSON.stringify(String(s ?? '')).slice(1, -1).replace(/'/g, "\\'");

function fmtDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function getAllKnownTipos() {
    const set = new Set(TIPOS_SUGERIDOS);
    machines.forEach(m => { if (m.tipo) set.add(normalizeTipo(m.tipo)); });
    return Array.from(set);
}

function renderStrip() {
    const counts = {};
    let totalUnits = 0;
    machines.forEach(m => {
        const q = getQtd(m);
        totalUnits += q;
        counts[m.estado] = (counts[m.estado] || 0) + q;
    });
    const tiles = [`<button class="status-tile ${!filterEstado ? 'selected' : ''}" style="--c:var(--primary)" onclick="setFilter(null)">
        <div class="n">${totalUnits}</div><div class="l"><i class="fas fa-industry"></i> Todas</div></button>`];
    ['operacional','restricao','manutencao','aguardando','parada'].forEach(k => {
        const e = ESTADOS[k];
        tiles.push(`<button class="status-tile ${filterEstado === k ? 'selected' : ''}" style="--c:${e.color}" onclick="setFilter('${k}')">
            <div class="n">${counts[k] || 0}</div><div class="l"><i class="fas ${e.icon}"></i> ${e.label}</div></button>`);
    });
    document.getElementById('statusStrip').innerHTML = tiles.join('');
    const prob = machines
        .filter(m => ESTADOS_PROBLEMA.includes(m.estado))
        .reduce((acc, m) => acc + getQtd(m), 0);
    const badge = document.getElementById('navBadge');
    badge.textContent = prob;
    badge.className = 'nav-badge' + (prob ? '' : ' green');
}

function renderInventory(filteredUnits, totalUnits, hasAnyFilter) {
    // Agrupar inventário somando quantidades: Tipo -> { total, ok, prob, sem_info, models: { modeloName: sumQtd } }
    const typeStats = {};
    const distinctModelsSet = new Set();

    machines.forEach(m => {
        const t = normalizeTipo(m.tipo);
        const q = getQtd(m);
        if (!typeStats[t]) {
            typeStats[t] = { total: 0, ok: 0, prob: 0, sem_info: 0, models: {} };
        }
        typeStats[t].total += q;
        if (m.estado === 'operacional') typeStats[t].ok += q;
        else if (ESTADOS_PROBLEMA.includes(m.estado)) typeStats[t].prob += q;
        else typeStats[t].sem_info += q;

        const mod = (m.modelo || '').trim();
        const modKey = mod || 'Sem modelo';
        typeStats[t].models[modKey] = (typeStats[t].models[modKey] || 0) + q;
        if (mod) distinctModelsSet.add(t + '::' + mod.toLowerCase());
    });

    const activeTypes = Object.keys(typeStats).sort((a, b) => {
        const diff = typeStats[b].total - typeStats[a].total;
        return diff !== 0 ? diff : a.localeCompare(b, 'pt-BR');
    });

    // Garantir que se filterTipo estava apontando para um tipo que ficou sem máquinas, ainda apareça ou permita limpar
    if (filterTipo && !typeStats[filterTipo]) {
        typeStats[filterTipo] = { total: 0, ok: 0, prob: 0, sem_info: 0, models: {} };
        activeTypes.push(filterTipo);
    }

    const emptySuggested = TIPOS_SUGERIDOS.filter(t => !typeStats[t] || typeStats[t].total === 0);
    const totalOk = machines
        .filter(m => m.estado === 'operacional')
        .reduce((acc, m) => acc + getQtd(m), 0);
    const activeTypesCount = Object.values(typeStats).filter(s => s.total > 0).length;

    const cards = activeTypes.map(t => {
        const s = typeStats[t];
        const selected = filterTipo === t;
        const icon = iconForTipo(t);
        const dots = [];
        if (s.total === 0) {
            dots.push('<span>0 unidades</span>');
        } else {
            if (s.ok > 0) dots.push(`<span class="inv-dot" style="--dc:var(--success)">${s.ok} oper.</span>`);
            if (s.prob > 0) dots.push(`<span class="inv-dot" style="--dc:var(--secondary)">${s.prob} c/ alerta</span>`);
            if (s.sem_info > 0) dots.push(`<span class="inv-dot" style="--dc:var(--gray-400)">${s.sem_info} s/ info</span>`);
        }

        const modelEntries = Object.entries(s.models).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0], 'pt-BR'));
        const modelChips = modelEntries.map(([modName, qtd]) => {
            const isSemModelo = modName === 'Sem modelo';
            const chipFilterVal = isSemModelo ? '__SEM_MODELO__' : modName;
            const isChipOn = selected && filterModelo === chipFilterVal;
            return `<span class="inv-model-chip ${isChipOn ? 'on' : ''}" onclick="event.stopPropagation(); setFilterTipoModelo('${jsStr(t)}', '${jsStr(chipFilterVal)}')" title="Filtrar modelo ${esc(modName)} dentro de ${esc(t)}">${esc(modName)} <b>×${qtd}</b></span>`;
        }).join('');

        return `
        <div class="inv-card ${selected ? 'selected' : ''}" onclick="setFilterTipo('${jsStr(t)}')" role="button" tabindex="0">
            <div class="inv-card-top">
                <div class="inv-icon"><i class="fas ${icon}"></i></div>
                <div class="inv-info">
                    <div class="inv-name">${esc(t)}</div>
                    <div class="inv-sub">${dots.join('')}</div>
                </div>
                <div class="inv-count">${s.total}</div>
            </div>
            ${modelChips ? `<div class="inv-models">${modelChips}</div>` : ''}
        </div>`;
    }).join('');

    const emptyRow = emptySuggested.length ? `
        <div class="inv-empty-types">
            <span>Sem unidades no showroom:</span>
            ${emptySuggested.map(t => `<button type="button" class="inv-empty-chip" onclick="openModal(null, '${jsStr(t)}')" title="Cadastrar nova máquina em ${esc(t)}"><i class="fas fa-plus"></i> ${esc(t)}</button>`).join('')}
        </div>` : '';

    document.getElementById('inventoryBox').innerHTML = `
        <div class="inventory-head">
            <div class="inventory-title">
                <div class="inventory-title-icon"><i class="fas fa-boxes-stacked"></i></div>
                <h2>Inventário por Tipo e Modelo</h2>
                <div class="inv-kpis">
                    <span class="inv-kpi total" title="${machines.length} ${machines.length === 1 ? 'registro cadastrado' : 'registros cadastrados'}"><i class="fas fa-industry"></i> ${totalUnits} ${totalUnits === 1 ? 'máquina' : 'máquinas'}</span>
                    <span class="inv-kpi types"><i class="fas fa-layer-group"></i> ${activeTypesCount} ${activeTypesCount === 1 ? 'tipo' : 'tipos'}</span>
                    <span class="inv-kpi models"><i class="fas fa-tags"></i> ${distinctModelsSet.size} ${distinctModelsSet.size === 1 ? 'modelo' : 'modelos'}</span>
                    <span class="inv-kpi ok"><i class="fas fa-circle-check"></i> ${totalOk} ${totalOk === 1 ? 'operacional' : 'operacionais'}</span>
                    ${hasAnyFilter ? `<span class="inv-kpi showing"><i class="fas fa-filter"></i> Exibindo ${filteredUnits} de ${totalUnits}</span>` : ''}
                </div>
            </div>
            ${hasAnyFilter ? `<button class="inv-clear" onclick="clearAllFilters()"><i class="fas fa-filter-circle-xmark"></i> Limpar filtros</button>` : ''}
        </div>
        ${cards ? `<div class="inv-grid">${cards}</div>` : `<div style="font-size:13px;color:var(--gray-500);">Nenhuma máquina cadastrada no inventário ainda.</div>`}
        ${emptyRow}
    `;

    // Popular select de Tipos
    const allTipos = getAllKnownTipos().sort((a, b) => a.localeCompare(b, 'pt-BR'));
    const selTipo = document.getElementById('typeSelect');
    selTipo.innerHTML = `<option value="">Todos os tipos (${totalUnits})</option>` +
        allTipos.map(t => {
            const cnt = typeStats[t] ? typeStats[t].total : 0;
            return `<option value="${esc(t)}" ${filterTipo === t ? 'selected' : ''}>${esc(t)} (${cnt})</option>`;
        }).join('');

    // Popular select de Modelos (escopado pelo Tipo selecionado, se houver)
    const modelCounts = {};
    let semModeloCnt = 0;
    const baseForModels = filterTipo ? machines.filter(m => normalizeTipo(m.tipo) === filterTipo) : machines;
    const baseUnits = baseForModels.reduce((acc, m) => acc + getQtd(m), 0);
    baseForModels.forEach(m => {
        const q = getQtd(m);
        const mod = (m.modelo || '').trim();
        if (!mod) semModeloCnt += q;
        else modelCounts[mod] = (modelCounts[mod] || 0) + q;
    });
    const sortedModels = Object.keys(modelCounts).sort((a, b) => a.localeCompare(b, 'pt-BR'));
    const selModelo = document.getElementById('modelSelect');
    selModelo.innerHTML = `<option value="">Todos os modelos (${baseUnits})</option>` +
        sortedModels.map(mod =>
            `<option value="${esc(mod)}" ${filterModelo === mod ? 'selected' : ''}>${esc(mod)} (${modelCounts[mod]})</option>`
        ).join('') +
        (semModeloCnt > 0 ? `<option value="__SEM_MODELO__" ${filterModelo === '__SEM_MODELO__' ? 'selected' : ''}>Sem modelo especificado (${semModeloCnt})</option>` : '');
}

function render() {
    renderStrip();
    const pill = document.getElementById('modePill');
    pill.className = 'mode-pill ' + mode;
    pill.innerHTML = mode === 'server'
        ? '<i class="fas fa-cloud"></i> Sincronizado'
        : '<i class="fas fa-laptop"></i> Salvo só neste navegador';
    pill.title = mode === 'server'
        ? 'Os dados ficam no banco de dados do servidor e todos veem em tempo real'
        : 'Sem conexão com o servidor — salvo temporariamente neste navegador';

    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const hasAnyFilter = Boolean(filterEstado || filterTipo || filterModelo || q);

    const list = machines
        .filter(m => !filterEstado || m.estado === filterEstado)
        .filter(m => !filterTipo || normalizeTipo(m.tipo) === filterTipo)
        .filter(m => {
            if (!filterModelo) return true;
            const mod = (m.modelo || '').trim();
            if (filterModelo === '__SEM_MODELO__') return mod === '';
            return mod === filterModelo;
        })
        .filter(m => {
            if (!q) return true;
            return [m.nome, m.tipo, m.modelo, m.serie, m.info_extra, m.obs].join(' ').toLowerCase().includes(q);
        })
        .sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR'));

    const totalUnits = machines.reduce((acc, m) => acc + getQtd(m), 0);
    const filteredUnits = list.reduce((acc, m) => acc + getQtd(m), 0);
    renderInventory(filteredUnits, totalUnits, hasAnyFilter);

    const grid = document.getElementById('grid');
    if (!list.length) {
        grid.innerHTML = `<div class="empty-state"><i class="fas fa-industry"></i>
            <h3>${machines.length ? 'Nenhuma máquina encontrada para este filtro' : 'Nenhuma máquina cadastrada'}</h3>
            <p>${machines.length ? 'Ajuste a busca, o tipo, o modelo ou o estado selecionado.' : 'Clique em "Nova Máquina" para começar.'}</p></div>`;
        return;
    }

    grid.innerHTML = list.map(m => {
        const d = drafts[m.id] || {};
        const estado = d.estado ?? m.estado;
        const obs = d.obs ?? m.obs;
        const quantidade = d.quantidade ?? getQtd(m);
        const e = ESTADOS[estado] || ESTADOS.sem_info;
        const tipoLabel = normalizeTipo(m.tipo);
        const icon = iconForTipo(tipoLabel);
        const dirty = d.estado !== undefined || d.obs !== undefined || d.quantidade !== undefined;
        const meta = [tipoLabel, m.modelo ? 'Mod. ' + m.modelo : '', m.serie ? 'S/N ' + m.serie : ''].filter(Boolean).map(x => `<span>${esc(x)}</span>`).join('');
        return `
        <div class="machine ${dirty ? 'dirty' : ''}" style="--c:${e.color}" data-id="${esc(m.id)}">
            <div class="machine-head">
                <div class="machine-icon"><i class="fas ${icon}"></i></div>
                <div class="machine-title">
                    <h3>${esc(m.nome)}</h3>
                    <div class="meta">${meta}</div>
                    <div class="badges-row">
                        <div class="state-badge">${e.label}</div>
                        <div class="qtd-stepper ${quantidade > 1 ? 'multi' : ''}" title="Quantidade de máquinas deste registro">
                            <button type="button" onclick="stepDraftQtd('${jsStr(m.id)}', -1)" title="Diminuir quantidade">−</button>
                            <span>${quantidade} un.</span>
                            <button type="button" onclick="stepDraftQtd('${jsStr(m.id)}', 1)" title="Aumentar quantidade">+</button>
                        </div>
                    </div>
                </div>
                <button class="icon-btn" title="Editar dados da máquina" onclick="openModal('${esc(m.id)}')"><i class="fas fa-pen"></i></button>
            </div>
            <div class="machine-body">
                ${m.info_extra ? `<div class="machine-extra"><i class="fas fa-circle-info"></i><span>${esc(m.info_extra)}</span></div>` : ''}
                <div class="state-options">
                    ${Object.entries(ESTADOS).map(([k, s]) => `
                        <button class="state-opt ${k === estado ? 'on' : ''}" style="--sc:${s.color}" onclick="setDraft('${esc(m.id)}','estado','${k}')">
                            <i class="fas ${s.icon}"></i>${s.label}</button>`).join('')}
                </div>
                <textarea placeholder="Descreva o estado da máquina: o que funciona, o que falta, peças pendentes..." oninput="setDraft('${esc(m.id)}','obs',this.value,true)">${esc(obs)}</textarea>
            </div>
            <div class="machine-foot">
                <div class="updated">Atualizado em <strong>${fmtDate(m.atualizado_em)}</strong>${m.atualizado_por ? '<br>por <strong>' + esc(m.atualizado_por) + '</strong>' : ''}</div>
                <button class="btn btn-blue save-btn" ${dirty ? '' : 'disabled'} onclick="commitDraft('${esc(m.id)}')"><i class="fas fa-floppy-disk"></i> Salvar</button>
            </div>
        </div>`;
    }).join('');
}

/* ================= ACTIONS ================= */
function setFilter(k) { filterEstado = filterEstado === k ? null : k; render(); }

function setFilterTipo(k, fromSelect) {
    const next = fromSelect ? (k || null) : (filterTipo === k ? null : k);
    if (next !== filterTipo) {
        filterTipo = next;
        filterModelo = null; // reseta sub-filtro de modelo ao trocar de família
    }
    render();
}

function setFilterModelo(mod, fromSelect) {
    filterModelo = fromSelect ? (mod || null) : (filterModelo === mod ? null : mod);
    render();
}

function setFilterTipoModelo(tipo, mod) {
    if (filterTipo === tipo && filterModelo === mod) {
        filterModelo = null;
    } else {
        filterTipo = tipo;
        filterModelo = mod;
    }
    render();
}

function clearAllFilters() {
    filterEstado = null;
    filterTipo = null;
    filterModelo = null;
    document.getElementById('searchInput').value = '';
    render();
}

function setDraft(id, field, value, fromTyping) {
    const m = machines.find(x => x.id === id);
    if (!m) return;
    drafts[id] = drafts[id] || {};
    const orig = field === 'quantidade' ? getQtd(m) : m[field];
    if (value === orig) delete drafts[id][field]; else drafts[id][field] = value;
    if (!Object.keys(drafts[id]).length) delete drafts[id];
    if (fromTyping) {
        const card = document.querySelector(`.machine[data-id="${CSS.escape(id)}"]`);
        if (card) {
            const dirty = !!drafts[id];
            card.classList.toggle('dirty', dirty);
            card.querySelector('.save-btn').disabled = !dirty;
        }
    } else render();
}

function stepDraftQtd(id, delta) {
    const m = machines.find(x => x.id === id);
    if (!m) return;
    const cur = drafts[id]?.quantidade ?? getQtd(m);
    const next = Math.max(1, Math.min(9999, cur + delta));
    setDraft(id, 'quantidade', next);
}

function stepModalQtd(delta) {
    const cur = parseInt(fQuantidade.value, 10) || 1;
    fQuantidade.value = Math.max(1, Math.min(9999, cur + delta));
}

async function commitDraft(id) {
    const m = { ...machines.find(x => x.id === id), ...drafts[id] };
    try {
        await saveMachine(m);
        delete drafts[id];
        render();
        toast('Estado salvo');
    } catch (e) { toast(e.message, true); }
}

function getSelectedModalTipo() {
    return fTipo.value === '__NOVO__' ? fTipoNovo.value.trim() : fTipo.value.trim();
}

function updateModelosDatalist() {
    const tipoAtual = getSelectedModalTipo();
    const modelosSet = new Set();
    machines.forEach(m => {
        const mod = (m.modelo || '').trim();
        if (!mod) return;
        if (!tipoAtual || normalizeTipo(m.tipo) === tipoAtual) {
            modelosSet.add(mod);
        }
    });
    document.getElementById('modelosDatalist').innerHTML = Array.from(modelosSet)
        .sort((a, b) => a.localeCompare(b, 'pt-BR'))
        .map(mod => `<option value="${esc(mod)}"></option>`)
        .join('');
}

function updateNomePlaceholder() {
    const t = getSelectedModalTipo();
    const mod = fModelo.value.trim();
    const autoName = [t, mod].filter(Boolean).join(' ');
    fNome.placeholder = autoName ? `Automático: ${autoName}` : 'Ex.: Centro de Furação MaxDrill M6.5';
}

function onTipoModalChange() {
    const isNovo = fTipo.value === '__NOVO__';
    fTipoNovoWrap.style.display = isNovo ? '' : 'none';
    if (isNovo) setTimeout(() => fTipoNovo.focus(), 30);
    updateModelosDatalist();
    updateNomePlaceholder();
}

function openModal(id, presetTipo) {
    editingId = id || null;
    const defaultTipo = presetTipo || filterTipo || TIPOS_SUGERIDOS[0];
    const m = id
        ? machines.find(x => x.id === id)
        : { nome: '', modelo: '', serie: '', tipo: defaultTipo, quantidade: 1, estado: 'sem_info', info_extra: '', obs: '' };

    document.getElementById('modalTitle').textContent = id ? 'Editar Máquina' : 'Nova Máquina';
    document.getElementById('btnDelete').style.display = id ? '' : 'none';

    const tiposDisponiveis = getAllKnownTipos();
    const tipoMaquina = normalizeTipo(m.tipo || defaultTipo);
    if (tipoMaquina && !tiposDisponiveis.includes(tipoMaquina)) {
        tiposDisponiveis.push(tipoMaquina);
    }
    tiposDisponiveis.sort((a, b) => a.localeCompare(b, 'pt-BR'));

    fTipo.innerHTML = tiposDisponiveis.map(t => `<option value="${esc(t)}" ${t === tipoMaquina ? 'selected' : ''}>${esc(t)}</option>`).join('') +
        `<option value="__NOVO__">+ Criar novo tipo / categoria...</option>`;
    fTipoNovo.value = '';
    fTipoNovoWrap.style.display = 'none';

    fEstado.innerHTML = Object.entries(ESTADOS).map(([k, e]) => `<option value="${k}" ${k === m.estado ? 'selected' : ''}>${e.label}</option>`).join('');
    fQuantidade.value = getQtd(m);
    fNome.value = m.nome || '';
    fModelo.value = m.modelo || '';
    fSerie.value = m.serie || '';
    fInfoExtra.value = m.info_extra || '';
    fObs.value = m.obs || '';

    updateModelosDatalist();
    updateNomePlaceholder();

    document.getElementById('modalBg').classList.add('open');
    setTimeout(() => (id ? fNome : fModelo).focus(), 50);
}
function closeModal() { document.getElementById('modalBg').classList.remove('open'); }

async function saveModal() {
    const tipoFinal = getSelectedModalTipo();
    if (!tipoFinal) {
        (fTipo.value === '__NOVO__' ? fTipoNovo : fTipo).focus();
        toast('Informe o tipo / família da máquina', true);
        return;
    }
    const modeloFinal = fModelo.value.trim();
    const nomeFinal = fNome.value.trim() || [tipoFinal, modeloFinal].filter(Boolean).join(' ');
    if (!nomeFinal) {
        fNome.focus();
        toast('Informe o nome ou modelo da máquina', true);
        return;
    }
    const qtdFinal = Math.max(1, Math.min(9999, parseInt(fQuantidade.value, 10) || 1));

    const base = editingId ? machines.find(x => x.id === editingId) : { id: 'm' + Date.now() };
    const m = {
        ...base,
        nome: nomeFinal,
        modelo: modeloFinal,
        serie: fSerie.value.trim(),
        tipo: tipoFinal,
        quantidade: qtdFinal,
        estado: fEstado.value,
        info_extra: fInfoExtra.value.trim(),
        obs: fObs.value.trim()
    };
    try {
        await saveMachine(m);
        delete drafts[m.id];
        closeModal(); render();
        toast(editingId ? 'Máquina atualizada' : 'Máquina adicionada');
    } catch (e) { toast(e.message, true); }
}

async function deleteMachine() {
    const m = machines.find(x => x.id === editingId);
    if (!m || !confirm(`Excluir "${m.nome}" do showroom?`)) return;
    try {
        await removeMachine(editingId);
        delete drafts[editingId];
        closeModal();
        render();
        toast('Máquina excluída');
    } catch (e) { toast(e.message, true); }
}

let toastTimer;
function toast(msg, err) {
    const t = document.getElementById('toast');
    t.className = 'toast show' + (err ? ' err' : '');
    t.innerHTML = `<i class="fas ${err ? 'fa-circle-exclamation' : 'fa-check'}"></i> ${esc(msg)}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
}

function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('sidebarBackdrop').classList.toggle('active');
    document.querySelector('#mobileToggle i').className = document.querySelector('.sidebar').classList.contains('open') ? 'fas fa-times' : 'fas fa-bars';
}
document.querySelectorAll('.sidebar .nav-item').forEach(i => i.addEventListener('click', () => { if (innerWidth <= 1024) toggleSidebar(); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
window.addEventListener('beforeunload', e => { if (Object.keys(drafts).length) { e.preventDefault(); e.returnValue = ''; } });

load();
</script>
</body>
</html>

