/* app.js - BRZ POL'AND'ROCK 2025 - KOMPLETNA WERSJA Z BACKUP */

// ---- CONFIGURATION ---- //
const API_BASE = window.location.pathname.includes('/brz/') ? 'api' : 'brz/api';
let items = [];
let autoRefreshInterval;
let autoBackupInterval;
let refreshCountdown = 10;

// Dane konfiguracyjne
const categoriesMap = {
    "Dokumenty": {prefix:"D", required:["imie_nazwisko","opis","typ_dokumentu"], optional:["address"]},
    "Portfele": {prefix:"P", required:["imie_nazwisko","opis","typ_dokumentu"], optional:["address"]},
    "Plecaki i nerki": {prefix:"PL", required:["imie_nazwisko","opis","typ_dokumentu"], optional:["address"]},
    "Telefony": {prefix:"T", required:["marka","opis"], optional:["imie_nazwisko","address"]},
    "Elektronika": {prefix:"E", required:["opis"], optional:["imie_nazwisko","address"]},
    "Klucze": {prefix:"K", required:["opis"], optional:["imie_nazwisko","address"]}
};

// ALFABETYCZNIE POSORTOWANA LISTA 20 MAREK TELEFONÓW W POLSCE 2025
const phoneBrands = ["Apple","ASUS","CMF","Google","Honor","Huawei","Infinix","Motorola","Nokia","Nothing","OnePlus","OPPO","POCO","Realme","Redmi","Samsung","Sony","Tecno","Vivo","Xiaomi","Inny"];

const staffMembers = ["Adam Pajęcki","Jarosław Bawelski","Karolina Rentel","Katarzyna Młodzińska","Magdalena Kiraga","Marcin Kotarski","Mariusz Holewski","Mikołaj Matusiak","Nicole Kmieć","Olga Sołtysiak","Tomasz Skrzypaszek"];

// ---- UTILITY FUNCTIONS ---- //
const $ = (selector, scope = document) => scope.querySelector(selector);
const $all = (selector, scope = document) => [...scope.querySelectorAll(selector)];

function showNotification(type, title, message) {
    const notifications = $("#notifications");
    const notification = document.createElement("div");
    notification.className = `notification notification--${type}`;
    notification.innerHTML = `
        <div class="notification__title">${title}</div>
        <div class="notification__message">${message}</div>
    `;
    notifications.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}

function formatDate(dateObj) {
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// ---- AUTO NUMBERING FUNCTION ---- //
async function generateNextLP(category) {
    const mapping = categoriesMap[category];
    if (!mapping) return '';

    try {
        const response = await apiCall(`${API_BASE}/items.php?category=${encodeURIComponent(category)}`);
        if (!response.success) {
            console.warn('Could not fetch items for category:', category);
            return mapping.prefix + '1';
        }

        const categoryItems = response.data || [];
        const prefix = mapping.prefix;

        let maxNumber = 0;
        categoryItems.forEach(item => {
            if (item.lp && item.lp.startsWith(prefix)) {
                const numPart = item.lp.substring(prefix.length);
                const num = parseInt(numPart, 10);
                if (!isNaN(num) && num > maxNumber) {
                    maxNumber = num;
                }
            }
        });

        return prefix + (maxNumber + 1);
    } catch (error) {
        console.warn('Error generating next LP:', error);
        return mapping.prefix + '1';
    }
}

// ---- API COMMUNICATION ---- //
async function apiCall(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API Call Error:', error);
        showNotification('error', 'Błąd połączenia', 'Nie można połączyć się z serwerem');
        throw error;
    }
}

