<?php
require_once '../api/auth-tecnico.php';
require_once '../api/config.php';

verificarTecnico();

$db = getDB();
$tecnico_id = $_SESSION['tecnico_id'];
$tecnico_nome = $_SESSION['tecnico_nome'] ?? 'Técnico';
$tecnico_email = $_SESSION['tecnico_email'] ?? '';

$stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ?");
$stmt->execute([$tecnico_id]);
$total_rats = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ? AND status = 'rascunho'");
$stmt->execute([$tecnico_id]);
$rascunho = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total FROM rats WHERE id_tecnico = ? AND status = 'enviado'");
$stmt->execute([$tecnico_id]);
$enviado = $stmt->fetch()['total'];

// Filtro por status
$status_filtro = $_GET['status'] ?? null;
$status_validos = ['rascunho', 'enviado'];
if ($status_filtro && !in_array($status_filtro, $status_validos)) {
    $status_filtro = null;
}

if ($status_filtro) {
    $stmt = $db->prepare("SELECT * FROM rats WHERE id_tecnico = ? AND status = ? ORDER BY data_criacao DESC LIMIT 20");
    $stmt->execute([$tecnico_id, $status_filtro]);
} else {
    $stmt = $db->prepare("SELECT * FROM rats WHERE id_tecnico = ? ORDER BY data_criacao DESC LIMIT 10");
    $stmt->execute([$tecnico_id]);
}
$rats_recentes = $stmt->fetchAll();

$iniciais = strtoupper(substr($tecnico_nome, 0, 1));

