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

class ExportAPI {
    private $pdo;

    public function __construct() {
        try {
            $db = Database::getInstance();
            $this->pdo = $db->getConnection();
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd połączenia z bazą: ' . $e->getMessage()], 500);
        }
    }

    public function handleRequest() {
        $format = $_GET['format'] ?? 'excel';
        $type = $_GET['type'] ?? 'full';

        try {
            switch ($format) {
                case 'excel':
                    $this->exportToExcel($type);
                    break;
                case 'print':
                    $this->generatePrintLabel($_GET['id'] ?? null);
                    break;
                default:
                    jsonResponse(['success' => false, 'error' => 'Nieobsługiwany format eksportu'], 400);
            }
        } catch (Exception $e) {
            error_log("Export Error: " . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Błąd eksportu: ' . $e->getMessage()], 500);
        }
    }

    private function exportToExcel($type) {
        try {
            $data = $this->getAllItems();

            $categories = [
                'Dokumenty' => [],
                'Portfele' => [],
                'Telefony' => [],
                'Elektronika' => [],
                'Plecaki i nerki' => [],
                'Klucze' => []
            ];

            foreach ($data as $item) {
                if (isset($categories[$item['kategoria']])) {
                    $categories[$item['kategoria']][] = $item;
                }
            }

            $filename = 'BRZ_export_' . date('Y-m-d_H-i-s') . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            echo $this->generateExcelContent($categories);
            exit;

        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd eksportu: ' . $e->getMessage()], 500);
        }
    }

    private function generateExcelContent($categories) {
        $html = "<html><head><meta charset='utf-8'></head><body>";

        foreach ($categories as $categoryName => $items) {
            if (empty($items)) continue;

            $html .= "<h2>$categoryName</h2>";
            $html .= "<table border='1'><tr>";

            switch ($categoryName) {
                case 'Dokumenty':
                    $html .= "<th>LP</th><th>Imię i nazwisko</th><th>Adres</th><th>Opis</th><th>Typ dokumentu</th><th>Status</th><th>Data przyjęcia</th>";
                    break;
                case 'Portfele':
                case 'Plecaki i nerki':
                    $html .= "<th>LP</th><th>Imię i nazwisko</th><th>Adres</th><th>Opis</th><th>Status</th><th>Data przyjęcia</th>";
                    break;
                case 'Telefony':
                    $html .= "<th>LP</th><th>Marka</th><th>Adres</th><th>Opis</th><th>Status</th><th>Data przyjęcia</th>";
                    break;
                case 'Elektronika':
                case 'Klucze':
                    $html .= "<th>LP</th><th>Adres</th><th>Opis</th><th>Status</th><th>Data przyjęcia</th>";
                    break;
            }
            $html .= "</tr>";

            foreach ($items as $item) {
                $html .= "<tr>";
                switch ($categoryName) {
                    case 'Dokumenty':
                        $html .= "<td>" . htmlspecialchars($item['lp']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['imie_nazwisko'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['adres'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['opis']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['typ_dokumentu'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['status']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['data_utworzenia']) . "</td>";
                        break;
                    case 'Portfele':
                    case 'Plecaki i nerki':
                        $html .= "<td>" . htmlspecialchars($item['lp']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['imie_nazwisko'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['adres'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['opis']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['status']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['data_utworzenia']) . "</td>";
                        break;
                    case 'Telefony':
                        $html .= "<td>" . htmlspecialchars($item['lp']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['marka'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['adres'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['opis']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['status']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['data_utworzenia']) . "</td>";
                        break;
                    case 'Elektronika':
                    case 'Klucze':
                        $html .= "<td>" . htmlspecialchars($item['lp']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['adres'] ?? '') . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['opis']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['status']) . "</td>";
                        $html .= "<td>" . htmlspecialchars($item['data_utworzenia']) . "</td>";
                        break;
                }
                $html .= "</tr>";
            }
            $html .= "</table><br><br>";
        }

        $html .= "</body></html>";
        return $html;
    }

    private function generatePrintLabel($id) {
        if (!$id) {
            jsonResponse(['success' => false, 'error' => 'Brak ID przedmiotu'], 400);
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM przedmioty WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                jsonResponse(['success' => false, 'error' => 'Przedmiot nie został znaleziony'], 404);
            }

            $labelHtml = $this->generateLabelHtml($item);

            header('Content-Type: text/html; charset=utf-8');
            echo $labelHtml;
            exit;

        } catch (Exception $e) {
            jsonResponse(['success' => false, 'error' => 'Błąd generowania etykiety: ' . $e->getMessage()], 500);
        }
    }

    private function generateLabelHtml($item) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Etykieta - ' . htmlspecialchars($item['lp']) . '</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: white;
            color: black;
        }
        .label {
            border: 2px solid black;
            padding: 15px;
            width: 400px;
            background: white;
        }
        .header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }
        .item-info {
            font-size: 14px;
            line-height: 1.5;
        }
        .item-info p {
            margin: 5px 0;
        }
        @media print {
            body { margin: 0; }
            .label { 
                width: auto; 
                border: 2px solid black;
                page-break-inside: avoid;
            }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="label">
        <div class="header">' . htmlspecialchars($item['lp']) . ' - ' . htmlspecialchars($item['kategoria']) . '</div>
        <div class="item-info">
            <p><strong>Opis:</strong> ' . htmlspecialchars($item['opis']) . '</p>';

        if ($item['imie_nazwisko']) {
            $html .= '<p><strong>Właściciel:</strong> ' . htmlspecialchars($item['imie_nazwisko']) . '</p>';
        }
        if ($item['adres']) {
            $html .= '<p><strong>Adres:</strong> ' . htmlspecialchars($item['adres']) . '</p>';
        }
        if ($item['marka']) {
            $html .= '<p><strong>Marka:</strong> ' . htmlspecialchars($item['marka']) . '</p>';
        }

        $html .= '<p><strong>Data przyjęcia:</strong> ' . date('Y-m-d', strtotime($item['data_utworzenia'])) . '</p>';
        $html .= '<p><strong>Osoba przyjmująca:</strong> ' . htmlspecialchars($item['osoba_przyjmujaca']) . '</p>';

        $html .= '
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    private function getAllItems() {
        $stmt = $this->pdo->prepare("SELECT * FROM przedmioty ORDER BY kategoria, lp");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

$api = new ExportAPI();
$api->handleRequest();
?>