<?php
/**
 * API de Backup e Reset - Sistema RAT
 * Apenas para fase de testes
 */
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'backup':
        // Gerar backup em JSON
        $db = getDB();
        $stmt = $db->query("SELECT * FROM rats ORDER BY id DESC");
        $rats = $stmt->fetchAll();
        
        $backup = [
            'data_backup' => date('Y-m-d H:i:s'),
            'total_registros' => count($rats),
            'rats' => $rats
        ];
        
        // Salvar arquivo de backup
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = 'backup_rats_' . date('Y-m-d_His') . '.json';
        $filepath = $backupDir . '/' . $filename;
        file_put_contents($filepath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup realizado com sucesso!',
            'arquivo' => $filename,
            'registros' => count($rats)
        ]);
        break;
        
    case 'download_backup':
        // Download do último backup
        $backupDir = __DIR__ . '/../backups';
        $files = glob($backupDir . '/backup_rats_*.json');
        
        if (empty($files)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Nenhum backup encontrado']);
            exit;
        }
        
        // Pegar o mais recente
        rsort($files);
        $latestBackup = $files[0];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . basename($latestBackup) . '"');
        readfile($latestBackup);
        exit;
        
    case 'reset':
        // Resetar banco de dados
        $db = getDB();
        
        // Deletar todos os registros
        $db->exec("DELETE FROM rats");
        
        // Resetar o autoincrement
        $db->exec("DELETE FROM sqlite_sequence WHERE name='rats'");
        
        echo json_encode([
            'success' => true,
            'message' => 'Banco de dados resetado com sucesso!'
        ]);
        break;
        
    case 'list_backups':
        // Listar todos os backups
        $backupDir = __DIR__ . '/../backups';
        $files = glob($backupDir . '/backup_rats_*.json');
        
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'nome' => basename($file),
                'tamanho' => filesize($file),
                'data' => date('d/m/Y H:i:s', filemtime($file))
            ];
        }
        
        rsort($backups);
        
        echo json_encode([
            'success' => true,
            'backups' => $backups
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Ação não especificada'
        ]);
}
