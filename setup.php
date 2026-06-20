<?php
/**
 * setup.php - Enhanced Setup Wizard
 * Requirements → Database → Folders → Complete
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$step = $_GET['step'] ?? 'info';
$error = '';
$success = '';

// Default DB params
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'certificate_mgmt';

// Load existing config if available
if (file_exists(__DIR__ . '/config/db.php')) {
    include_once __DIR__ . '/config/db.php';
    if (defined('DB_HOST')) $db_host = DB_HOST;
    if (defined('DB_USER')) $db_user = DB_USER;
    if (defined('DB_PASS')) $db_pass = DB_PASS;
    if (defined('DB_NAME')) $db_name = DB_NAME;
}

// Requirement checks
$requirements = [
    'php_version' => [
        'name' => 'PHP Version 8.0+',
        'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'value' => PHP_VERSION,
        'icon' => 'fa-php'
    ],
    'pdo' => [
        'name' => 'PDO MySQL Extension',
        'status' => extension_loaded('pdo_mysql'),
        'value' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
        'icon' => 'fa-database'
    ],
    'gd' => [
        'name' => 'GD Graphics Library',
        'status' => extension_loaded('gd'),
        'value' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
        'icon' => 'fa-image'
    ],
    'json' => [
        'name' => 'JSON Extension',
        'status' => extension_loaded('json'),
        'value' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'icon' => 'fa-code'
    ],
    'mbstring' => [
        'name' => 'Mbstring Extension',
        'status' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
        'icon' => 'fa-font'
    ],
    'writable' => [
        'name' => 'Directory Writable',
        'status' => is_writable(__DIR__),
        'value' => is_writable(__DIR__) ? 'Writable' : 'Not Writable',
        'icon' => 'fa-folder'
    ]
];

$all_met = true;
foreach ($requirements as $r) {
    if (!$r['status']) $all_met = false;
}

// Handle DB Install
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'install_db') {
    $db_host = $_POST['db_host'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];
    $db_name = $_POST['db_name'];

    try {
        $temp = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $temp->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $temp = null;

        $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Save config
        if (!file_exists(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0777, true);

        $cfg = "<?php\n"
            . "// db.php - Database Configuration\n\n"
            . "define('DB_HOST', '" . addslashes($db_host) . "');\n"
            . "define('DB_USER', '" . addslashes($db_user) . "');\n"
            . "define('DB_PASS', '" . addslashes($db_pass) . "');\n"
            . "define('DB_NAME', '" . addslashes($db_name) . "');\n\n"
            . "try {\n"
            . "    \$pdo = new PDO(\n"
            . "        \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\",\n"
            . "        DB_USER,\n"
            . "        DB_PASS,\n"
            . "        [\n"
            . "            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
            . "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
            . "            PDO::ATTR_EMULATE_PREPARES   => false,\n"
            . "        ]\n"
            . "    );\n"
            . "} catch (PDOException \$e) {\n"
            . "    \$pdo = null;\n"
            . "}\n";

        file_put_contents(__DIR__ . '/config/db.php', $cfg);

        // Run schema
        if (file_exists(__DIR__ . '/database/schema.sql')) {
            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            $sql = preg_replace('/^USE `.*`;/mi', '', $sql);
            $conn->exec($sql);
        }

        $success = "Database configured and schema imported successfully!";
        $step = 'folders';
    } catch (Exception $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Handle folder creation
if ($step === 'folders' && ($_GET['create'] ?? '') === '1') {
    try {
        $dirs = [
            __DIR__ . '/uploads',
            __DIR__ . '/uploads/templates',
            __DIR__ . '/uploads/elements',
            __DIR__ . '/uploads/certificates',
            __DIR__ . '/uploads/imports',
            __DIR__ . '/uploads/events'
        ];
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) throw new Exception("Failed to create: $dir");
            }
            file_put_contents($dir . '/index.html', '<!DOCTYPE html><html><body><h3 style="font-family:sans-serif;text-align:center;margin-top:10%;">Access Denied</h3></body></html>');
        }
        $success = "All directories created successfully!";
        $step = 'complete';
    } catch (Exception $e) {
        $error = "Directory Error: " . $e->getMessage();
    }
}

$steps = ['info' => 1, 'db' => 2, 'folders' => 3, 'complete' => 4];
$current_step = $steps[$step] ?? 1;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — Certificate Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(37,99,235,0.06) 0%, transparent 70%);
            top: -200px; right: -200px;
            pointer-events: none;
        }
        .setup-card {
            width: 100%;
            max-width: 650px;
            z-index: 10;
        }
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 2rem;
        }
        .step-dot {
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center; justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            transition: var(--transition);
            border: 2px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-muted);
        }
        .step-dot.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 15px rgba(37,99,235,0.4);
        }
        .step-dot.done {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }
        .step-line {
            flex: 0 0 50px;
            height: 2px;
            background: var(--border-color);
            transition: var(--transition);
        }
        .step-line.done { background: var(--success); }
        .req-item {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
        }
    </style>
</head>
<body>
<div class="setup-card animate-fade-up">
    <div class="card-glass p-4 p-md-5">
        <h2 class="text-center fw-bold mb-1" style="font-family:var(--font-heading);">Setup Assistant</h2>
        <p class="text-center mb-4" style="color:var(--text-muted);font-size:0.85rem;">Configure your Certificate Management System</p>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php if ($i > 1): ?>
                    <div class="step-line <?= $current_step > $i - 1 ? 'done' : '' ?>"></div>
                <?php endif; ?>
                <div class="step-dot <?= $current_step === $i ? 'active' : ($current_step > $i ? 'done' : '') ?>">
                    <?= $current_step > $i ? '<i class="fa-solid fa-check"></i>' : $i ?>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert border-0 text-white mb-3" style="background:rgba(239,68,68,0.15);border-left:4px solid var(--danger)!important;border-radius:var(--radius-sm);">
                <i class="fa-solid fa-circle-xmark me-2"></i><strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert border-0 text-white mb-3" style="background:rgba(16,185,129,0.15);border-left:4px solid var(--success)!important;border-radius:var(--radius-sm);">
                <i class="fa-solid fa-circle-check me-2"></i><strong>Success:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Requirements -->
        <?php if ($step === 'info'): ?>
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>System Requirements</h5>
            <div class="d-flex flex-column gap-2 mb-4">
                <?php foreach ($requirements as $req): ?>
                    <div class="req-item">
                        <div>
                            <div class="fw-bold" style="font-size:0.9rem;"><?= $req['name'] ?></div>
                        </div>
                        <span class="badge-status <?= $req['status'] ? 'badge-valid' : 'badge-revoked' ?>"><?= $req['status'] ? '✓ ' . $req['value'] : '✗ Failed' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-end">
                <?php if ($all_met): ?>
                    <a href="setup.php?step=db" class="btn btn-primary btn-pill px-4">Continue <i class="fa-solid fa-arrow-right ms-1"></i></a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-pill px-4" disabled>Fix Failures to Proceed</button>
                <?php endif; ?>
            </div>

        <!-- Step 2: Database -->
        <?php elseif ($step === 'db'): ?>
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-database text-primary me-2"></i>Database Configuration</h5>
            <p style="color:var(--text-muted);font-size:0.85rem;" class="mb-4">Enter your MySQL credentials. The database and all tables will be created automatically.</p>
            <form method="POST" action="setup.php?step=db">
                <input type="hidden" name="action" value="install_db">
                <div class="mb-3">
                    <label class="form-label">MySQL Host</label>
                    <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($db_host) ?>" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($db_user) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($db_pass) ?>">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Database Name</label>
                    <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($db_name) ?>" required>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="setup.php?step=info" class="btn btn-ghost btn-pill px-4"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
                    <button type="submit" class="btn btn-primary btn-pill px-4">Initialize Database</button>
                </div>
            </form>

        <!-- Step 3: Folders -->
        <?php elseif ($step === 'folders'): ?>
            <div class="text-center py-3">
                <h5 class="fw-bold mb-3"><i class="fa-solid fa-folder-tree text-primary me-2"></i>Create Directories</h5>
                <p style="color:var(--text-muted);font-size:0.85rem;" class="mb-4">We'll create secure upload directories for templates, certificates, and imported files.</p>
                <a href="setup.php?step=folders&create=1" class="btn btn-primary btn-pill px-5 py-2">
                    <i class="fa-solid fa-folder-plus me-2"></i>Create Folders
                </a>
            </div>

        <!-- Step 4: Complete -->
        <?php elseif ($step === 'complete'): ?>
            <div class="text-center py-3">
                <div style="font-size:4rem;" class="mb-3">🎉</div>
                <h4 class="fw-bold mb-2">Setup Complete!</h4>
                <p style="color:var(--text-muted);font-size:0.85rem;" class="mb-4">Your Certificate Management System is ready to use.</p>
                <div class="card-glass p-3 text-start mx-auto mb-4" style="max-width:350px;">
                    <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="fa-solid fa-user-shield text-primary me-2"></i>Default Admin Account</h6>
                    <div style="font-size:0.8rem;" class="mb-1"><span style="color:var(--text-muted);">Username:</span> <strong>admin</strong></div>
                    <div style="font-size:0.8rem;"><span style="color:var(--text-muted);">Password:</span> <code>admin123</code></div>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a href="index.php" class="btn btn-ghost btn-pill px-4">Main Site</a>
                    <a href="admin/login.php" class="btn btn-primary btn-pill px-4">Open Admin Panel <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
