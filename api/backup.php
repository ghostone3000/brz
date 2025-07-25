<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/config.php', 
    'config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    jsonResponse(['success' => false, 'error' => 'Nie można załadować konfiguracji'], 500);
}

class BackupAPI {
    private $pdo;
    private $backupDir;

    public function __construct() {
        try {
            $db = Database::getInstance();
            $this->pdo = $db->getConnection();
            $this->backupDir = dirname(__DIR__) . '/backups/';
            
            if (!is_dir($this->backupDir)) {
                if (!mkdir($this->backupDir, 0755, true)) {
                    throw new Exception('Nie można utworzyć katalogu backupów');
                }
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd inicjalizacji backup: ' . $e->getMessage()], 500);
        }
    }

    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'create':
                    $this->createBackup();
                    break;
                case 'list':
                    $this->listBackups();
                    break;
                case 'restore':
                    $this->restoreBackup();
                    break;
                case 'delete':
                    $this->deleteBackup();
                    break;
                case 'auto':
                    $this->autoBackup();
                    break;
                default:
                    jsonResponse(['success' => false, 'error' => 'Nieznana akcja backup'], 400);
            }
        } catch (Exception $e) {
            error_log("Backup Error: " . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Błąd backup: ' . $e->getMessage()], 500);
        }
    }

    private function createBackup() {
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
            $filepath = $this->backupDir . $filename;

            $stmt = $this->pdo->prepare("SELECT * FROM przedmioty ORDER BY id");
            $stmt->execute();
            $data = $stmt->fetchAll();

            $backupData = [
                'version' => '1.0',
                'created_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data),
                'database_name' => DB_NAME,
                'data' => $data
            ];

            $json = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($filepath, $json) !== false) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Backup został utworzony pomyślnie',
                    'filename' => $filename,
                    'records' => count($data),
                    'size' => filesize($filepath)
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => 'Nie udało się zapisać pliku backup'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd tworzenia backup: ' . $e->getMessage()], 500);
        }
    }

    private function listBackups() {
        try {
            $files = glob($this->backupDir . 'backup_*.json');
            $backups = [];

            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'created' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }

            jsonResponse(['success' => true, 'backups' => $backups]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd listowania backupów: ' . $e->getMessage()], 500);
        }
    }

    private function restoreBackup() {
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = $input['filename'] ?? null;

        if (!$filename) {
            jsonResponse(['success' => false, 'error' => 'Brak nazwy pliku backup'], 400);
        }

        $filepath = $this->backupDir . $filename;
        if (!file_exists($filepath)) {
            jsonResponse(['success' => false, 'error' => 'Plik backup nie został znaleziony'], 404);
        }

        try {
            $json = file_get_contents($filepath);
            $backupData = json_decode($json, true);

            if (!$backupData || !isset($backupData['data'])) {
                jsonResponse(['success' => false, 'error' => 'Nieprawidłowy format pliku backup'], 400);
            }

            $this->pdo->exec("TRUNCATE TABLE przedmioty");

            $sql = "INSERT INTO przedmioty (id, lp, kategoria, imie_nazwisko, opis, typ_dokumentu, marka, adres, osoba_przyjmujaca, status, data_utworzenia, data_modyfikacji) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);

            foreach ($backupData['data'] as $item) {
                $stmt->execute([
                    $item['id'],
                    $item['lp'],
                    $item['kategoria'],
                    $item['imie_nazwisko'],
                    $item['opis'],
                    $item['typ_dokumentu'],
                    $item['marka'],
                    $item['adres'],
                    $item['osoba_przyjmujaca'],
                    $item['status'],
                    $item['data_utworzenia'],
                    $item['data_modyfikacji']
                ]);
            }

            jsonResponse([
                'success' => true,
                'message' => 'Backup został przywrócony pomyślnie',
                'records' => count($backupData['data'])
            ]);

        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd przywracania backup: ' . $e->getMessage()], 500);
        }
    }

    private function deleteBackup() {
        $input = json_decode(file_get_contents('php://input'), true);
        $filename = $input['filename'] ?? null;

        if (!$filename) {
            jsonResponse(['success' => false, 'error' => 'Brak nazwy pliku backup'], 400);
        }

        $filepath = $this->backupDir . $filename;
        if (!file_exists($filepath)) {
            jsonResponse(['success' => false, 'error' => 'Plik backup nie został znaleziony'], 404);
        }

        try {
            if (unlink($filepath)) {
                jsonResponse(['success' => true, 'message' => 'Backup został usunięty']);
            } else {
                jsonResponse(['success' => false, 'error' => 'Nie udało się Авusunąć backup'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd usuwania backup: ' . $e->getMessage()], 500);
        }
    }

    private function autoBackup() {
        try {
            $filename = 'auto_backup_' . date('Y-m-d_H-i') . '.json';
            $filepath = $this->backupDir . $filename;

            if (file_exists($filepath)) {
                jsonResponse(['success' => true, 'message' => 'Auto backup już istnieje']);
                return;
            }

            $stmt = $this->pdo->prepare("SELECT * FROM przedmioty ORDER BY id");
            $stmt->execute();
            $data = $stmt->fetchAll();

            $backupData = [
                'version' => '1.0',
                'type' => 'auto',
                'created_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data),
                'database_name' => DB_NAME,
                'data' => $data
            ];

            $json = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($filepath, $json) !== false) {
                $this->cleanOldAutoBackups();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Auto backup utworzony',
                    'filename' => $filename,
                    'records' => count($data)
                ]);
            } else {
                jsonResponse(['success' => false, 'error' => 'Nie udało się utworzyć auto backup'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd auto backup: ' . $e->getMessage()], 500);
        }
    }

    private function cleanOldAutoBackups() {
        $files = glob($this->backupDir . 'auto_backup_*.json');
        $cutoff = time() - (24 * 60 * 60); // 24 godziny

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

$api = new BackupAPI();
$api->handleRequest();
?>