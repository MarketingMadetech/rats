<?php
require_once '../api/auth-tecnico.php';
require_once '../api/config.php';

verificarTecnico();

$db = getDB();
$tecnico_id = $_SESSION['tecnico_id'];
$tecnico_nome = $_SESSION['tecnico_nome'] ?? 'Técnico';
$tecnico_email = $_SESSION['tecnico_email'] ?? '';
$iniciais = strtoupper(substr($tecnico_nome, 0, 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa = $_POST['cliente_empresa'] ?? '';
    $responsavel = $_POST['cliente_responsavel'] ?? '';
    $email = $_POST['cliente_email'] ?? '';
    $endereco = $_POST['cliente_endereco'] ?? '';
    $cidade = $_POST['cliente_cidade'] ?? '';
    $estado = $_POST['cliente_estado'] ?? '';

    if ($empresa && $responsavel && $email) {
        // Número temporário para rascunho. O número oficial será gerado apenas no envio.
        $numero = "RASCUNHO-" . time() . "-" . rand(1000, 9999);
        $proximo_seq = null;
        
        $stmt = $db->prepare("
            INSERT INTO rats (id_tecnico, numero, numero_sequencial, cliente_empresa, cliente_responsavel, email_cliente, 
                            endereco, cidade, estado, status, pedagio_qtd, hospedagem_dias, alimentacao_qtd, outros_qtd)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho', 0, 0, 0, 0)
        ");
        
        if ($stmt->execute([$tecnico_id, $numero, $proximo_seq, $empresa, $responsavel, $email, $endereco, $cidade, $estado])) {
            $rat_id = $db->lastInsertId();
            header("Location: editar-rat.php?id=" . $rat_id);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Criar RAT | Sistema RAT - Madetech</title>
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            color: var(--gray-900);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
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
            overflow: hidden;
        }

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
        .sidebar-brand h2 { color: white; font-size: 18px; font-weight: 700; }
        .version-badge { display: inline-block; background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9); font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 20px; }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-section { margin-bottom: 24px; }
        .nav-section-title { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1.5px; padding: 0 12px 8px; }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px; color: rgba(255,255,255,0.7);
            text-decoration: none; border-radius: 10px;
            font-size: 14px; font-weight: 500;
            transition: all 0.3s; position: relative; margin-bottom: 4px;
        }
        .nav-item i { width: 20px; text-align: center; font-size: 16px; }
        .nav-item:hover { background: rgba(255,255,255,0.12); color: white; transform: translateX(4px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; font-weight: 600; }
        .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 24px; background: white; border-radius: 0 4px 4px 0; }

        .sidebar-footer { padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.1); }
        .sidebar-user { display: flex; align-items: center; gap: 12px; }
        .sidebar-user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--teal), #0d9488); display: flex; align-items: center; justify-content: center; font-size: 16px; color: white; font-weight: 700; }
        .sidebar-user-info { flex: 1; }
        .sidebar-user-info strong { display: block; color: white; font-size: 13px; }
        .sidebar-user-info small { color: rgba(255,255,255,0.5); font-size: 11px; }
        .sidebar-logout { color: rgba(255,255,255,0.5); font-size: 16px; text-decoration: none; transition: all 0.3s; }
        .sidebar-logout:hover { color: #ef4444; }

        /* ========== MAIN ========== */
        .main-content { margin-left: var(--sidebar-width); min-height: 100vh; }

        .topbar {
            position: sticky; top: 0; height: 70px;
            background: white; border-bottom: 1px solid var(--gray-200);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .topbar-left h1 { font-size: 18px; font-weight: 700; }
        .topbar-breadcrumb { font-size: 13px; color: var(--gray-400); }
        .topbar-breadcrumb a { color: var(--primary); text-decoration: none; }

        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .topbar-btn {
            height: 36px; border-radius: 8px; border: 1px solid var(--gray-200);
            background: white; display: flex; align-items: center; justify-content: center;
            color: var(--gray-500); cursor: pointer; transition: all 0.3s;
            text-decoration: none; padding: 0 14px; font-size: 13px; font-weight: 600; gap: 6px;
        }
        .topbar-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateY(-2px); }

        /* ========== CONTENT ========== */
        .content { padding: 32px; max-width: 720px; }

        @keyframes cardPop {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            animation: cardPop 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.1s both;
            transition: all 0.3s;
        }
        .form-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-color: rgba(3,76,140,0.15); }

        .section-header {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 24px; padding-bottom: 16px;
            border-bottom: 1px solid var(--gray-100);
        }
        .section-icon {
            width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 12px; color: white; font-size: 18px; flex-shrink: 0;
        }
        .section-title { font-size: 17px; font-weight: 700; color: var(--gray-900); }
        .section-description { font-size: 13px; color: var(--gray-400); margin-top: 2px; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-grid:last-child { margin-bottom: 0; }

        .form-group { display: flex; flex-direction: column; }
        .form-group label {
            font-size: 12px; font-weight: 600; color: var(--gray-600);
            margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .form-group input,
        .form-group select {
            padding: 10px 14px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.3s;
            background: var(--gray-50);
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: var(--primary);
            background: white; box-shadow: 0 0 0 3px rgba(3,76,140,0.08);
        }

        .button-group {
            display: flex; gap: 12px; justify-content: center;
            margin-top: 28px; padding-top: 20px;
            border-top: 1px solid var(--gray-100);
        }
        .btn {
            padding: 12px 28px; font-size: 14px; font-weight: 600;
            border: none; border-radius: 10px; cursor: pointer;
            transition: all 0.3s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white; box-shadow: 0 4px 12px rgba(3,76,140,0.25);
        }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(3,76,140,0.35); }
        .btn-secondary {
            background: white; color: var(--primary); border: 2px solid var(--gray-200);
        }
        .btn-secondary:hover { background: var(--gray-50); border-color: var(--primary); transform: translateY(-2px); }

        /* Voice Dictation Styles */
        .label-with-voice {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            gap: 8px;
        }

        .label-with-voice label {
            margin-bottom: 0 !important;
        }

        .btn-voice-dictation {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            color: var(--primary);
            background: var(--gray-50);
            border: 1px solid var(--gray-300);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            user-select: none;
            line-height: 1.2;
        }

        .btn-voice-dictation:hover {
            background: var(--gray-200);
            color: var(--primary-light);
            transform: translateY(-1px);
        }

        .btn-voice-dictation i {
            font-size: 11px;
            transition: transform 0.2s ease;
        }

        .btn-voice-dictation.is-listening {
            background: linear-gradient(135deg, #ef4444, #f97316);
            border-color: #dc2626;
            color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25);
            animation: voice-pulse 1.5s infinite;
        }

        .btn-voice-dictation.is-listening i {
            animation: mic-bounce 0.8s infinite alternate ease-in-out;
        }

        @keyframes voice-pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
            70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        @keyframes mic-bounce {
            0% { transform: scale(1); }
            100% { transform: scale(1.25); }
        }

        .field-listening {
            border-color: #f97316 !important;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2) !important;
            background-color: #fffaf5 !important;
        }

        .voice-live-preview {
            display: none;
            font-size: 11px;
            color: #c2410c;
            background: #fff7ed;
            border: 1px dashed #fdba74;
            border-radius: 6px;
            padding: 4px 8px;
            margin-top: 4px;
            font-style: italic;
        }

        .voice-live-preview.active {
            display: block;
        }

        /* ========== MOBILE ========== */
        .mobile-toggle {
            display: none; position: fixed; top: 15px; left: 14px; z-index: 300;
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--primary); color: white; border: none;
            font-size: 18px; cursor: pointer;
            box-shadow: 0 4px 12px rgba(3,76,140,0.3);
            -webkit-tap-highlight-color: transparent;
        }
        .sidebar-backdrop {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 199;
            backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px);
        }
        .sidebar-backdrop.active { display: block; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s ease; animation: none !important; }
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
            .form-card { padding: 20px 16px; border-radius: 12px; }
            .form-grid { grid-template-columns: 1fr; gap: 12px; }
            .form-group input,
            .form-group select {
                padding: 12px 14px;
                font-size: 16px; /* prevent iOS zoom */
                border-radius: 10px;
            }
            .form-group label { font-size: 11px; }
            .section-icon { width: 38px; height: 38px; font-size: 15px; }
            .section-title { font-size: 15px; }
            .button-group { flex-direction: column; gap: 10px; }
            .btn { width: 100%; justify-content: center; padding: 14px 20px; font-size: 15px; border-radius: 12px; }
        }

        @media (max-width: 380px) {
            .content { padding: 12px 8px; }
            .form-card { padding: 16px 12px; }
            .topbar { padding: 0 8px 0 54px; }
            .mobile-toggle { width: 34px; height: 34px; top: 13px; left: 8px; font-size: 15px; border-radius: 8px; }
        }

        @media print {
            .sidebar, .topbar, .mobile-toggle, .sidebar-backdrop, .button-group { display: none !important; }
            .main-content { margin-left: 0; }
            body { background: white; }
            .content { padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
    <button class="mobile-toggle" id="mobileToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="logo-row">
                <img src="https://madetech.com.br/wp-content/uploads/2026/05/Logo-Madetech-Final.webp" alt="Madetech">
                <h2>Sistema RAT</h2>
            </div>
            <span class="version-badge">Técnico v1.0</span>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Principal</div>
                <a href="index.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i> Meu Painel
                </a>
                <a href="criar-rat.php" class="nav-item active">
                    <i class="fas fa-plus-circle"></i> Criar RAT
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar"><?= $iniciais ?></div>
                <div class="sidebar-user-info">
                    <strong><?= htmlspecialchars($tecnico_nome) ?></strong>
                    <small><?= htmlspecialchars($tecnico_email) ?></small>
                </div>
                <a href="../api/logout.php" class="sidebar-logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <div>
                    <h1>Criar Novo RAT</h1>
                    <div class="topbar-breadcrumb">
                        <a href="index.php">Painel</a> / Criar RAT
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <a href="index.php" class="topbar-btn"><i class="fas fa-arrow-left"></i> Voltar</a>
            </div>
        </div>

        <div class="content">
            <form method="POST">
                <div class="form-card">
                    <div class="section-header">
                        <div class="section-icon"><i class="fas fa-building"></i></div>
                        <div>
                            <div class="section-title">Dados do Cliente</div>
                            <div class="section-description">Preencha para gerar um novo relatório técnico</div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Empresa *</label>
                                <button type="button" class="btn-voice-dictation" data-voice-target="cliente_empresa" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="cliente_empresa" name="cliente_empresa" placeholder="Nome da empresa" required>
                        </div>
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Responsável *</label>
                                <button type="button" class="btn-voice-dictation" data-voice-target="cliente_responsavel" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="cliente_responsavel" name="cliente_responsavel" placeholder="Nome do responsável" required>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="cliente_email" placeholder="email@empresa.com" required>
                        </div>
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Endereço</label>
                                <button type="button" class="btn-voice-dictation" data-voice-target="cliente_endereco" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="cliente_endereco" name="cliente_endereco" placeholder="Rua e número">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <div class="label-with-voice">
                                <label>Cidade</label>
                                <button type="button" class="btn-voice-dictation" data-voice-target="cliente_cidade" title="Ditar por voz">
                                    <i class="fas fa-microphone"></i> <span>Ditar</span>
                                </button>
                            </div>
                            <input type="text" id="cliente_cidade" name="cliente_cidade" placeholder="Cidade">
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <input type="text" name="cliente_estado" placeholder="SP, MG, RJ..." maxlength="2">
                        </div>
                    </div>

                    <div class="button-group">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Criar e Continuar
                        </button>
                    </div>
                </div>
            </form>
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

    // ==========================================
    // DITADO POR VOZ INTELIGENTE (Web Speech API)
    // ==========================================
    (function initVoiceDictation() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) return;

        let recognition = null;
        let currentActiveBtn = null;
        let currentTargetInput = null;
        let isListening = false;
        let restartTimeout = null;

        function formatSpeechText(text) {
            if (!text) return '';
            let formatted = text
                .replace(/\b(ponto final|ponto)\b/gi, '.')
                .replace(/\b(vírgula)\b/gi, ',')
                .replace(/\b(dois pontos)\b/gi, ':')
                .replace(/\b(ponto e vírgula)\b/gi, ';')
                .replace(/\b(interrogação|ponto de interrogação)\b/gi, '?')
                .replace(/\b(exclamação|ponto de exclamação)\b/gi, '!')
                .replace(/\b(nova linha|novo parágrafo|parágrafo)\b/gi, '\n');

            formatted = formatted.replace(/\s+([.,!?:;])/g, '$1');
            formatted = formatted.replace(/[ \t]+/g, ' ').trim();
            if (!formatted) return '';
            return formatted.charAt(0).toUpperCase() + formatted.slice(1);
        }

        function startRecognitionSession() {
            if (!isListening || !currentTargetInput) return;

            try {
                recognition = new SpeechRecognition();
                recognition.lang = 'pt-BR';
                recognition.continuous = false; // Impede duplicação no Chrome Mobile
                recognition.interimResults = true;
                recognition.maxAlternatives = 1;

                let phraseResult = '';

                recognition.onstart = function() {
                    if (currentActiveBtn && currentTargetInput) {
                        currentActiveBtn.classList.add('is-listening');
                        const span = currentActiveBtn.querySelector('span');
                        if (span) span.textContent = 'Ouvindo...';
                        currentTargetInput.classList.add('field-listening');
                    }
                };

                recognition.onresult = function(event) {
                    if (!currentTargetInput || !isListening) return;
                    for (let i = 0; i < event.results.length; ++i) {
                        if (event.results[i].isFinal) {
                            phraseResult = event.results[i][0].transcript;
                        }
                    }
                };

                recognition.onerror = function(event) {
                    console.warn('SpeechRecognition error:', event.error);
                    if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                        alert('Permissão de microfone negada. Permita o microfone no navegador para ditar.');
                        stopDictation();
                    }
                };

                recognition.onend = function() {
                    if (phraseResult && currentTargetInput) {
                        const formatted = formatSpeechText(phraseResult);
                        if (formatted) {
                            const currentVal = (currentTargetInput.value || '').trim();
                            if (currentVal) {
                                const lastChar = currentVal.slice(-1);
                                const separator = (lastChar === '\n') ? '' : ' ';
                                currentTargetInput.value = currentVal + separator + formatted;
                            } else {
                                currentTargetInput.value = formatted;
                            }
                            currentTargetInput.dispatchEvent(new Event('input', { bubbles: true }));
                            currentTargetInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        phraseResult = '';
                    }

                    if (isListening) {
                        clearTimeout(restartTimeout);
                        restartTimeout = setTimeout(() => {
                            if (isListening) startRecognitionSession();
                        }, 200);
                    } else {
                        stopDictation();
                    }
                };

                recognition.start();
            } catch(err) {
                console.error('Erro ao iniciar reconhecimento de fala:', err);
                stopDictation();
            }
        }

        function stopDictation() {
            isListening = false;
            clearTimeout(restartTimeout);

            if (recognition) {
                try { recognition.stop(); } catch(e) {}
                recognition = null;
            }

            if (currentActiveBtn) {
                currentActiveBtn.classList.remove('is-listening');
                const span = currentActiveBtn.querySelector('span');
                if (span) span.textContent = 'Ditar';
            }
            if (currentTargetInput) currentTargetInput.classList.remove('field-listening');
            currentActiveBtn = null;
            currentTargetInput = null;
        }

        function toggleDictation(btn, targetInput) {
            if (!targetInput) return;

            if (isListening && currentActiveBtn === btn) {
                stopDictation();
                return;
            }

            if (isListening) {
                stopDictation();
            }

            currentActiveBtn = btn;
            currentTargetInput = targetInput;
            isListening = true;

            startRecognitionSession();
        }

        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-voice-dictation');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            const targetId = btn.getAttribute('data-voice-target');
            const targetInput = targetId ? document.getElementById(targetId) : null;
            if (targetInput) toggleDictation(btn, targetInput);
        });
    })();
    </script>
</body>
</html>
