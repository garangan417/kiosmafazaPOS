<?php
// 1. Auto-Scan File SQLite di Folder Saat Ini
$targetFolder = __DIR__;
$extensions = ['db', 'sqlite', 'sqlite3', 'db3'];
$foundDatabases = [];

foreach ($extensions as $ext) {
    $files = glob($targetFolder . "/*." . $ext);
    if ($files !== false) {
        foreach ($files as $file) {
            $foundDatabases[] = basename($file);
        }
    }
}
$foundDatabases = array_values(array_unique($foundDatabases));

// API ENDPOINT UNTUK AJAX
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $reqDb = basename($_GET['api_db'] ?? '');
    $reqFile = $targetFolder . '/' . $reqDb;

    if (!$reqDb || !file_exists($reqFile)) {
        echo json_encode([]);
        exit;
    }

    try {
        $realPath = realpath($reqFile);
        $apiPdo = new PDO('sqlite:' . $realPath);
        $apiPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $apiPdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
        $apiPdo->exec("PRAGMA busy_timeout = 5000;");

        if ($_GET['api'] === 'get_tables') {
            $stmt = $apiPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $data = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $apiPdo = null;
            echo json_encode(array_values($data) ?: []);
            exit;
        }

        if ($_GET['api'] === 'get_cols' && isset($_GET['api_table'])) {
            $tbl = $_GET['api_table'];
            $stmt = $apiPdo->query("PRAGMA table_info(\"$tbl\")");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $apiPdo = null;
            echo json_encode(array_values($data) ?: []);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode([]);
        exit;
    }
}

// 2. Pilih Database Aktif (Target)
$activeDb = $_GET['db'] ?? ($foundDatabases[0] ?? null);
$dbFile = $activeDb ? $targetFolder . '/' . $activeDb : null;

$pdo = null;
$msg = '';
$msgType = 'msg';
$tables = [];

