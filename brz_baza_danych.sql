-- 
-- Struktura bazy danych dla BRZ POL'AND'ROCK 2025
-- Data utworzenia: 2025-07-19
--

-- Utworzenie bazy danych
CREATE DATABASE IF NOT EXISTS brz_polandrock2025 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Użycie bazy danych
USE brz_polandrock2025;

-- Utworzenie tabeli przedmiotów
CREATE TABLE IF NOT EXISTS przedmioty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lp VARCHAR(10) NOT NULL UNIQUE COMMENT 'Numer LP (D1, P2, T15 itp.)',
    kategoria VARCHAR(50) NOT NULL COMMENT 'Dokumenty, Portfele, Telefony, Elektronika, Plecaki i nerki, Klucze',
    imie_nazwisko VARCHAR(100) COMMENT 'Imię i nazwisko właściciela (wymagane dla Dokumenty/Portfele/Plecaki)',
    opis TEXT NOT NULL COMMENT 'Szczegółowy opis przedmiotu',
    typ_dokumentu VARCHAR(50) COMMENT 'Dowód osobisty, Prawo jazdy, Legitymacja, Karta płatnicza, Inne',
    marka VARCHAR(50) COMMENT 'Marka telefonu (tylko dla kategorii Telefony)',
    adres TEXT COMMENT 'Adres właściciela (opcjonalnie)',
    osoba_przyjmujaca VARCHAR(100) NOT NULL COMMENT 'Osoba z zespołu która przyjęła przedmiot',
    status ENUM('Znaleziony', 'Wydany') NOT NULL DEFAULT 'Znaleziony' COMMENT 'Status przedmiotu',
    data_utworzenia TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data dodania do systemu',
    data_modyfikacji TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data ostatniej modyfikacji',
    
    -- Indeksy dla poprawy wydajności
    INDEX idx_kategoria (kategoria),
    INDEX idx_status (status),
    INDEX idx_imie_nazwisko (imie_nazwisko),
    INDEX idx_marka (marka),
    INDEX idx_lp (lp),
    INDEX idx_data_utworzenia (data_utworzenia)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela przedmiotów znalezionych na festiwalu';

-- Utworzenie użytkownika dla aplikacji
CREATE USER IF NOT EXISTS 'brzuser'@'%' IDENTIFIED BY 'BRZPolandRock2025!';

-- Przyznanie uprawnień
GRANT ALL PRIVILEGES ON brz_polandrock2025.* TO 'brzuser'@'%';
FLUSH PRIVILEGES;

-- Wstawienie przykładowych danych testowych
INSERT IGNORE INTO przedmioty (lp, kategoria, imie_nazwisko, opis, typ_dokumentu, marka, adres, osoba_przyjmujaca, status) VALUES 
('D1', 'Dokumenty', 'Jan Kowalski', 'Dowód osobisty - seria ABC123456', 'Dowód osobisty', NULL, 'Warszawa, ul. Kwiatowa 5/10', 'Adam Pajęcki', 'Znaleziony'),
('D2', 'Dokumenty', 'Anna Nowak', 'Prawo jazdy kat. B', 'Prawo jazdy', NULL, 'Kraków, ul. Słoneczna 15', 'Karolina Rentel', 'Wydany'),
('P1', 'Portfele', 'Piotr Zieliński', 'Skórzany portfel czarny z dokumentami', NULL, NULL, 'Gdańsk, ul. Morska 20', 'Magdalena Kiraga', 'Znaleziony'),
('PL1', 'Plecaki i nerki', 'Maria Kowalczyk', 'Plecak sportowy niebieski Adidas', NULL, NULL, NULL, 'Tomasz Skrzypaszek', 'Znaleziony'),
('T1', 'Telefony', NULL, 'iPhone 14 Pro Max 256GB Space Black', NULL, 'Apple', NULL, 'Mikołaj Matusiak', 'Znaleziony'),
('T2', 'Telefony', NULL, 'Samsung Galaxy S23 Ultra', NULL, 'Samsung', NULL, 'Nicole Kmieć', 'Wydany'),
('E1', 'Elektronika', NULL, 'Słuchawki bezprzewodowe Sony WH-1000XM4', NULL, NULL, NULL, 'Mariusz Holewski', 'Znaleziony'),
('K1', 'Klucze', NULL, 'Pęk kluczy z breloczkiem - logo BMW', NULL, NULL, NULL, 'Olga Sołtysiak', 'Znaleziony');

-- Sprawdzenie czy dane zostały wstawione
SELECT 'Baza danych utworzona pomyślnie!' as status;
SELECT COUNT(*) as 'Liczba_przedmiotów_testowych' FROM przedmioty;

-- Wyświetlenie struktury tabeli
DESCRIBE przedmioty;