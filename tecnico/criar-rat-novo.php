<?php
require_once '../api/config.php';
require_once '../api/auth-tecnico.php';

verificarTecnico();

$db = getDB();
$tecnico = obterTecnicoAtual($db);
$erro = '';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa = $_POST['cliente_empresa'] ?? '';
    $responsavel = $_POST['cliente_responsavel'] ?? '';
    $email_cliente = $_POST['email_cliente'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $cidade = $_POST['cidade'] ?? '';
    $estado = $_POST['estado'] ?? '';
    
    if ($empresa && $responsavel && $email_cliente) {
        try {
            $seq = obterProximoSequencialRAT($_SESSION['tecnico_id']);
            $numero_rat = gerarNumeroRATporTecnico($_SESSION['tecnico_id'], $seq);
            
            $stmt = $db->prepare("
                INSERT INTO rats (numero, numero_sequencial, id_tecnico, status, cliente_empresa, cliente_responsavel, email_cliente, endereco, cidade, estado, data_criacao)
                VALUES (?, ?, ?, 'rascunho', ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([$numero_rat, $seq, $_SESSION['tecnico_id'], $empresa, $responsavel, $email_cliente, $endereco, $cidade, $estado]);
            
            $rat_id = $db->lastInsertId();
            header("Location: editar-rat.php?id=$rat_id");
            exit;
        } catch (Exception $e) {
            $erro = "Erro ao criar RAT: " . $e->getMessage();
        }
    } else {
        $erro = "Preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo RAT | Sistema RAT - Madetech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            color: #2d3748;
            min-height: 100vh;
        }
        
        /* HEADER */
        header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -1px;
        }
        
        .header-btn {
            padding: 8px 16px;
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .header-btn:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }
        
        /* CONTAINER */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* BREADCRUMB */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb-sep {
            color: #cbd5e1;
        }
        
        /* PAGE TITLE */
        .page-header {
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            font-size: 36px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-header p {
            font-size: 15px;
            color: #718096;
        }
        
        /* ALERT */
        .alert {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #ef4444;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 30px;
            color: #991b1b;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* FORM CARD */
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e0e7ff;
        }
        
        .form-section-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        /* FORM GRID */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .required {
            color: #ef4444;
        }
        
        input, textarea, select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f7fafc;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #10b981;
            background: white;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            transform: translateY(-2px);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* BUTTONS */
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 40px;
            padding-top: 24px;
            border-top: 2px solid #e0e7ff;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-secondary {
            background: white;
            color: #10b981;
            border: 2px solid #10b981;
            box-shadow: none;
        }
        
        .btn-secondary:hover {
            background: #f0fdf4;
        }
        
        .info-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #e0f2fe 100%);
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            font-size: 13px;
            color: #065f46;
            line-height: 1.6;
        }
        
        .info-box strong {
            color: #10b981;
            display: block;
            margin-bottom: 8px;
        }
        
        .info-box i {
            margin-right: 6px;
        }
        
        @media (max-width: 768px) {
            header {
                padding: 16px 20px;
            }
            
            .container {
                padding: 20px;
            }
            
            .form-card {
                padding: 24px;
            }
            
            .page-header h1 {
                font-size: 28px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <header>
        <div class="header-content">
            <div class="logo">⚙️ SISTEMA RAT</div>
            <a href="index.php" class="header-btn">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </header>
    
    <!-- MAIN -->
    <div class="container">
        <!-- BREADCRUMB -->
        <div class="breadcrumb">
            <a href="index.php">Meu Painel</a>
            <span class="breadcrumb-sep">/</span>
            <span>Criar Novo RAT</span>
        </div>
        
        <!-- PAGE TITLE -->
        <div class="page-header">
            <h1>📋 Criar Novo RAT</h1>
            <p>Preencha os dados do cliente para iniciar um novo relatório técnico</p>
        </div>
        
        <!-- ALERT -->
        <?php if ($erro): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $erro; ?>
            </div>
        <?php endif; ?>
        
        <!-- FORM -->
        <form method="POST" class="form-card">
            <!-- DADOS DO CLIENTE -->
            <div class="form-section-title">
                <div class="form-section-icon">👥</div>
                Dados do Cliente
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="cliente_empresa">
                        <i class="fas fa-building"></i> Empresa/Razão Social
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="cliente_empresa" name="cliente_empresa" placeholder="Ex: Madetech Indústrias" required>
                </div>
                
                <div class="form-group">
                    <label for="cliente_responsavel">
                        <i class="fas fa-user"></i> Responsável
                        <span class="required">*</span>
                    </label>
                    <input type="text" id="cliente_responsavel" name="cliente_responsavel" placeholder="Ex: João da Silva" required>
                </div>
                
                <div class="form-group">
                    <label for="email_cliente">
                        <i class="fas fa-envelope"></i> Email do Cliente
                        <span class="required">*</span>
                    </label>
                    <input type="email" id="email_cliente" name="email_cliente" placeholder="cliente@exemplo.com" required>
                </div>
                
                <div class="form-group">
                    <label for="endereco">
                        <i class="fas fa-map-marker-alt"></i> Endereço
                    </label>
                    <input type="text" id="endereco" name="endereco" placeholder="Ex: Rua das Flores, 123">
                </div>
                
                <div class="form-group">
                    <label for="cidade">
                        <i class="fas fa-city"></i> Cidade
                    </label>
                    <input type="text" id="cidade" name="cidade" placeholder="Ex: São Paulo">
                </div>
                
                <div class="form-group">
                    <label for="estado">
                        <i class="fas fa-map"></i> Estado
                    </label>
                    <select id="estado" name="estado">
                        <option value="">Selecionar Estado</option>
                        <option value="SP">São Paulo</option>
                        <option value="RJ">Rio de Janeiro</option>
                        <option value="MG">Minas Gerais</option>
                        <option value="BA">Bahia</option>
                        <option value="RS">Rio Grande do Sul</option>
                        <option value="SC">Santa Catarina</option>
                        <option value="PR">Paraná</option>
                        <option value="PE">Pernambuco</option>
                        <option value="CE">Ceará</option>
                        <option value="PA">Pará</option>
                        <option value="GO">Goiás</option>
                        <option value="DF">Distrito Federal</option>
                        <option value="MT">Mato Grosso</option>
                        <option value="MS">Mato Grosso do Sul</option>
                        <option value="ES">Espírito Santo</option>
                        <option value="AL">Alagoas</option>
                        <option value="AM">Amazonas</option>
                        <option value="AP">Amapá</option>
                        <option value="PI">Piauí</option>
                        <option value="RN">Rio Grande do Norte</option>
                        <option value="RO">Rondônia</option>
                        <option value="RR">Roraima</option>
                        <option value="SE">Sergipe</option>
                        <option value="TO">Tocantins</option>
                    </select>
                </div>
            </div>
            
            <!-- INFO BOX -->
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> Informação Importante</strong>
                Um número de RAT único será gerado automaticamente para você: <strong><?php echo gerarNumeroRATporTecnico($_SESSION['tecnico_id']); ?></strong>
            </div>
            
            <!-- BUTTONS -->
            <div class="button-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Criar e Continuar Preenchendo
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</body>
</html>