// ---- CRUD OPERATIONS ---- //
async function loadItems() {
    try {
        const response = await apiCall(`${API_BASE}/items.php`);
        if (response.success) {
            items = response.data;
            updateDashboard();
            handleSearch(); // Refresh search results with current filters
        } else {
            showNotification('error', 'Błąd', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można załadować danych');
    }
}

async function addItem(itemData) {
    try {
        const response = await apiCall(`${API_BASE}/items.php`, {
            method: 'POST',
            body: JSON.stringify(itemData)
        });

        if (response.success) {
            showNotification('success', 'Sukces', 'Przedmiot został dodany do bazy');
            await loadItems();

            if (response.data && response.data.id) {
                window.open(`${API_BASE}/export.php?format=print&id=${response.data.id}`, '_blank');
            }

            return true;
        } else {
            showNotification('error', 'Błąd', response.error);
            return false;
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można dodać przedmiotu');
        return false;
    }
}

async function updateItem(id, itemData) {
    try {
        const response = await apiCall(`${API_BASE}/items.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(itemData)
        });

        if (response.success) {
            showNotification('success', 'Sukces', 'Przedmiot został zaktualizowany');
            await loadItems();
            return true;
        } else {
            showNotification('error', 'Błąd', response.error);
            return false;
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można zaktualizować przedmiotu');
        return false;
    }
}

async function deleteItem(id) {
    if (!confirm('Czy na pewno chcesz usunąć ten przedmiot?')) return;

    try {
        const response = await apiCall(`${API_BASE}/items.php?id=${id}`, {
            method: 'DELETE'
        });

        if (response.success) {
            showNotification('success', 'Sukces', 'Przedmiot został usunięty');
            await loadItems();
        } else {
            showNotification('error', 'Błąd', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można usunąć przedmiotu');
    }
}

// ---- BACKUP FUNCTIONS ---- //
async function createBackup() {
    try {
        showNotification('info', 'Backup', 'Tworzenie kopii zapasowej...');

        const response = await apiCall(`${API_BASE}/backup.php?action=create`, {
            method: 'POST'
        });

        if (response.success) {
            showNotification('success', 'Backup', `Kopia zapasowa utworzona: ${response.filename}`);
            await loadBackupList();
        } else {
            showNotification('error', 'Błąd backup', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można utworzyć kopii zapasowej');
    }
}

async function loadBackupList() {
    try {
        const response = await apiCall(`${API_BASE}/backup.php?action=list`);

        if (response.success) {
            const backupsList = $('#backupsList');
            if (!backupsList) return;

            backupsList.innerHTML = '';

            if (response.backups.length === 0) {
                backupsList.innerHTML = '<p style="text-align: center; color: #d1d5db; padding: 20px;">Brak dostępnych kopii zapasowych</p>';
                return;
            }

            response.backups.forEach(backup => {
                const backupItem = document.createElement('div');
                backupItem.className = 'backup-item';
                backupItem.innerHTML = `
                    <div class="backup-info">
                        <div class="backup-filename">${backup.filename}</div>
                        <div class="backup-details">Utworzono: ${backup.created} | Rozmiar: ${Math.round(backup.size/1024)} KB</div>
                    </div>
                    <div class="backup-actions">
                        <button class="btn btn--sm btn--outline" onclick="restoreBackup('${backup.filename}')">Przywróć</button>
                        <button class="btn btn--sm btn--warning" onclick="deleteBackup('${backup.filename}')">Usuń</button>
                    </div>
                `;
                backupsList.appendChild(backupItem);
            });
        } else {
            showNotification('error', 'Błąd', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można załadować listy kopii zapasowych');
    }
}

async function restoreBackup(filename) {
    if (!confirm(`Czy na pewno chcesz przywrócić dane z kopii zapasowej "${filename}"?\n\nTo zastąpi wszystkie obecne dane w bazie!`)) {
        return;
    }

    try {
        showNotification('info', 'Backup', 'Przywracanie danych...');

        const response = await apiCall(`${API_BASE}/backup.php?action=restore`, {
            method: 'POST',
            body: JSON.stringify({ filename: filename })
        });

        if (response.success) {
            showNotification('success', 'Backup', `Przywrócono ${response.records} rekordów z kopii zapasowej`);
            await loadItems();
        } else {
            showNotification('error', 'Błąd backup', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można przywrócić kopii zapasowej');
    }
}

async function deleteBackup(filename) {
    if (!confirm(`Czy na pewno chcesz usunąć kopię zapasową "${filename}"?`)) {
        return;
    }

    try {
        const response = await apiCall(`${API_BASE}/backup.php?action=delete`, {
            method: 'POST',
            body: JSON.stringify({ filename: filename })
        });

        if (response.success) {
            showNotification('success', 'Backup', 'Kopia zapasowa została usunięta');
            await loadBackupList();
        } else {
            showNotification('error', 'Błąd backup', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można usunąć kopii zapasowej');
    }
}

async function autoBackup() {
    try {
        const response = await apiCall(`${API_BASE}/backup.php?action=auto`);

        if (response.success) {
            console.log(`Auto backup: ${response.message}`);
        } else {
            console.error('Auto backup failed:', response.error);
        }
    } catch (error) {
        console.error('Auto backup error:', error);
    }
}

// ---- UI RENDERING ---- //
function updateDashboard() {
    $('#totalItems').textContent = items.length;
    $('#foundItems').textContent = items.filter(i => i.status === 'Znaleziony').length;
    $('#returnedItems').textContent = items.filter(i => i.status === 'Wydany').length;

    const grid = $('#categoryStats');
    if (grid) {
        grid.innerHTML = '';
        Object.keys(categoriesMap).forEach(cat => {
            const count = items.filter(i => i.kategoria === cat).length;
            const div = document.createElement('div');
            div.className = 'category-stat';
            div.innerHTML = `
                <span class="category-stat__number">${count}</span>
                <span class="category-stat__label">${cat}</span>
            `;
            grid.appendChild(div);
        });
    }

    const recent = $('#recentItems');
    if (recent) {
        recent.innerHTML = '';
        const last5 = [...items].slice(-5).reverse();
        if (last5.length === 0) {
            recent.innerHTML = '<p style="text-align: center; color: #d1d5db; padding: 20px;">Brak przedmiotów</p>';
            return;
        }

        last5.forEach(it => {
            const div = document.createElement('div');
            div.className = 'recent-item';
            div.innerHTML = `
                <div class="recent-item__info">
                    <div class="recent-item__number">${it.lp} - ${it.kategoria}</div>
                    <div class="recent-item__desc">${it.opis}</div>
                </div>
                <span class="recent-item__status ${it.status === 'Znaleziony' ? 'recent-item__status--found' : 'recent-item__status--returned'}">${it.status}</span>
            `;
            recent.appendChild(div);
        });
    }
}

function renderSearchResults(filteredItems) {
    const tbody = $('#searchResultsBody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (filteredItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px; color: #d1d5db;">Nie znaleziono przedmiotów spełniających kryteria</td></tr>';
        return;
    }

    filteredItems.forEach((it, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${it.lp}</td>
            <td>${it.kategoria}</td>
            <td>${it.imie_nazwisko || ''}</td>
            <td>${it.adres || ''}</td>
            <td>${it.opis}</td>
            <td>${it.marka || ''}</td>
            <td>
                <span class="status-badge ${it.status === 'Znaleziony' ? 'status-badge--found' : 'status-badge--returned'}" 
                      data-action="toggle" data-id="${it.id}" 
                      title="Kliknij aby zmienić status">
                    ${it.status}
                </span>
            </td>
            <td>${it.data_utworzenia ? it.data_utworzenia.slice(0, 10) : formatDate(new Date())}</td>
            <td>${it.osoba_przyjmujaca}</td>
            <td>
                <button class="btn btn--sm btn--outline action-btn" data-action="print" data-id="${it.id}">Drukuj</button>
                <button class="btn btn--sm btn--warning action-btn" data-action="delete" data-id="${it.id}">Usuń</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ---- EVENT HANDLERS ---- //
function initializeEventHandlers() {
    $all('.nav__tab').forEach(btn => {
        btn.addEventListener('click', () => {
            $all('.nav__tab').forEach(b => b.classList.remove('nav__tab--active'));
            btn.classList.add('nav__tab--active');
            const tabName = btn.dataset.tab;
            $all('.tab-content').forEach(tab => tab.classList.remove('tab-content--active'));
            const targetTab = $('#' + tabName);
            if (targetTab) {
                targetTab.classList.add('tab-content--active');
                if (tabName === 'export') {
                    loadBackupList();
                } else if (tabName === 'search') {
                    handleSearch();
                }
            }
        });
    });

    const categorySelect = $('#category');
    const docTypeGroup = $('#documentTypeGroup');
    const phoneBrandGroup = $('#phoneBrandGroup');
    const fullNameField = $('#fullName');
    const itemNumberField = $('#itemNumber');

    if (categorySelect && docTypeGroup && phoneBrandGroup && fullNameField) {
        categorySelect.addEventListener('change', async () => {
            const cat = categorySelect.value;
            const mapping = categoriesMap[cat];

            if (mapping) {
                const showDocType = mapping.required.includes('typ_dokumentu');
                docTypeGroup.classList.toggle('hidden', !showDocType);
                const docTypeField = $('#documentType');
                if (showDocType) docTypeField.setAttribute('required', '');
                else docTypeField.removeAttribute('required');

                phoneBrandGroup.classList.toggle('hidden', cat !== 'Telefony');

                if (mapping.required.includes('imie_nazwisko')) {
                    fullNameField.setAttribute('required', '');
                    const label = fullNameField.parentElement.querySelector('.form-label');
                    if (label) label.innerHTML = 'Imię i nazwisko *';
                } else {
                    fullNameField.removeAttribute('required');
                    const label = fullNameField.parentElement.querySelector('.form-label');
                    if (label) label.innerHTML = 'Imię i nazwisko';
                }

                if (cat && itemNumberField) {
                    const nextLP = await generateNextLP(cat);
                    itemNumberField.value = nextLP;
                }
            }
        });
    }

    const addForm = $('#addItemForm');
    if (addForm) {
        addForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newItem = collectFormData(e.target);
            if (newItem) {
                const success = await addItem(newItem);
                if (success) {
                    e.target.reset();
                    if (docTypeGroup) docTypeGroup.classList.add('hidden');
                    if (phoneBrandGroup) phoneBrandGroup.classList.add('hidden');
                    if (fullNameField) {
                        fullNameField.removeAttribute('required');
                        const label = fullNameField.parentElement.querySelector('.form-label');
                        if (label) label.innerHTML = 'Imię i nazwisko';
                    }
                    if (itemNumberField) itemNumberField.value = '';
                }
            }
        });
    }

    const searchResultsTable = $('#searchResultsBody');
    if (searchResultsTable) {
        searchResultsTable.addEventListener('click', async (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            const action = target.dataset.action;
            const id = target.dataset.id;

            switch (action) {
                case 'toggle':
                    const item = items.find(i => i.id == id);
                    if (item) {
                        const newStatus = item.status === 'Znaleziony' ? 'Wydany' : 'Znaleziony';
                        await updateItem(id, { status: newStatus });
                    }
                    break;
                case 'print':
                    const printItem = items.find(i => i.id == id);
                    if (printItem) openPrintModal(printItem);
                    break;
                case 'delete':
                    await deleteItem(id);
                    break;
            }
        });
    }

    $all('#searchLP,#searchName,#searchCategory,#searchBrand,#searchStatus').forEach(input => {
        if (input) {
            input.addEventListener('input', handleSearch);
            input.addEventListener('change', handleSearch);
        }
    });

    const clearSearchBtn = $('#clearSearch');
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            const searchLP = $('#searchLP');
            const searchName = $('#searchName');
            const searchCategory = $('#searchCategory');
            const searchBrand = $('#searchBrand');  
            const accordingStatus = $('#searchStatus');

            if (searchLP) searchLP.value = '';
            if (searchName) searchName.value = '';
            if (searchCategory) searchCategory.value = '';
            if (searchBrand) searchBrand.value = '';
            if (searchStatus) searchStatus.value = '';

            handleSearch();
        });
    }

    const exportExcelBtn = $('#exportExcel');
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', async () => {
            try {
                window.open(`${API_BASE}/export.php?format=excel`, '_blank');
            } catch (error) {
                showNotification('error', 'Błąd', 'Nie można wyeksportować danych');
            }
        });
    }

    const createBackupBtn = $('#createBackup');
    if (createBackupBtn) {
        createBackupBtn.addEventListener('click', createBackup);
    }

    const refreshBackupsBtn = $('#refreshBackups');
    if (refreshBackupsBtn) {
        refreshBackupsBtn.addEventListener('click', loadBackupList);
    }

    const closePrintModal = $('#closePrintModal');
    if (closePrintModal) {
        closePrintModal.addEventListener('click', () => {
            const modal = $('#printModal');
            if (modal) modal.classList.remove('modal--show');
        });
    }

    const cancelPrint = $('#cancelPrint');
    if (cancelPrint) {
        cancelPrint.addEventListener('click', () => {
            const modal = $('#printModal');
            if (modal) modal.classList.remove('modal--show');
        });
    }

    const printButton = $('#printButton');
    if (printButton) {
        printButton.addEventListener('click', () => {
            window.print();
            const modal = $('#printModal');
            if (modal) modal.classList.remove('modal--show');
        });
    }

    const printModal = $('#printModal');
    if (printModal) {
        printModal.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal__overlay')) {
                printModal.classList.remove('modal--show');
            }
        });
    }
}

function collectFormData(form) {
    const categorySelect = $('#category');
    const category = categorySelect ? categorySelect.value : '';

    if (!category) {
        showNotification('error', 'Błąd', 'Wybierz kategorię.');
        return null;
    }

    const mapping = categoriesMap[category];
    const itemNumberField = $('#itemNumber');
    const itemNumber = itemNumberField ? itemNumberField.value.trim() : '';

    if (!itemNumber) {
        showNotification('error', 'Błąd', 'Numer przedmiotu jest wymagany.');
        return null;
    }

    // Validate that the item number starts with the correct prefix
    if (!itemNumber.toUpperCase().startsWith(mapping.prefix)) {
        showNotification('error', 'Błąd', `Numer przedmiotu musi zaczynać się od prefiksu ${mapping.prefix}.`);
        return null;
    }

    // Validate that the item number contains digits after the prefix
    const numericPart = itemNumber.substring(mapping.prefix.length);
    if (!numericPart || !/^\d+$/.test(numericPart)) {
        showNotification('error', 'Błąd', `Numer przedmiotu musi zawierać cyfry po prefiksie ${mapping.prefix}.`);
        return null;
    }

    const fullNameField = $('#fullName');
    const fullName = fullNameField ? fullNameField.value.trim() : '';

    const descriptionField = $('#description');
    const description = descriptionField ? descriptionField.value.trim() : '';

    const receivingPersonField = $('#receivingPerson');
    const receivingPerson = receivingPersonField ? receivingPersonField.value : '';

    let errors = [];
    mapping.required.forEach(field => {
        switch (field) {
            case 'imie_nazwisko':
                if (!fullName) errors.push('Imię i nazwisko jest wymagane dla tej kategorii.');
                break;
            case 'opis':
                if (!description) errors.push('Opis jest wymagany.');
                break;
            case 'marka':
                const phoneBrandField = $('#phoneBrand');
                if (!phoneBrandField || !phoneBrandField.value) errors.push('Marka telefonu jest wymagana.');
                break;
            case 'typ_dokumentu':
                const docTypeField = $('#documentType');
                if (!docTypeField || !docTypeField.value) errors.push('Typ dokumentu jest wymagany.');
                break;
        }
    });

    if (!receivingPerson) errors.push('Wybierz osobę przyjmującą.');

    if (errors.length) {
        showNotification('error', 'Błąd', errors.join(' '));
        return null;
    }

    const newItem = {
        lp: itemNumber.toUpperCase(),
        kategoria: category,
        opis: description,
        status: $('#status') ? $('#status').value : 'Znaleziony',
        osoba_przyjmujaca: receivingPerson
    };

    if (fullName) newItem.imie_nazwisko = fullName;

    const addressField = $('#address');
    if (addressField && addressField.value.trim()) {
        newItem.adres = addressField.value.trim();
    }

    const docTypeField = $('#documentType');
    if (docTypeField && docTypeField.value && mapping.required.includes('typ_dokumentu')) {
        newItem.typ_dokumentu = docTypeField.value;
    }

    const phoneBrandField = $('#phoneBrand');
    if (phoneBrandField && phoneBrandField.value && category === 'Telefony') {
        newItem.marka = phoneBrandField.value;
    }

    return newItem;
}

async function handleSearch() {
    const searchLP = $('#searchLP') ? $('#searchLP').value.trim() : '';
    const searchName = $('#searchName') ? $('#searchName').value.trim() : '';
    const searchCategory = $('#searchCategory') ? $('#searchCategory').value : '';
    const searchBrand = $('#searchBrand') ? $('#searchBrand').value : '';
    const searchStatus = $('#searchStatus') ? $('#searchStatus').value : '';

    let query = '';
    if (searchLP) query += `&lp=${encodeURIComponent(searchLP)}`;
    if (searchName) query += `&name=${encodeURIComponent(searchName)}`;
    if (searchCategory) query += `&category=${encodeURIComponent(searchCategory)}`;
    if (searchBrand) query += `&brand=${encodeURIComponent(searchBrand)}`;
    if (searchStatus) query += `&status=${encodeURIComponent(searchStatus)}`;
    if (query) query = '?' + query.substring(1);

    try {
        const response = await apiCall(`${API_BASE}/items.php${query}`);
        if (response.success) {
            const filteredItems = response.data;
            renderSearchResults(filteredItems);
        } else {
            showNotification('error', 'Błąd', response.error);
        }
    } catch (error) {
        showNotification('error', 'Błąd', 'Nie można wykonać wyszukiwania');
    }
}

function openPrintModal(item) {
    const printLabel = $('#printLabel');
    if (printLabel) {
        printLabel.innerHTML = `
            <h4>${item.lp} - ${item.kategoria}</h4>
            <p><strong>Opis:</strong> ${item.opis}</p>
            ${item.imie_nazwisko ? `<p><strong>Właściciel:</strong> ${item.imie_nazwisko}</p>` : ''}
            ${item.adres ? `<p><strong>Adres:</strong> ${item.adres}</p>` : ''}
            ${item.marka ? `<p><strong>Marka:</strong> ${item.marka}</p>` : ''}
            <p><strong>Data przyjęcia:</strong> ${item.data_utworzenia ? item.data_utworzenia.slice(0, 10) : formatDate(new Date())}</p>
            <p><strong>Osoba przyjmująca:</strong> ${item.osoba_przyjmujaca}</p>
        `;
    }

    const modal = $('#printModal');
    if (modal) modal.classList.add('modal--show');
}

function populateDropdowns() {
    const receivingPersonSelect = $('#receivingPerson');
    if (receivingPersonSelect) {
        receivingPersonSelect.innerHTML = '<option value="">Wybierz osobę</option>';
        staffMembers.forEach(person => {
            const option = document.createElement('option');
            option.value = person;
            option.textContent = person;
            receivingPersonSelect.appendChild(option);
        });
    }

    const phoneBrandSelect = $('#phoneBrand');
    if (phoneBrandSelect) {
        phoneBrandSelect.innerHTML = '<option value="">Wybierz markę</option>';
        phoneBrands.forEach(brand => {
            const option = document.createElement('option');
            option.value = brand;
            option.textContent = brand;
            phoneBrandSelect.appendChild(option);
        });
    }

    const searchBrandSelect = $('#searchBrand');
    if (searchBrandSelect) {
        searchBrandSelect.innerHTML = '<option value="">Wszystkie marki</option>';
        phoneBrands.forEach(brand => {
            const option = document.createElement('option');
            option.value = brand;
            option.textContent = brand;
            searchBrandSelect.appendChild(option);
        });
    }

    const searchCategorySelect = $('#searchCategory');
    if (searchCategorySelect) {
        searchCategorySelect.innerHTML = '<option value="">Wszystkie kategorie</option>';
        Object.keys(categoriesMap).forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            searchCategorySelect.appendChild(option);
        });
    }
}

// ---- AUTO REFRESH ---- //
function startAutoRefresh() {
    autoRefreshInterval = setInterval(async () => {
        refreshCountdown--;
        const refreshDisplay = document.getElementById('refreshCounter') || createRefreshCounter();
        refreshDisplay.textContent = `Odświeżenie za: ${refreshCountdown}s`;

        if (refreshCountdown <= 0) {
            await loadItems();
            refreshCountdown = 10;
            refreshDisplay.textContent = 'Dane odświeżone';
            setTimeout(() => {
                refreshDisplay.textContent = `Odświeżenie za: ${refreshCountdown}s`;
            }, 1000);
        }
    }, 1000);
}

function createRefreshCounter() {
    const counter = document.createElement('div');
    counter.id = 'refreshCounter';
    counter.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: var(--color-surface); padding: 8px 12px; border-radius: 6px; font-size: 12px; border: 1px solid var(--color-border); z-index: 1000;';
    document.body.appendChild(counter);
    return counter;
}

// ---- AUTO BACKUP TIMER (CO 10 MINUT) ---- //
function startAutoBackup() {
    autoBackupInterval = setInterval(() => {
        autoBackup();
    }, 600000); // 10 minut

    console.log('Auto backup timer started - backup co 10 minut');
}

// ---- INITIALIZATION ---- //
document.addEventListener('DOMContentLoaded', async function() {
    populateDropdowns();
    initializeEventHandlers();

    try {
        const testResponse = await apiCall('config.php?test');
        if (testResponse.success) {
            console.log('Połączenie z bazą OK:', testResponse.message);
            showNotification('success', 'Połączono', 'Połączenie z bazą danych nawiązane');
        } else {
            throw new Error(testResponse.message);
        }
    } catch (error) {
        showNotification('error', 'Błąd bazy', 'Nie można połączyć się z bazą danych. Sprawdź konfigurację.');
        console.error('Database connection test failed:', error);
        return;
    }

    await loadItems();
    startAutoRefresh();
    startAutoBackup();
});

// Global error handler
window.addEventListener('unhandledrejection', event => {
    console.error('Unhandled promise rejection:', event.reason);
    showNotification('error', 'Błąd aplikacji', 'Wystąpił nieoczekiwany błąd');
});

// Global functions for inline onclick handlers
window.restoreBackup = restoreBackup;
window.deleteBackup = deleteBackup;