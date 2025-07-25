<?php
// Nagłówki CORS - MUSZĄ być na początku przed jakimkolwiek output
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Obsługa zapytań OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include konfiguracji - różne ścieżki dla różnych struktur katalogów
$config_paths = [
    __DIR__ . '/../config.php',  // Katalog nadrzędny  
    __DIR__ . '/config.php',     // Ten sam katalog
    'config.php',                // Relatywna ścieżka
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
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Nie można załadować konfiguracji bazy danych'], JSON_UNESCAPED_UNICODE);
    exit;
}

class ItemsAPI {
    private $pdo;

    public function __construct() {
        try {
            $db = Database::getInstance();
            $this->pdo = $db->getConnection();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Błąd połączenia z bazą: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        try {
            switch ($method) {
                case 'GET':
                    $this->handleGet();
                    break;
                case 'POST':
                    $this->handlePost();
                    break;
                case 'PUT':
                    $this->handlePut();
                    break;
                case 'DELETE':
                    $this->handleDelete();
                    break;
                default:
                    jsonResponse(['success' => false, 'error' => 'Metoda nie obsługiwana'], 405);
            }
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Błąd serwera: ' . $e->getMessage()], 500);
        }
    }

    private function handleGet() {
        $lp = $_GET['lp'] ?? '';
        $name = $_GET['name'] ?? '';
        $category = $_GET['category'] ?? '';
        $brand = $_GET['brand'] ?? '';
        $status = $_GET['status'] ?? '';

        $sql = "SELECT * FROM przedmioty WHERE 1=1";
        $params = [];

        if (!empty($lp)) {
            $sql .= " AND lp LIKE ?";
            $params[] = "%$lp%";
        }

        if (!empty($name)) {
            $sql .= " AND imie_nazwisko LIKE ?";
            $params[] = "%$name%";
        }

        if (!empty($category)) {
            $sql .= " AND kategoria = ?";
            $params[] = $category;
        }

        if (!empty($brand)) {
            $sql .= " AND marka LIKE ?";
            $params[] = "%$brand%";
        }

        if (!empty($status)) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY data_utworzenia DESC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
            jsonResponse(['success' => true, 'data' => $items]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd pobierania danych: ' . $e->getMessage()], 500);
        }
    }

    private function handlePost() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            jsonResponse(['success' => false, 'error' => 'Brak danych JSON'], 400);
        }

        // Walidacja wymaganych pól
        $requiredFields = ['lp', 'kategoria', 'opis', 'osoba_przyjmujaca'];
        $error = validateRequired($input, $requiredFields);
        if ($error) {
            jsonResponse(['success' => false, 'error' => $error], 400);
        }

        // Walidacja specyficznych pól dla kategorii
        $docTypeRequired = ['Dokumenty', 'Portfele', 'Plecaki i nerki'];
        if (in_array($input['kategoria'], $docTypeRequired) && empty($input['typ_dokumentu'])) {
            jsonResponse(['success' => false, 'error' => 'Typ dokumentu jest wymagany dla tej kategorii'], 400);
        }

        if ($input['kategoria'] === 'Telefony' && empty($input['marka'])) {
            jsonResponse(['success' => false, 'error' => 'Marka jest wymagana dla telefonów'], 400);
        }

        // Walidacja czy imię i nazwisko jest wymagane dla niektórych kategorii
        $requireNameCategories = ['Dokumenty', 'Portfele', 'Plecaki i nerki'];
        if (in_array($input['kategoria'], $requireNameCategories) &&
            (empty($input['imie_nazwisko']) || trim($input['imie_nazwisko']) === '')) {
            jsonResponse(['success' => false, 'error' => 'Imię i nazwisko jest wymagane dla kategorii: ' . $input['kategoria']], 400);
        }

        // Sprawdzenie czy LP już istnieje
        try {
			$stmt = $this->pdo->prepare("SELECT id FROM przedmioty WHERE lp = ?");
			$stmt->execute([$input['lp']]);
			if ($stmt->fetch()) {
				jsonResponse(['success' => false, 'error' => 'Przedmiot o numerze LP "' . $input['lp'] . '" już istnieje. Wybierz inny numer.'], 400);
			}
		} catch (Exception $e) {
			jsonResponse(['success' => false, 'error' => 'Błąd sprawdzania duplikatów LP'], 500);
		}

        $sql = "INSERT INTO przedmioty (lp, kategoria, imie_nazwisko, opis, typ_dokumentu, marka, adres, osoba_przyjmujaca, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $input['lp'],
                $input['kategoria'],
                $input['imie_nazwisko'] ?? null,
                $input['opis'],
                $input['typ_dokumentu'] ?? null,
                $input['marka'] ?? null,
                $input['adres'] ?? null,
                $input['osoba_przyjmujaca'],
                $input['status'] ?? 'Znaleziony'
            ]);

            if ($result) {
                $id = $this->pdo->lastInsertId();
                $stmt = $this->pdo->prepare("SELECT * FROM przedmioty WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                jsonResponse(['success' => true, 'message' => 'Przedmiot został dodany', 'data' => $item], 201);
            } else {
                jsonResponse(['success' => false, 'error' => 'Nie udało się dodać przedmiotu'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd zapisu do bazy: ' . $e->getMessage()], 500);
        }
    }

    private function handlePut() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            jsonResponse(['success' => false, 'error' => 'Brak ID przedmiotu'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsonResponse(['success' => false, 'error' => 'Brak danych JSON'], 400);
        }

        // Sprawdzenie czy przedmiot istnieje
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM przedmioty WHERE id = ?");
            $stmt->execute([$id]);
            $existingItem = $stmt->fetch();
            if (!$existingItem) {
                jsonResponse(['success' => false, 'error' => 'Przedmiot nie został znaleziony'], 404);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd sprawdzania przedmiotu'], 500);
        }

        $sql = "UPDATE przedmioty SET 
                kategoria = ?, imie_nazwisko = ?, opis = ?, typ_dokumentu = ?, 
                marka = ?, adres = ?, osoba_przyjmujaca = ?, status = ? 
                WHERE id = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $input['kategoria'] ?? $existingItem['kategoria'],
                $input['imie_nazwisko'] ?? $existingItem['imie_nazwisko'],
                $input['opis'] ?? $existingItem['opis'],
                $input['typ_dokumentu'] ?? $existingItem['typ_dokumentu'],
                $input['marka'] ?? $existingItem['marka'],
                $input['adres'] ?? $existingItem['adres'],
                $input['osoba_przyjmujaca'] ?? $existingItem['osoba_przyjmujaca'],
                $input['status'] ?? $existingItem['status'],
                $id
            ]);

            if ($result) {
                $stmt = $this->pdo->prepare("SELECT * FROM przedmioty WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                jsonResponse(['success' => true, 'message' => 'Przedmiot został zaktualizowany', 'data' => $item]);
            } else {
                jsonResponse(['success' => false, 'error' => 'Nie udało się zaktualizować przedmiotu'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd aktualizacji: ' . $e->getMessage()], 500);
        }
    }

    private function handleDelete() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            jsonResponse(['success' => false, 'error' => 'Brak ID przedmiotu'], 400);
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM przedmioty WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                jsonResponse(['success' => true, 'message' => 'Przedmiot został usunięty']);
            } else {
                jsonResponse(['success' => false, 'error' => 'Nie udało się usunąć przedmiotu'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd usuwania: ' . $e->getMessage()], 500);
        }
    }
}

// Uruchomienie API
$api = new ItemsAPI();
$api->handleRequest();
?>