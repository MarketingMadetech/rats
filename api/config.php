<?php
/**
 * Configurações do Sistema RAT - Madetech
 */

// Configurações gerais
define('SITE_NAME', 'Sistema RAT - Madetech');
define('WHATSAPP_NOTIFY', '5511920664794'); // Número para notificações
define('RAT_PREFIX', 'RAT');

// Caminho do banco de dados SQLite
define('DB_PATH', __DIR__ . '/../data/rats.db');

// URL base do sistema
define('SITE_URL', 'https://madetech.com.br');
define('BASE_URL', '/rats/');

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Função para conectar ao banco
function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    } catch (PDOException $e) {
        die("Erro de conexão: " . $e->getMessage());
    }
}

// Inicializar banco de dados se não existir
function initDB() {
    $db = getDB();
    
    // Criar tabela de Administradores
    $db->exec("
        CREATE TABLE IF NOT EXISTS administradores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            senha VARCHAR(255) NOT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Criar tabela de Técnicos
    $db->exec("
        CREATE TABLE IF NOT EXISTS tecnicos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            senha VARCHAR(255) NOT NULL,
            telefone VARCHAR(20),
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Criar tabela de RATs (atualizada)
    $db->exec("
        CREATE TABLE IF NOT EXISTS rats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero VARCHAR(20) NOT NULL,
            numero_sequencial INTEGER,
            id_tecnico INTEGER NOT NULL,
            token VARCHAR(64) UNIQUE,
            status VARCHAR(20) DEFAULT 'rascunho',
            
            -- Dados do cliente
            cliente_empresa VARCHAR(255),
            cliente_responsavel VARCHAR(255),
            endereco TEXT,
            cidade VARCHAR(100),
            estado VARCHAR(50),
            email_cliente VARCHAR(255),
            
            -- Dados técnicos
            equipamento VARCHAR(255),
            modelo_maquina VARCHAR(255),
            matricula VARCHAR(100),
            garantia VARCHAR(10),
            
            -- Serviço
            defeito_constatado TEXT,
            trabalho_executado TEXT,
            horas_servico DECIMAL(10,2),
            
            -- Turnos (JSON)
            turnos_json TEXT,
            total_turnos VARCHAR(50),
            
            -- Viagem
            horas_viajadas_json TEXT,
            total_horas_viajadas VARCHAR(50),
            kms_rodados_json TEXT,
            total_kms VARCHAR(50),
            
            -- Adicionais
            pedagio DECIMAL(10,2) DEFAULT 0,
            hospedagem DECIMAL(10,2) DEFAULT 0,
            alimentacao DECIMAL(10,2) DEFAULT 0,
            total_adicionais DECIMAL(10,2) DEFAULT 0,
            
            -- Assinaturas (base64)
            assinatura_cliente TEXT,
            assinatura_tecnico TEXT,
            aceite_cliente BOOLEAN DEFAULT 0,
            aceite_tecnico BOOLEAN DEFAULT 0,
            nome_assinatura VARCHAR(255) DEFAULT '',
            cargo_assinatura VARCHAR(255) DEFAULT '',
            cpf_assinatura VARCHAR(20) DEFAULT '',
            
            -- Emails automáticos
            email_suporte VARCHAR(255),
            email_marketing VARCHAR(255),
            
            -- Datas
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_envio DATETIME,
            data_preenchimento DATETIME,
            
            -- Notas adicionais
            observacoes TEXT,
            pago_reembolso INTEGER DEFAULT 0,
            lancado_reembolso INTEGER DEFAULT 0,
            sem_lancamento INTEGER DEFAULT 0,
            
            -- Foreign key
            FOREIGN KEY (id_tecnico) REFERENCES tecnicos(id)
        )
    ");
    
    // Migrations: adicionar colunas que não estão no CREATE TABLE original
    $migrations = [
        "ALTER TABLE rats ADD COLUMN tipo_servico VARCHAR(50) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN outros_despesas_desc VARCHAR(255) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN outros_despesas DECIMAL(10,2) DEFAULT 0",
        "ALTER TABLE rats ADD COLUMN pedagio_qtd INTEGER DEFAULT 1",
        "ALTER TABLE rats ADD COLUMN alimentacao_qtd INTEGER DEFAULT 1",
        "ALTER TABLE rats ADD COLUMN outros_qtd INTEGER DEFAULT 1",
        "ALTER TABLE rats ADD COLUMN hospedagem_dias INTEGER DEFAULT 1",
        "ALTER TABLE rats ADD COLUMN atualizado_em DATETIME",
        "ALTER TABLE rats ADD COLUMN enviado_em DATETIME",
        "ALTER TABLE rats ADD COLUMN total_horas_trabalhadas VARCHAR(50) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN total_horas_viajadas_calc VARCHAR(50) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN notas_fiscais_json TEXT DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN nome_assinatura VARCHAR(255) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN cargo_assinatura VARCHAR(255) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN cpf_assinatura VARCHAR(20) DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN feedback_suporte TEXT DEFAULT ''",
        "ALTER TABLE rats ADD COLUMN data_feedback DATETIME",
        "ALTER TABLE rats ADD COLUMN orcamento_json TEXT DEFAULT '[]'",
        "ALTER TABLE rats ADD COLUMN tecnicos_adicionais_json TEXT DEFAULT '[]'",
        "ALTER TABLE rats ADD COLUMN pago_reembolso INTEGER DEFAULT 0",
        "ALTER TABLE rats ADD COLUMN lancado_reembolso INTEGER DEFAULT 0",
        "ALTER TABLE rats ADD COLUMN sem_lancamento INTEGER DEFAULT 0",
    ];
    foreach ($migrations as $sql) {
        try { $db->exec($sql); } catch(Exception $e) { /* coluna já existe */ }
    }

    inicializarShowroom($db);
    
    return $db;
}

// Normalizar chaves legadas de tipo do Showroom para nomes legíveis das famílias Madetech
function normalizarTipoShowroom($tipo) {
    $t = trim((string)($tipo ?? ''));
    $mapa = [
        'router'          => 'Centro Nesting CNC',
        'coladeira'       => 'Coladeira de Bordos',
        'seccionadora'    => 'Seccionadora Vertical',
        'furadeira'       => 'Centro de Furação',
        'esquadrejadeira' => 'Esquadrejadeira',
        'laser'           => 'Solda a Laser',
        'prensa'          => 'Guilhotina Hidráulica',
        'outro'           => 'Outro',
    ];
    $key = strtolower($t);
    if (isset($mapa[$key])) {
        return $mapa[$key];
    }
    return $t !== '' ? $t : 'Outro';
}

// Criar e popular tabela inicial de Máquinas Showroom (executado apenas na criação da tabela)
function inicializarShowroom($db = null) {
    if (!$db) {
        $db = getDB();
    }
    $existe = (bool)$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='showroom_maquinas'")->fetchColumn();
    $db->exec("
        CREATE TABLE IF NOT EXISTS showroom_maquinas (
            id VARCHAR(64) PRIMARY KEY,
            nome VARCHAR(150) NOT NULL,
            modelo VARCHAR(150) DEFAULT '',
            serie VARCHAR(80) DEFAULT '',
            tipo VARCHAR(100) DEFAULT 'Outro',
            quantidade INTEGER DEFAULT 1,
            estado VARCHAR(30) DEFAULT 'sem_info',
            obs TEXT DEFAULT '',
            info_extra TEXT DEFAULT '',
            atualizado_em VARCHAR(40) DEFAULT '',
            atualizado_por VARCHAR(150) DEFAULT '',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    try {
        $db->exec("ALTER TABLE showroom_maquinas ADD COLUMN info_extra TEXT DEFAULT ''");
    } catch (Exception $e) {
        // Coluna já existe
    }

    try {
        $db->exec("ALTER TABLE showroom_maquinas ADD COLUMN quantidade INTEGER DEFAULT 1");
    } catch (Exception $e) {
        // Coluna já existe
    }

    // Migrar códigos legados ('router', 'coladeira', etc.) para os nomes reais das categorias
    $mapa = [
        'router'          => 'Centro Nesting CNC',
        'coladeira'       => 'Coladeira de Bordos',
        'seccionadora'    => 'Seccionadora Vertical',
        'furadeira'       => 'Centro de Furação',
        'esquadrejadeira' => 'Esquadrejadeira',
        'laser'           => 'Solda a Laser',
        'prensa'          => 'Guilhotina Hidráulica',
        'outro'           => 'Outro',
    ];
    try {
        $upd = $db->prepare("UPDATE showroom_maquinas SET tipo = ? WHERE LOWER(tipo) = ?");
        foreach ($mapa as $oldKey => $newLabel) {
            $upd->execute([$newLabel, $oldKey]);
        }
    } catch (Exception $e) {}

    if (!$existe) {
        $seed = [
            ['Centro Nesting CNC', 'CutPro', 'Centro Nesting CNC'],
            ['Coladeira de Bordos Contour S-Line', 'Contour S-Line', 'Coladeira de Bordos'],
            ['Seccionadora Vertical', 'SVP 145 Plus', 'Seccionadora Vertical'],
            ['Centro de Furação MaxDrill', 'MaxDrill M6.5', 'Centro de Furação'],
            ['Esquadrejadeira MJ1132C', 'MJ1132C', 'Esquadrejadeira'],
        ];
        $agora = date('c');
        $stmt = $db->prepare("
            INSERT INTO showroom_maquinas (id, nome, modelo, serie, tipo, estado, obs, info_extra, atualizado_em, atualizado_por)
            VALUES (?, ?, ?, '', ?, 'sem_info', '', '', ?, '')
        ");
        foreach ($seed as $idx => $item) {
            $id = 'm' . time() . ($idx + 1);
            $stmt->execute([$id, $item[0], $item[1], $item[2], $agora]);
        }
    }
}

// Contar máquinas do Showroom com problema (para badge no menu lateral)
function contarProblemasShowroom($db = null) {
    try {
        if (!$db) {
            $db = getDB();
        }
        $stmt = $db->query("SELECT COALESCE(SUM(CASE WHEN COALESCE(quantidade, 1) > 0 THEN COALESCE(quantidade, 1) ELSE 1 END), 0) as total FROM showroom_maquinas WHERE estado IN ('restricao', 'manutencao', 'aguardando', 'parada')");
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

// Obter próximo número sequencial de RAT para o técnico (inteiro)
function obterProximoSequencialRAT($id_tecnico) {
    $db = getDB();
    $stmt = $db->prepare("SELECT MAX(numero_sequencial) as max_seq FROM rats WHERE id_tecnico = ?");
    $stmt->execute([$id_tecnico]);
    $result = $stmt->fetch();
    return ($result['max_seq'] ?? 0) + 1;
}

// Gerar número do RAT sequencial formatado POR TÉCNICO (RAT-XX-YYYY)
function gerarNumeroRATporTecnico($id_tecnico, $seq = null) {
    if ($seq === null) {
        $seq = obterProximoSequencialRAT($id_tecnico);
    }
    return "RAT-" . str_pad($id_tecnico, 2, '0', STR_PAD_LEFT) . "-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// Gerar número do RAT baseado no id real (chamado após o INSERT)
function gerarNumeroRAT($id) {
    return (string)$id;
}

// Gerar número do RAT sequencial POR TÉCNICO (chamado ao enviar rascunho)
function gerarNumeroRATGlobal($id_tecnico) {
    return obterProximoSequencialRAT($id_tecnico);
}

// Exibir apenas o número limpo do RAT (sem prefixo RAT-XX-YYYY se for formatado)
function exibirNumeroRAT($numero, $numero_sequencial = null) {
    if (!empty($numero_sequencial)) {
        return (string)$numero_sequencial;
    }
    if (preg_match('/^RAT-\d{2}-(\d{4})$/', $numero, $matches)) {
        return (string)intval($matches[1]);
    }
    return $numero;
}

// Gerar token único para o formulário
function gerarToken() {
    return bin2hex(random_bytes(32));
}

// Formatar para WhatsApp
function formatWhatsAppLink($numero, $mensagem) {
    $mensagem = urlencode($mensagem);
    return "https://wa.me/{$numero}?text={$mensagem}";
}

// Configurações de Email (Google Workspace via SSL/465)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'marketing@madetech.com.br');
define('SMTP_PASS', 'ygxe jfci uosn epci');
define('SMTP_SECURE', 'ssl');
define('EMAIL_SUPORTE_CENTRAL', 'suporte@madeparts.com.br');
define('EMAIL_MARKETING_CENTRAL', 'marketing@madetech.com.br');
define('EMAIL_ADMINISTRATIVO', 'raquel@madeparts.com.br');
define('EMAIL_FROM_NAME', 'Sistema RAT - Madetech');

// Inicializar banco na primeira execução
if (!file_exists(DB_PATH)) {
    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0755, true);
    }
    initDB();
} else {
    // Banco já existe: só rodar as migrations de colunas novas (seguro, erros ignorados)
    try {
        $db_migrate = getDB();
        $migrations = [
            "ALTER TABLE rats ADD COLUMN tipo_servico VARCHAR(50) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN outros_despesas_desc VARCHAR(255) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN outros_despesas DECIMAL(10,2) DEFAULT 0",
            "ALTER TABLE rats ADD COLUMN pedagio_qtd INTEGER DEFAULT 1",
            "ALTER TABLE rats ADD COLUMN alimentacao_qtd INTEGER DEFAULT 1",
            "ALTER TABLE rats ADD COLUMN outros_qtd INTEGER DEFAULT 1",
            "ALTER TABLE rats ADD COLUMN hospedagem_dias INTEGER DEFAULT 1",
            "ALTER TABLE rats ADD COLUMN atualizado_em DATETIME",
            "ALTER TABLE rats ADD COLUMN enviado_em DATETIME",
            "ALTER TABLE rats ADD COLUMN total_horas_trabalhadas VARCHAR(50) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN total_horas_viajadas_calc VARCHAR(50) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN notas_fiscais_json TEXT DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN nome_assinatura VARCHAR(255) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN cargo_assinatura VARCHAR(255) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN cpf_assinatura VARCHAR(20) DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN feedback_suporte TEXT DEFAULT ''",
            "ALTER TABLE rats ADD COLUMN data_feedback DATETIME",
            "ALTER TABLE rats ADD COLUMN orcamento_json TEXT DEFAULT '[]'",
            "ALTER TABLE rats ADD COLUMN tecnicos_adicionais_json TEXT DEFAULT '[]'",
            "ALTER TABLE rats ADD COLUMN pago_reembolso INTEGER DEFAULT 0",
            "ALTER TABLE rats ADD COLUMN lancado_reembolso INTEGER DEFAULT 0",
            "ALTER TABLE rats ADD COLUMN sem_lancamento INTEGER DEFAULT 0",
        ];
        foreach ($migrations as $sql) {
            try { $db_migrate->exec($sql); } catch(Exception $e) { /* coluna já existe */ }
        }
        inicializarShowroom($db_migrate);
        unset($db_migrate);
    } catch(Exception $e) { /* silencioso */ }
}
?>