// 3. Inisialisasi Database Utama & Process Form Submit
if ($dbFile && file_exists($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . realpath($dbFile));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
        $pdo->exec("PRAGMA busy_timeout = 10000;");

        $action = $_GET['action'] ?? 'tables';
        $table  = $_GET['table'] ?? '';

        // MANAJEMEN STRUKTUR: TAMBAH KOLOM
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_column']) && $table) {
            $colName = trim($_POST['new_col_name'] ?? '');
            $colType = $_POST['new_col_type'] ?? 'TEXT';
            $defaultVal = trim($_POST['new_col_default'] ?? '');

            if ($colName !== '') {
                $sql = "ALTER TABLE \"$table\" ADD COLUMN \"$colName\" $colType";
                if ($defaultVal !== '') {
                    $sql .= " DEFAULT " . $pdo->quote($defaultVal);
                }
                $pdo->exec($sql);
                $msg = "Kolom '$colName' ($colType) berhasil ditambahkan!";
                $action = 'structure';
            }
        }

        // MANAJEMEN STRUKTUR: UBAH NAMA KOLOM
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_column']) && $table) {
            $oldCol = $_POST['old_col_name'] ?? '';
            $newCol = trim($_POST['new_col_name'] ?? '');

            if ($oldCol && $newCol) {
                $sql = "ALTER TABLE \"$table\" RENAME COLUMN \"$oldCol\" TO \"$newCol\"";
                $pdo->exec($sql);
                $msg = "Nama kolom '$oldCol' berhasil diubah menjadi '$newCol'!";
                $action = 'structure';
            }
        }

        // PROSES COPY DATA ANTA DB (SAFE TRANSACTION WITHOUT ATTACH DATABASE)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_data'])) {
            $sourceDb     = $_POST['source_db'] ?? '';
            $sourceTable  = $_POST['source_table'] ?? '';
            $targetTable  = $_POST['target_table'] ?? '';
            $mapTypes     = $_POST['map_type'] ?? [];
            $mapCols      = $_POST['map_col'] ?? [];
            $mapValues    = $_POST['map_value'] ?? [];
            $onConflict   = $_POST['on_conflict'] ?? 'IGNORE';

            $sourceDbPath = $targetFolder . '/' . $sourceDb;

            if ($sourceDb && $sourceTable && $targetTable && file_exists($sourceDbPath)) {
                // 1. Ambil MAX ID awal dari tabel tujuan
                $maxId = 0;
                try {
                    $maxIdStmt = $pdo->query("SELECT MAX(CAST(id AS INTEGER)) FROM \"$targetTable\"");
                    $maxId = (int) $maxIdStmt->fetchColumn();
                } catch (Exception $e) {
                    $maxId = 0;
                }

                // 2. Baca data dari Database Asal menggunakan koneksi terpisah (bebas locking)
                $srcPdo = new PDO('sqlite:' . realpath($sourceDbPath));
                $srcPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $srcPdo->setAttribute(PDO::ATTR_TIMEOUT, 5);

                $srcStmt = $srcPdo->query("SELECT * FROM \"$sourceTable\"");
                $sourceRows = $srcStmt->fetchAll(PDO::FETCH_ASSOC);
                $srcPdo = null; // Tutup koneksi db asal secepat mungkin

                if (!empty($sourceRows)) {
                    $targetFields = [];
                    foreach ($mapTypes as $tgtCol => $type) {
                        if ($type !== 'ignore') {
                            $targetFields[] = $tgtCol;
                        }
                    }

                    if (!empty($targetFields)) {
                        $insertKeyword = "INSERT";
                        if ($onConflict === 'IGNORE') {
                            $insertKeyword = "INSERT OR IGNORE";
                        } elseif ($onConflict === 'REPLACE') {
                            $insertKeyword = "INSERT OR REPLACE";
                        }

                        $fieldList = implode(', ', array_map(fn($f) => "\"$f\"", $targetFields));
                        $placeholders = implode(', ', array_fill(0, count($targetFields), '?'));
                        $sqlInsert = "$insertKeyword INTO \"$targetTable\" ($fieldList) VALUES ($placeholders)";

                        // 3. Masukkan data dengan Transaction ke DB Target
                        $pdo->beginTransaction();
                        $insertStmt = $pdo->prepare($sqlInsert);
                        $insertedCount = 0;
                        $autoIdCounter = $maxId;

                        foreach ($sourceRows as $row) {
                            $rowValues = [];
                            $autoIdCounter++;

                            foreach ($targetFields as $tgtCol) {
                                $type = $mapTypes[$tgtCol];

                                if ($type === 'col') {
                                    $srcColName = $mapCols[$tgtCol] ?? '';
                                    $rowValues[] = $row[$srcColName] ?? null;
                                } elseif ($type === 'auto_id') {
                                    $rowValues[] = $autoIdCounter;
                                } elseif ($type === 'custom') {
                                    $val = $mapValues[$tgtCol] ?? '';
                                    $rowValues[] = ($val === '' || strtoupper($val) === 'NULL') ? null : $val;
                                }
                            }

                            $insertStmt->execute($rowValues);
                            $insertedCount++;
                        }

                        $pdo->commit();
                        $msg = "Berhasil menyalin $insertedCount baris data dari '$sourceDb ($sourceTable)' ke '$activeDb ($targetTable)'!";
                    } else {
                        $msg = "Mohon pilih minimal satu pemetaan kolom yang diisi!";
                        $msgType = 'alert';
                    }
                } else {
                    $msg = "Tabel asal '$sourceTable' tidak memiliki data untuk disalin.";
                    $msgType = 'alert';
                }
            } else {
                $msg = "Database atau Tabel tidak valid!";
                $msgType = 'alert';
            }
        }

        // HAPUS SATU BARIS DATA
        if ($action === 'delete_row' && $table) {
            $pkCol = $_GET['pk_col'] ?? '';
            $pkVal = $_GET['pk_val'] ?? '';
            if ($pkCol && $pkVal !== '') {
                $stmt = $pdo->prepare("DELETE FROM \"$table\" WHERE \"$pkCol\" = :val");
                $stmt->execute([':val' => $pkVal]);
                $msg = "Data berhasil dihapus.";
            }
            $action = 'browse';
        }

        // KOSONGKAN TABEL
        if ($action === 'truncate' && $table) {
            $pdo->exec("DELETE FROM \"$table\"");
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name=" . $pdo->quote($table));
            $msg = "Semua data pada tabel '$table' dikosongkan!";
            $action = 'browse';
        }

        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = "Error DB: " . $e->getMessage();
        $msgType = 'alert';
    }
}

