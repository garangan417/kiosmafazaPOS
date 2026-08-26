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

// API ENDPOINT UNTUK AJAX (Mendapatkan Tabel & Kolom secara Dinamis)
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $reqDb = $_GET['api_db'] ?? '';
    $reqFile = $targetFolder . '/' . $reqDb;

    if (!file_exists($reqFile)) {
        echo json_encode(['error' => 'File DB tidak ditemukan']);
        exit;
    }

    try {
        $apiPdo = new PDO('sqlite:' . $reqFile);
        $apiPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($_GET['api'] === 'get_tables') {
            $stmt = $apiPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
            exit;
        }

        if ($_GET['api'] === 'get_cols' && isset($_GET['api_table'])) {
            $tbl = $_GET['api_table'];
            $stmt = $apiPdo->query("PRAGMA table_info(" . $apiPdo->quote($tbl) . ")");
            $cols = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
            echo json_encode($cols);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
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

// 3. Inisialisasi Database & Handling Action
if ($dbFile && file_exists($dbFile)) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $action = $_GET['action'] ?? 'tables';
        $table  = $_GET['table'] ?? '';

        // PROSES COPY DATA DENGAN CHECKBOX MAPPING
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_data'])) {
            $sourceDb = $_POST['source_db'] ?? '';
            $sourceTable = $_POST['source_table'] ?? '';
            $targetTable = $_POST['target_table'] ?? '';
            $selectedCols = $_POST['cols'] ?? []; // Array dari centang [src_col => tgt_col]

            $srcCols = [];
            $tgtCols = [];

            foreach ($selectedCols as $src => $tgt) {
                if (!empty($tgt)) {
                    $srcCols[] = "\"$src\"";
                    $tgtCols[] = "\"$tgt\"";
                }
            }

            if ($sourceDb && $sourceTable && $targetTable && !empty($srcCols)) {
                $sourceDbPath = $targetFolder . '/' . $sourceDb;
                
                $pdo->exec("ATTACH DATABASE " . $pdo->quote($sourceDbPath) . " AS source_db");

                $sqlCopy = sprintf(
                    "INSERT INTO \"%s\" (%s) SELECT %s FROM source_db.\"%s\"",
                    $targetTable,
                    implode(', ', $tgtCols),
                    implode(', ', $srcCols),
                    $sourceTable
                );
                
                $count = $pdo->exec($sqlCopy);
                $pdo->exec("DETACH DATABASE source_db");

                $msg = "Berhasil menyalin $count data dari '$sourceDb ($sourceTable)' ke '$activeDb ($targetTable)'!";
            } else {
                $msg = "Mohon pilih minimal satu kolom untuk disalin!";
                $msgType = 'alert';
            }
        }

        // HAPUS DATA PER BARIS
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

        // KOSONGKAN TABEL (TRUNCATE)
        if ($action === 'truncate' && $table) {
            $pdo->exec("DELETE FROM \"$table\"");
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name=" . $pdo->quote($table));
            $msg = "Semua data pada tabel '$table' dikosongkan!";
            $action = 'browse';
        }

        // Ambil Tabel Target
        $tablesStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
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
    <title>SQLite Admin - Auto AJAX Mapping</title>
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
        .form-card { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 650px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group select { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .col-mapping-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; background: #f8fafc; padding: 6px 10px; border-radius: 4px; border: 1px solid #e2e8f0; }
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
                   class="<?= $table === $t && $action === 'browse' ? 'active' : '' ?>">
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

    <?php if ($pdo && $action === 'copy'): ?>
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
                        <?php foreach ($tables as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Pilih & Mapping Kolom:</label>
                    <div id="columns_container" style="margin-top: 10px;">
                        <small style="color: #64748b;">Pilih tabel asal dan tujuan di atas untuk memilih kolom.</small>
                    </div>
                </div>

                <br>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Proses copy data?')">Mulai Copy Data</button>
            </form>
        </div>

        <script>
        let targetColumns = [];

        async function loadSourceTables() {
            const db = document.getElementById('source_db').value;
            const tableSelect = document.getElementById('source_table');
            tableSelect.innerHTML = '<option value="">-- Memuat Tabel... --</option>';
            tableSelect.disabled = true;
            document.getElementById('columns_container').innerHTML = '';

            if (!db) return;

            const res = await fetch(`?api=get_tables&api_db=${encodeURIComponent(db)}`);
            const tables = await res.json();

            tableSelect.innerHTML = '<option value="">-- Pilih Tabel Asal --</option>';
            tables.forEach(t => {
                tableSelect.innerHTML += `<option value="${t}">${t}</option>`;
            });
            tableSelect.disabled = false;
        }

        async function loadColumnsMapping() {
            const srcDb = document.getElementById('source_db').value;
            const srcTbl = document.getElementById('source_table').value;
            const tgtTbl = document.getElementById('target_table').value;
            const container = document.getElementById('columns_container');

            if (!srcDb || !srcTbl || !tgtTbl) return;

            container.innerHTML = '<i>Memuat kolom...</i>';

            // Ambil Kolom Asal & Kolom Tujuan secara bersamaan
            const [resSrc, resTgt] = await Promise.all([
                fetch(`?api=get_cols&api_db=${encodeURIComponent(srcDb)}&api_table=${encodeURIComponent(srcTbl)}`),
                fetch(`?api=get_cols&api_db=${encodeURIComponent('<?= $activeDb ?>')}&api_table=${encodeURIComponent(tgtTbl)}`)
            ]);

            const srcCols = await resSrc.json();
            targetColumns = await resTgt.json();

            container.innerHTML = '';
            
            srcCols.forEach(col => {
                let optionsHtml = targetColumns.map(tc => 
                    `<option value="${tc}" ${tc.toLowerCase() === col.toLowerCase() ? 'selected' : ''}>${tc}</option>`
                ).join('');

                container.innerHTML += `
                    <div class="col-mapping-row">
                        <input type="checkbox" name="cols[${col}]" value="${col}" id="chk_${col}" checked 
                               onchange="document.getElementById('sel_${col}').disabled = !this.checked">
                        <label for="chk_${col}" style="width: 150px; cursor:pointer;"><strong>${col}</strong></label>
                        <span>➔ Copy ke Kolom Tujuan:</span>
                        <select name="cols[${col}]" id="sel_${col}" style="flex:1; padding:4px;">
                            ${optionsHtml}
                        </select>
                    </div>
                `;
            });
        }
        </script>

    <?php elseif ($pdo && $action === 'browse' && $table): ?>
        <?php
        $colsStmt = $pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")");
        $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
        $pkCol = $columns[0]['name'] ?? 'id';

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
            <tr>
                <th style="width: 60px;">Aksi</th>
                <?php foreach (array_keys($rows[0] ?? []) as $c): ?><th><?= htmlspecialchars($c) ?></th><?php endforeach; ?>
            </tr>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <a href="?db=<?= urlencode($activeDb) ?>&action=delete_row&table=<?= urlencode($table) ?>&pk_col=<?= urlencode($pkCol) ?>&pk_val=<?= urlencode($r[$pkCol]) ?>" 
                       class="btn btn-danger" onclick="return confirm('Hapus baris ini?')">Hapus</a>
                </td>
                <?php foreach ($r as $v): ?><td><?= htmlspecialchars($v ?? 'NULL') ?></td><?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>