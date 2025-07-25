<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Obsługa zapytań OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Konfiguracja bazy danych
define('DB_HOST', 'localhost');
define('DB_USER', 'brzuser');
define('DB_PASS', 'BRZPolandRock2025!');
define('DB_NAME', 'brz_polandrock2025');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $pdo;
    private static $instance = null;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Błąd połączenia z bazą danych: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'Błąd połączenia z bazą danych',
                'details' => $e->getMessage()
            ]);
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
    
    public function testConnection() {
        try {
            $stmt = $this->pdo->prepare("SELECT 1");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function checkTableExists() {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'przedmioty'");
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            return "Pole '$field' jest wymagane";
        }
    }
    return null;
}

// PRZYWRÓCONY test połączenia dla debugowania
if (isset($_GET['test'])) {
    try {
        $db = Database::getInstance();
        if ($db->testConnection() && $db->checkTableExists()) {
            $stmt = $db->getConnection()->prepare("SELECT COUNT(*) as count FROM przedmioty");
            $stmt->execute();
            $result = $stmt->fetch();
            jsonResponse([
                'success' => true, 
                'message' => 'Baza działa prawidłowo', 
                'count' => $result['count'],
                'database' => DB_NAME,
                'user' => DB_USER
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Problem z bazą danych lub tabelą']);
        }
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