$titulos_filtro = [
    'rascunho' => 'Rascunhos',
    'enviado' => 'Enviados',
];
$titulo_tabela = $status_filtro ? $titulos_filtro[$status_filtro] : 'Relatórios Recentes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Técnico | Sistema RAT - Madetech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #034c8c;
            --primary-light: #0466c8;
            --primary-dark: #022d54;
            --secondary: #f58220;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --teal: #14b8a6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --sidebar-width: 260px;
            --header-height: 70px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            color: var(--gray-900);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            z-index: 200;
            display: flex;
            flex-direction: column;
            animation: sidebarSlide 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            overflow: hidden;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        @keyframes sidebarSlide {
            from { opacity: 0; transform: translateX(-100%); }
            to { opacity: 1; transform: translateX(0); }
        }

        .sidebar-brand {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand .logo-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .sidebar-brand img {
            height: 36px;
            filter: brightness(0) invert(1);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .sidebar-brand img:hover {
            transform: scale(1.1) rotate(-3deg);
        }

        .sidebar-brand h2 {
            color: white;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .sidebar-brand .version-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.9);
            font-size: 10px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Nav */
        .sidebar-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-section { margin-bottom: 24px; }

        .nav-section-title {
            font-size: 10px;
            font-weight: 700;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 0 12px 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            margin-bottom: 4px;
        }

        .nav-item i { width: 20px; text-align: center; font-size: 16px; transition: transform 0.3s; }

        .nav-item:hover {
            background: rgba(255,255,255,0.12);
            color: white;
            transform: translateX(4px);
        }

        .nav-item:hover i { transform: scale(1.2); }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            width: 4px; height: 24px;
            background: white;
            border-radius: 0 4px 4px 0;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--secondary);
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            min-width: 24px;
            text-align: center;
        }

        .nav-badge.green { background: var(--success); }

        /* CTA Button */
        .sidebar-cta {
            margin: 0 12px 16px;
            padding: 14px;
            background: linear-gradient(135deg, var(--secondary), #ff9940);
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 4px 16px rgba(245, 130, 32, 0.4);
            animation: ctaPulse 3s ease-in-out infinite;
        }

        .sidebar-cta:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 24px rgba(245, 130, 32, 0.5);
        }

        @keyframes ctaPulse {
            0%, 100% { box-shadow: 0 4px 16px rgba(245, 130, 32, 0.4); }
            50% { box-shadow: 0 4px 24px rgba(245, 130, 32, 0.6); }
        }

        .sidebar-cta i { font-size: 18px; }

        /* Sidebar footer */
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-user-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal) 0%, #0d9488 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .sidebar-user-info { flex: 1; }

        .sidebar-user-info strong {
            display: block;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }

        .sidebar-user-info small {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
        }

        .sidebar-logout {
            color: rgba(255,255,255,0.5);
            font-size: 16px;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
        }

        .sidebar-logout:hover {
            color: var(--danger);
            transform: scale(1.2);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }

        /* Top Bar */
        .topbar {
            position: sticky;
            top: 0;
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            animation: fadeInDown 0.6s ease-out 0.3s both;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .topbar-left h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .topbar-breadcrumb {
            font-size: 13px;
            color: var(--gray-400);
        }

        .topbar-breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-btn {
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .topbar-btn:hover {
            background: var(--gray-50);
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.1);
        }

        /* Content */
        .content { padding: 32px; }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 60%, #1a8cff 100%);
            border-radius: 20px;
            padding: 32px 40px;
            color: white;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            animation: bannerSlide 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.4s both;
        }

        @keyframes bannerSlide {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            right: -40px; top: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -60px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .welcome-text h2 {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .welcome-text p {
            font-size: 15px;
            opacity: 0.85;
            line-height: 1.5;
        }

        .welcome-date {
            background: rgba(255,255,255,0.15);
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(10px);
        }

        .welcome-art {
            font-size: 64px;
            position: relative;
            z-index: 1;
            animation: welcomeFloat 3s ease-in-out infinite;
        }

        @keyframes welcomeFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .stat-card:hover::before { transform: scaleX(1); }

        .stat-card:nth-child(1) { animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.5s both; }
        .stat-card:nth-child(2) { animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.6s both; }
        .stat-card:nth-child(3) { animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.7s both; }

        .stat-card:nth-child(1)::before { background: var(--primary); }
        .stat-card:nth-child(2)::before { background: var(--secondary); }
        .stat-card:nth-child(3)::before { background: var(--success); }

        @keyframes cardPop {
            from { opacity: 0; transform: translateY(30px) scale(0.9); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, rgba(3,76,140,0.1), rgba(4,102,200,0.15)); color: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, rgba(245,130,32,0.1), rgba(255,153,64,0.15)); color: var(--secondary); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.15)); color: var(--success); }

        .stat-trend {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-trend.up { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-trend.neutral { background: rgba(107,114,128,0.1); color: var(--gray-500); }

        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 4px;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .action-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 24px;
            text-decoration: none;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .action-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .action-card:nth-child(1)::after { background: var(--primary); }
        .action-card:nth-child(2)::after { background: var(--success); }
        .action-card:nth-child(3)::after { background: var(--purple); }

        .action-card:hover::after { transform: scaleX(1); }

        .action-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
        }

        .action-card:nth-child(1) { animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.8s both; }
        .action-card:nth-child(2) { animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.9s both; }
        .action-card:nth-child(3) { animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 1.0s both; }

        .action-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 14px;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .action-card:hover .action-icon { transform: scale(1.15) rotate(-5deg); }

        .action-card:nth-child(1) .action-icon { background: linear-gradient(135deg, var(--secondary), #ff9940); color: white; }
        .action-card:nth-child(2) .action-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .action-card:nth-child(3) .action-icon { background: linear-gradient(135deg, var(--teal), #0d9488); color: white; }

        .action-card h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
        }

        .action-card p {
            font-size: 12px;
            color: var(--gray-400);
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            animation: cardPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 1.1s both;
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 28px 20px;
            border-bottom: 1px solid var(--gray-100);
        }

        .table-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .table-header-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .table-header-right a {
            padding: 8px 16px;
            background: var(--gray-50);
            color: var(--primary);
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
        }

        .table-header-right a:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.2);
        }

        .table-container { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: var(--gray-50);
            padding: 12px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-500);
            border-bottom: 1px solid var(--gray-200);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        tbody tr { transition: all 0.2s; }
        tbody tr:hover { background: rgba(3, 76, 140, 0.02); }

        .rat-number {
            font-weight: 700;
            color: var(--primary);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-rascunho { background: #fef3c7; color: #b45309; }

        .badge-rascunho::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #f59e0b;
        }

        .badge-enviado { background: #d1fae5; color: #065f46; }

        .badge-enviado::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #10b981;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 7px 14px;
            background: white;
            color: var(--primary);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.15);
        }

        .action-btn.edit {
            color: var(--secondary);
            border-color: rgba(245, 130, 32, 0.3);
        }

        .action-btn.edit:hover {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
            box-shadow: 0 4px 12px rgba(245, 130, 32, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-400);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .empty-state a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 10px 24px;
            background: linear-gradient(135deg, var(--secondary), #ff9940);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .empty-state a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 130, 32, 0.3);
        }

        /* Mobile */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px; left: 14px;
            z-index: 300;
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(3, 76, 140, 0.3);
            -webkit-tap-highlight-color: transparent;
        }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 199;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .sidebar-backdrop.active { display: block; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; animation: none !important; }
            .sidebar.open { transform: translateX(0); }
            .mobile-toggle { display: flex; align-items: center; justify-content: center; }
            .main-content { margin-left: 0; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
            .topbar { padding: 0 20px 0 68px; }
            .content { padding: 24px 20px; }
        }

        @media (max-width: 768px) {
            .content { padding: 16px 12px; }
            .topbar { height: 60px; padding: 0 12px 0 68px; }
            .mobile-toggle { top: 10px; }
            .topbar-left h1 { font-size: 15px; }
            .stats-grid { grid-template-columns: 1fr; gap: 12px; }
            .quick-actions { gap: 12px; }
            .stat-number { font-size: 28px; }
            .welcome-banner {
                flex-direction: column;
                text-align: center;
                padding: 20px 16px;
                border-radius: 14px;
            }
            .welcome-art { margin-top: 12px; }
            .table-section { border-radius: 12px; }
            .table-section table { font-size: 13px; }
            .action-btn { padding: 6px 10px; font-size: 11px; }
        }

        @media (max-width: 380px) {
            .content { padding: 12px 8px; }
            .topbar { padding: 0 8px 0 54px; }
            .mobile-toggle { width: 34px; height: 34px; top: 13px; left: 8px; font-size: 15px; border-radius: 8px; }
        }

        @media print {
            .sidebar, .topbar, .mobile-toggle, .quick-actions, .welcome-banner, .sidebar-backdrop { display: none !important; }
            .main-content { margin-left: 0; }
            .table-section { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <!-- MOBILE -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
    <button class="mobile-toggle" id="mobileToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-row">
                <img src="https://www.madetech.com.br/loja/wp-content/uploads/2025/05/Logo-Madetech-Final.png" alt="Madetech">
                <h2>Sistema RAT</h2>
            </div>
            <span class="version-badge">Técnico v1.0</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item <?= !$status_filtro ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>
                    Meu Painel
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Meus RATs</div>
                <a href="index.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    Todos
                    <span class="nav-badge"><?= $total_rats ?></span>
                </a>
                <a href="index.php?status=rascunho" class="nav-item <?= $status_filtro === 'rascunho' ? 'active' : '' ?>">
                    <i class="fas fa-pencil-alt"></i>
                    Rascunhos
                    <span class="nav-badge"><?= $rascunho ?></span>
                </a>
                <a href="index.php?status=enviado" class="nav-item <?= $status_filtro === 'enviado' ? 'active' : '' ?>">
                    <i class="fas fa-paper-plane"></i>
                    Enviados
                    <span class="nav-badge green"><?= $enviado ?></span>
                </a>
            </div>
        </nav>

        <!-- CTA: New RAT -->
        <a href="criar-rat.php" class="sidebar-cta">
            <i class="fas fa-plus-circle"></i>
            Criar Novo RAT
        </a>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar"><?= $iniciais ?></div>
                <div class="sidebar-user-info">
                    <strong><?= htmlspecialchars($tecnico_nome) ?></strong>
                    <small><?= htmlspecialchars($tecnico_email) ?></small>
                </div>
                <a href="../api/logout.php" class="sidebar-logout" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- TOP BAR -->
        <div class="topbar">
            <div class="topbar-left">
                <div>
                    <h1>Meu Painel</h1>
                    <div class="topbar-breadcrumb">
                        <a href="index.php">Técnico</a> / Dashboard
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <a href="criar-rat.php" class="topbar-btn" title="Criar RAT">
                    <i class="fas fa-plus"></i>
                </a>
                <a href="index.php" class="topbar-btn" title="Atualizar">
                    <i class="fas fa-sync-alt"></i>
                </a>
                <a href="../api/logout.php" class="topbar-btn" title="Sair">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <!-- WELCOME BANNER -->
            <div class="welcome-banner">
                <div class="welcome-text">
                    <h2>Olá, <?= htmlspecialchars($tecnico_nome) ?>! 👋</h2>
                    <p>Gerencie seus relatórios de atendimento técnico e mantenha tudo em dia.</p>
                    <div class="welcome-date">
                        <i class="fas fa-calendar-alt"></i>
                        <?= date('d/m/Y') ?>
                    </div>
                </div>
                <div class="welcome-art">👷</div>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                        <span class="stat-trend neutral"><i class="fas fa-minus"></i> Total</span>
                    </div>
                    <div class="stat-number"><?= $total_rats ?></div>
                    <div class="stat-label">Meus RATs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon"><i class="fas fa-pencil-alt"></i></div>
                        <span class="stat-trend neutral"><i class="fas fa-clock"></i> Pendente</span>
                    </div>
                    <div class="stat-number"><?= $rascunho ?></div>
                    <div class="stat-label">Em Rascunho</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <span class="stat-trend up"><i class="fas fa-arrow-up"></i> Completo</span>
                    </div>
                    <div class="stat-number"><?= $enviado ?></div>
                    <div class="stat-label">Enviados</div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="quick-actions">
                <a href="criar-rat.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                    <h3>Criar Novo RAT</h3>
                    <p>Iniciar novo relatório</p>
                </a>
                <a href="index.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-clipboard-check"></i></div>
                    <h3>Meus Relatórios</h3>
                    <p>Ver e editar RATs</p>
                </a>
                <a href="index.php?status=enviado" class="action-card">
                    <div class="action-icon"><i class="fas fa-paper-plane"></i></div>
                    <h3>RATs Enviados</h3>
                    <p>Relatórios finalizados</p>
                </a>
            </div>

            <!-- TABLE -->
            <div class="table-section">
                <div class="table-header">
                    <div class="table-header-left">
                        <div class="table-header-icon"><i class="fas fa-clock"></i></div>
                        <h2><?= $titulo_tabela ?></h2>
                    </div>
                    <div class="table-header-right">
                        <a href="criar-rat.php"><i class="fas fa-plus"></i> Novo RAT</a>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (count($rats_recentes) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Empresa</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rats_recentes as $rat): ?>
                            <tr>
                                <td><span class="rat-number"><?= htmlspecialchars($rat['numero']) ?></span></td>
                                <td><strong><?= htmlspecialchars($rat['cliente_empresa'] ?? '-') ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $rat['status'] ?>">
                                        <?= ucfirst($rat['status']) ?>
                                    </span>
                                </td>
                                <td style="color: var(--gray-500); font-size: 13px;">
                                    <?= date('d/m/Y H:i', strtotime($rat['data_criacao'])) ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="editar-rat.php?id=<?= $rat['id'] ?>" class="action-btn edit">
                                            <i class="fas fa-pen"></i> Editar
                                        </a>
                                        <?php
                                        $notas_count = count(json_decode($rat['notas_fiscais_json'] ?? '[]', true) ?: []);
                                        ?>
                                        <a href="editar-rat.php?id=<?= $rat['id'] ?>#notas-fiscais" class="action-btn"
                                           title="<?= $notas_count ?> nota(s) fiscal(is)"
                                           style="<?= $notas_count > 0 ? 'color:#f59e0b;border-color:rgba(245,158,11,.35);' : 'color:#94a3b8;' ?>">
                                            <i class="fas fa-receipt"></i> <?= $notas_count ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Nenhum RAT Criado</h3>
                        <p>Comece criando seu primeiro relatório de atendimento técnico.</p>
                        <a href="criar-rat.php"><i class="fas fa-plus-circle"></i> Criar Primeiro RAT</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('open');
        document.getElementById('sidebarBackdrop').classList.toggle('active');
        const icon = document.querySelector('#mobileToggle i');
        icon.className = document.querySelector('.sidebar').classList.contains('open') ? 'fas fa-times' : 'fas fa-bars';
    }
    document.querySelectorAll('.sidebar .nav-item').forEach(item => {
        item.addEventListener('click', () => { if (window.innerWidth <= 1024) toggleSidebar(); });
    });
    </script>
</body>
</html>