$action = $_GET['action'] ?? 'tables';
$table  = $_GET['table'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SQLite Admin & Column Manager</title>
    <style>
        body { font-family: monospace, sans-serif; margin: 0; display: flex; height: 100vh; }
        #sidebar { width: 260px; background: #1e293b; color: #f8fafc; padding: 15px; box-sizing: border-box; }
        #sidebar select { width: 100%; padding: 6px; margin: 8px 0 15px 0; background: #334155; color: #fff; border: 1px solid #475569; border-radius: 4px; }
        #sidebar a { color: #cbd5e1; text-decoration: none; display: block; margin: 6px 0; padding: 4px 6px; border-radius: 3px; }
        #sidebar a:hover { background: #334155; color: #fff; }
        #sidebar a.active { background: #0284c7; color: #fff; }
        #content { flex: 1; padding: 20px; overflow-y: auto; background: #f8fafc; }
        table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; font-size: 13px; }
        th { background: #f1f5f9; }
        .msg { padding: 10px; background: #e0f2fe; color: #0369a1; margin-bottom: 15px; border-radius: 4px; }
        .alert { padding: 10px; background: #fee2e2; color: #991b1b; margin-bottom: 15px; border-radius: 4px; }
        .btn { padding: 5px 10px; border-radius: 3px; cursor: pointer; text-decoration: none; font-size: 12px; }
        .btn-danger { background: #ef4444; color: white; border: none; }
        .btn-primary { background: #0284c7; color: white; border: none; padding: 8px 15px; border-radius: 4px; }
        .btn-success { background: #16a34a; color: white; border: none; }
        .form-card { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 850px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select, .form-group input[type="text"] { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .col-mapping-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; background: #f8fafc; padding: 8px 12px; border-radius: 4px; border: 1px solid #e2e8f0; }
        .badge-notnull { background: #ef4444; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; font-weight: bold; }
        .option-box { background: #f1f5f9; padding: 12px; border-radius: 6px; border: 1px solid #cbd5e1; margin: 15px 0; }
        .radio-label { font-weight: normal; margin-right: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .nav-tabs { display: flex; gap: 8px; margin-bottom: 15px; }
        .nav-tabs a { padding: 8px 16px; background: #e2e8f0; text-decoration: none; color: #334155; border-radius: 4px; font-weight: bold; }
        .nav-tabs a.active { background: #0284c7; color: #fff; }
    </style>
</head>
<body>

<div id="sidebar">
    <h3 style="margin-top:0;">📂 SQLite Admin</h3>
    
    <label><small>Pilih Target DB (Tujuan):</small></label>
    <select onchange="location = '?' + new URLSearchParams({db: this.value}).toString();">
        <?php foreach ($foundDatabases as $db): ?>
            <option value="<?= htmlspecialchars($db) ?>" <?= $db === $activeDb ? 'selected' : '' ?>>
                <?= htmlspecialchars($db) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($pdo): ?>
        <strong>Daftar Tabel (<?= count($tables) ?>):</strong>
        <div style="margin-top: 8px;">
            <?php foreach ($tables as $t): ?>
                <a href="?db=<?= urlencode($activeDb) ?>&action=browse&table=<?= urlencode($t) ?>" 
                   class="<?= $table === $t ? 'active' : '' ?>">
                    📊 <?= htmlspecialchars($t) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <hr style="border-color: #334155; margin: 15px 0;">
        <a href="?db=<?= urlencode($activeDb) ?>&action=copy" class="<?= $action === 'copy' ? 'active' : '' ?>">🔄 Copy Data Antar DB</a>
    <?php endif; ?>
</div>

<div id="content">
    <?php if ($msg): ?>
        <div class="<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- TAB NAVIGASI -->
    <?php if ($pdo && $table && in_array($action, ['browse', 'structure'])): ?>
        <div class="nav-tabs">
            <a href="?db=<?= urlencode($activeDb) ?>&action=browse&table=<?= urlencode($table) ?>" class="<?= $action === 'browse' ? 'active' : '' ?>">📄 Data Tabel</a>
            <a href="?db=<?= urlencode($activeDb) ?>&action=structure&table=<?= urlencode($table) ?>" class="<?= $action === 'structure' ? 'active' : '' ?>">⚙️ Kelola Skema Kolom</a>
        </div>
    <?php endif; ?>

    <!-- TAB 1: BROWSE DATA -->
    <?php if ($pdo && $action === 'browse' && $table): ?>
        <?php
        $colsStmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
        $columnsInfo = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_column($columnsInfo, 'name');
        $pkCol = $columnsInfo[0]['name'] ?? 'id';

        $dataStmt = $pdo->query("SELECT * FROM \"$table\" LIMIT 100");
        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Tabel: <u><?= htmlspecialchars($table) ?></u></h2>
            <?php if (!empty($rows)): ?>
                <a href="?db=<?= urlencode($activeDb) ?>&action=truncate&table=<?= urlencode($table) ?>" 
                   class="btn btn-danger" onclick="return confirm('Kosongkan tabel?')">⚠️ Hapus Semua Data</a>
            <?php endif; ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">Aksi</th>
                    <?php foreach ($columnNames as $c): ?>
                        <th><?= htmlspecialchars($c) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= count($columnNames) + 1 ?>" style="text-align: center; color: #64748b; padding: 20px;">
                            <i>Tabel ini masih kosong (0 data). Silakan gunakan menu <b>"🔄 Copy Data Antar DB"</b> untuk mengisi data.</i>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <a href="?db=<?= urlencode($activeDb) ?>&action=delete_row&table=<?= urlencode($table) ?>&pk_col=<?= urlencode($pkCol) ?>&pk_val=<?= urlencode($r[$pkCol] ?? '') ?>" 
                               class="btn btn-danger" onclick="return confirm('Hapus baris ini?')">Hapus</a>
                        </td>
                        <?php foreach ($columnNames as $colName): ?>
                            <td><?= htmlspecialchars($r[$colName] ?? 'NULL') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <!-- TAB 2: EDIT & TAMBAH KOLOM -->
    <?php elseif ($pdo && $action === 'structure' && $table): ?>
        <?php
        $colsStmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
        $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <h2>⚙️ Kelola Struktur Kolom: <u><?= htmlspecialchars($table) ?></u></h2>

        <div class="form-card" style="margin-bottom: 25px;">
            <h4 style="margin-top:0; color:#16a34a;">➕ Tambah Kolom Baru</h4>
            <form method="POST">
                <input type="hidden" name="add_column" value="1">
                <div style="display:flex; gap:10px;">
                    <input type="text" name="new_col_name" placeholder="Nama Kolom Baru" required style="flex:2;">
                    <select name="new_col_type" style="flex:1;">
                        <option value="INTEGER">INTEGER</option>
                        <option value="TEXT" selected>TEXT</option>
                        <option value="REAL">REAL (Float)</option>
                        <option value="BLOB">BLOB</option>
                    </select>
                    <input type="text" name="new_col_default" placeholder="Nilai Default (Opsional)" style="flex:1;">
                    <button type="submit" class="btn btn-success">Tambah Kolom</button>
                </div>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Kolom Saat Ini</th>
                    <th>Tipe Data</th>
                    <th>Not Null?</th>
                    <th>Default Value</th>
                    <th>Ubah Nama Kolom</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($columns as $idx => $col): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><strong><?= htmlspecialchars($col['name']) ?></strong> <?= $col['pk'] ? '🔑 (PK)' : '' ?></td>
                        <td><?= htmlspecialchars($col['type']) ?></td>
                        <td><?= $col['notnull'] ? 'YES' : 'NO' ?></td>
                        <td><?= htmlspecialchars($col['dflt_value'] ?? 'NULL') ?></td>
                        <td>
                            <form method="POST" style="display:flex; gap:5px;">
                                <input type="hidden" name="rename_column" value="1">
                                <input type="hidden" name="old_col_name" value="<?= htmlspecialchars($col['name']) ?>">
                                <input type="text" name="new_col_name" value="<?= htmlspecialchars($col['name']) ?>" required style="padding:4px; font-size:12px;">
                                <button type="submit" class="btn btn-primary" style="padding:4px 8px;">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <!-- TAB 3: COPY DATA ANTA DB -->
    <?php elseif ($pdo && $action === 'copy'): ?>
        <h2>🔄 Copy Data Antar Database</h2>
        <div class="form-card">
            <form method="POST">
                <input type="hidden" name="copy_data" value="1">
                
                <h4 style="margin-top:0; color:#0284c7;">1. Database & Tabel Asal (Source)</h4>
                <div class="form-group">
                    <label>Database Asal:</label>
                    <select name="source_db" id="source_db" onchange="loadSourceTables()">
                        <option value="">-- Pilih Database Asal --</option>
                        <?php foreach ($foundDatabases as $db): ?>
                            <?php if ($db !== $activeDb): ?>
                                <option value="<?= htmlspecialchars($db) ?>"><?= htmlspecialchars($db) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tabel Asal:</label>
                    <select name="source_table" id="source_table" onchange="loadColumnsMapping()" disabled>
                        <option value="">-- Pilih DB Asal Terlebih Dahulu --</option>
                    </select>
                </div>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #e2e8f0;">

                <h4 style="margin-top:0; color:#16a34a;">2. Tabel Tujuan (Target: <?= htmlspecialchars($activeDb) ?>)</h4>
                <div class="form-group">
                    <label>Tabel Tujuan:</label>
                    <select name="target_table" id="target_table" onchange="loadColumnsMapping()" required>
                        <option value="">-- Pilih Tabel Tujuan --</option>
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="option-box">
                    <label style="font-weight:bold;">Penanganan Data Duplikat (Barcode / Primary Key):</label>
                    <div style="margin-top: 8px;">
                        <label class="radio-label">
                            <input type="radio" name="on_conflict" value="IGNORE" checked>
                            <span><strong>INSERT OR IGNORE</strong> (Abaikan & lewati data duplikat)</span>
                        </label>
                        <br>
                        <label class="radio-label" style="margin-top: 6px;">
                            <input type="radio" name="on_conflict" value="REPLACE">
                            <span><strong>INSERT OR REPLACE</strong> (Timpa data lama jika barcode bentrok)</span>
                        </label>
                        <br>
                        <label class="radio-label" style="margin-top: 6px;">
                            <input type="radio" name="on_conflict" value="">
                            <span><strong>INSERT Standard</strong> (Gagal/Error jika duplikat)</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Pemetaan Kolom Tabel Tujuan:</label>
                    <div id="columns_container" style="margin-top: 10px;">
                        <small style="color: #64748b;">Pilih tabel tujuan di atas untuk menampilkan pemetaan kolom.</small>
                    </div>
                </div>

                <br>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Proses salin data?')">Mulai Copy Data</button>
            </form>
        </div>

        <script>
        const ACTIVE_DB = "<?= htmlspecialchars($activeDb, ENT_QUOTES, 'UTF-8') ?>";

        document.addEventListener("DOMContentLoaded", () => {
            const srcDbSelect = document.getElementById('source_db');
            const tgtTblSelect = document.getElementById('target_table');

            if (srcDbSelect && srcDbSelect.value) {
                loadSourceTables();
            }
            if (tgtTblSelect && tgtTblSelect.value) {
                loadColumnsMapping();
            }
        });

        async function loadSourceTables() {
            const db = document.getElementById('source_db').value;
            const tableSelect = document.getElementById('source_table');
            
            if (!db) {
                tableSelect.innerHTML = '<option value="">-- Pilih DB Asal Terlebih Dahulu --</option>';
                tableSelect.disabled = true;
                loadColumnsMapping();
                return;
            }

            tableSelect.innerHTML = '<option value="">-- Memuat Tabel... --</option>';
            tableSelect.disabled = true;

            try {
                const res = await fetch(`?api=get_tables&api_db=${encodeURIComponent(db)}`);
                const tables = await res.json();

                if (Array.isArray(tables) && tables.length > 0) {
                    tableSelect.innerHTML = '<option value="">-- Pilih Tabel Asal --</option>';
                    tables.forEach(t => {
                        tableSelect.innerHTML += `<option value="${t}">${t}</option>`;
                    });
                    tableSelect.disabled = false;
                } else {
                    tableSelect.innerHTML = '<option value="">(Tidak Ada Tabel / File Kosong)</option>';
                    tableSelect.disabled = true;
                }
            } catch (e) {
                console.error("Error loading tables:", e);
                tableSelect.innerHTML = '<option value="">Gagal Memuat Tabel</option>';
                tableSelect.disabled = true;
            }

            loadColumnsMapping();
        }

        async function loadColumnsMapping() {
            const srcDb  = document.getElementById('source_db').value;
            const srcTbl = document.getElementById('source_table').value;
            const tgtTbl = document.getElementById('target_table').value;
            const container = document.getElementById('columns_container');

            if (!tgtTbl) {
                container.innerHTML = '<small style="color: #64748b;">Pilih tabel tujuan untuk menampilkan pemetaan kolom.</small>';
                return;
            }

            container.innerHTML = '<i>Memuat pemetaan kolom...</i>';

            let srcCols = [];
            let tgtCols = [];

            try {
                const resTgt = await fetch(`?api=get_cols&api_db=${encodeURIComponent(ACTIVE_DB)}&api_table=${encodeURIComponent(tgtTbl)}`);
                tgtCols = await resTgt.json();

                if (srcDb && srcTbl) {
                    const resSrc = await fetch(`?api=get_cols&api_db=${encodeURIComponent(srcDb)}&api_table=${encodeURIComponent(srcTbl)}`);
                    srcCols = await resSrc.json();
                }
            } catch (e) {
                console.error("Error fetching columns:", e);
            }

            if (!Array.isArray(tgtCols) || tgtCols.length === 0) {
                container.innerHTML = `<small style="color: #ef4444;">Gagal memuat skema kolom tabel tujuan (${tgtTbl}). Pastikan DB target tidak terkunci.</small>`;
                return;
            }

            container.innerHTML = '';

            tgtCols.forEach(tCol => {
                const colName = tCol.name;
                const isNotNull = tCol.notnull == 1;
                const isIdCol = colName.toLowerCase() === 'id' || tCol.pk == 1;
                const badge = isNotNull ? '<span class="badge-notnull">NOT NULL</span>' : '';

                let colOptionsHtml = '<option value="">-- Abaikan (NULL) --</option>';
                let isMatched = false;

                if (Array.isArray(srcCols)) {
                    srcCols.forEach(sCol => {
                        const selected = sCol.name.toLowerCase() === colName.toLowerCase() ? 'selected' : '';
                        if (selected) isMatched = true;
                        colOptionsHtml += `<option value="${sCol.name}" ${selected}>Ambil dari: ${sCol.name}</option>`;
                    });
                }

                let defaultType = 'ignore';
                if (isIdCol && !isMatched) {
                    defaultType = 'auto_id';
                } else if (isMatched) {
                    defaultType = 'col';
                } else if (isNotNull) {
                    defaultType = 'custom';
                }

                const defaultValue = isNotNull ? (tCol.dflt_value ? tCol.dflt_value.replace(/^'|'$/g, '') : '1') : '';

                container.innerHTML += `
                    <div class="col-mapping-row">
                        <div style="width: 220px;">
                            <strong>${colName}</strong> ${badge}
                            <div style="font-size:11px; color:#64748b;">Tipe: ${tCol.type || 'ANY'} ${tCol.pk ? '(PK)' : ''}</div>
                        </div>

                        <select name="map_type[${colName}]" id="type_${colName}" style="width: 170px; padding:4px;" onchange="toggleInputMode('${colName}')">
                            <option value="col" ${defaultType === 'col' ? 'selected' : ''}>Dari Kolom Asal</option>
                            <option value="auto_id" ${defaultType === 'auto_id' ? 'selected' : ''}>🔢 Auto-Generate ID (Urut)</option>
                            <option value="custom" ${defaultType === 'custom' ? 'selected' : ''}>Nilai Tetap (Manual)</option>
                            <option value="ignore" ${defaultType === 'ignore' ? 'selected' : ''}>Abaikan (NULL)</option>
                        </select>

                        <select name="map_col[${colName}]" id="col_${colName}" style="flex:1; padding:4px; ${defaultType === 'col' ? '' : 'display:none;'}">
                            ${colOptionsHtml}
                        </select>

                        <input type="text" name="map_value[${colName}]" id="val_${colName}" value="${defaultValue}" 
                               placeholder="${isNotNull ? 'Wajib diisi (NOT NULL)' : 'Nilai konstan / fallback'}" 
                               style="flex:1; padding:4px; ${defaultType === 'custom' ? '' : 'display:none;'}">
                               
                        <div id="info_${colName}" style="flex:1; font-size:11px; color:#0284c7; ${defaultType === 'auto_id' ? '' : 'display:none;'}">
                            ⚡ Otomatis generate ID berurutan (+1 dari ID tertinggi)
                        </div>
                    </div>
                `;
            });
        }

        function toggleInputMode(colName) {
            const type = document.getElementById(`type_${colName}`).value;
            const colSelect = document.getElementById(`col_${colName}`);
            const valInput = document.getElementById(`val_${colName}`);
            const infoDiv = document.getElementById(`info_${colName}`);

            colSelect.style.display = type === 'col' ? 'block' : 'none';
            valInput.style.display = type === 'custom' ? 'block' : 'none';
            infoDiv.style.display = type === 'auto_id' ? 'block' : 'none';
        }
        </script>
    <?php endif; ?>
</div>

</body>
</html>