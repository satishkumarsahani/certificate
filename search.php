<?php
/**
 * search.php - Public Multi-Field Certificate Search
 * No login required
 */

session_start();

$setup_completed = file_exists(__DIR__ . '/config/db.php');
if (!$setup_completed) { header("Location: setup.php"); exit(); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

$results = [];
$searched = false;
$query = sanitize($_GET['q'] ?? '');
$search_type = sanitize($_GET['type'] ?? 'name');
$custom_fields = [];

try {
    $custom_fields = $pdo->query("SELECT * FROM custom_fields WHERE show_in_verification = 1 ORDER BY sequence ASC, id ASC")->fetchAll();
} catch (PDOException $e) {}

if (!empty($query)) {
    $searched = true;
    $where = '';
    $params = [];

    switch ($search_type) {
        case 'certificate':
            $where = "c.certificate_number LIKE :q OR c.verification_id LIKE :q2";
            $params = ['q' => "%$query%", 'q2' => "%$query%"];
            break;
        case 'roll':
            $where = "p.custom_data LIKE :q";
            $params = ['q' => '%"custom_roll_number":"' . addslashes($query) . '"%'];
            break;
        case 'registration':
            $where = "p.custom_data LIKE :q";
            $params = ['q' => '%"custom_registration_number":"' . addslashes($query) . '"%'];
            break;
        case 'mobile':
            $where = "p.custom_data LIKE :q";
            $params = ['q' => '%"custom_mobile_number":"' . addslashes($query) . '"%'];
            break;
        default: // name
            $where = "p.custom_data LIKE :q";
            $params = ['q' => "%$query%"];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.custom_data, ct.template_name, e.event_name
            FROM certificates c 
            JOIN participants p ON c.participant_id = p.id 
            JOIN certificate_templates ct ON c.template_id = ct.id 
            LEFT JOIN events e ON c.event_id = e.id 
            WHERE $where 
            ORDER BY c.issue_date DESC 
            LIMIT 50
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Log verification
        $result_status = !empty($results) ? 'found' : 'not_found';
        $cert_id = !empty($results) ? $results[0]['id'] : null;
        $pdo->prepare("INSERT INTO certificate_verifications (certificate_id, search_query, search_type, ip_address, result) VALUES (:cid, :query, :type, :ip, :result)")
            ->execute(['cid' => $cert_id, 'query' => $query, 'type' => $search_type, 'ip' => get_client_ip(), 'result' => $result_status]);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Certificates</title>
    <meta name="description" content="Search for your certificate by name, roll number, registration number, or certificate ID.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .search-hero {
            max-width: 900px;
            margin: 0 auto;
        }
        .result-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: var(--transition);
            margin-bottom: 1rem;
        }
        .result-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="search-hero">
    <!-- Back Link -->
    <div class="mb-4">
        <a href="index.php" style="color:var(--text-muted);font-size:0.85rem;"><i class="fa-solid fa-arrow-left me-1"></i>Back to Home</a>
    </div>

    <!-- Search Header -->
    <div class="text-center mb-4">
        <div style="width:55px;height:55px;border-radius:50%;background:rgba(6,182,212,0.1);border:1px solid rgba(6,182,212,0.2);display:inline-flex;align-items:center;justify-content:center;color:var(--info);font-size:1.3rem;margin-bottom:1rem;">
            <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <h1 class="fw-bold mb-2" style="font-size:1.75rem;">Search Certificates</h1>
        <p style="color:var(--text-secondary);font-size:0.9rem;">Find your certificate using any of the options below. No login required.</p>
    </div>

    <!-- Search Form -->
    <div class="card-glass mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search By</label>
                    <select name="type" class="form-select">
                        <option value="name" <?= $search_type === 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="roll" <?= $search_type === 'roll' ? 'selected' : '' ?>>Roll Number</option>
                        <option value="registration" <?= $search_type === 'registration' ? 'selected' : '' ?>>Registration Number</option>
                        <option value="certificate" <?= $search_type === 'certificate' ? 'selected' : '' ?>>Certificate ID</option>
                        <option value="mobile" <?= $search_type === 'mobile' ? 'selected' : '' ?>>Mobile Number</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Search Query</label>
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" name="q" class="form-control" placeholder="Enter your search term..." value="<?= e($query) ?>" required autofocus style="height:42px;">
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" style="height:42px;"><i class="fa-solid fa-search me-1"></i>Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if ($searched): ?>
        <div class="mb-3" style="font-size:0.85rem;color:var(--text-muted);">
            <i class="fa-solid fa-search me-1"></i>Found <strong style="color:var(--text-primary);"><?= count($results) ?></strong> result(s) for "<strong style="color:var(--primary-light);"><?= e($query) ?></strong>"
        </div>

        <?php if (empty($results)): ?>
            <div class="card-glass">
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="fa-solid fa-search"></i></div>
                    <div class="empty-state-title">No Certificates Found</div>
                    <div class="empty-state-text">Try a different search term or search type.</div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($results as $r): ?>
                <?php $cd = parse_custom_data($r['custom_data']); ?>
                <div class="result-card animate-fade-up">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1" style="font-size:1rem;"><?= e(get_participant_name($cd, $custom_fields)) ?></h5>
                            <div style="font-size:0.8rem;color:var(--text-muted);">
                                Certificate No: <code><?= e($r['certificate_number']) ?></code>
                            </div>
                        </div>
                        <span class="badge-status badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                    </div>
                    <div class="row g-3 mb-3">
                        <?php foreach (array_slice($custom_fields, 0, 6) as $cf): ?>
                            <?php if (!empty($cd[$cf['field_key']])): ?>
                                <div class="col-sm-4 col-6">
                                    <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;"><?= e($cf['field_name']) ?></div>
                                    <div style="font-size:0.85rem;font-weight:600;"><?= e($cd[$cf['field_key']]) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="col-sm-4 col-6">
                            <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Event</div>
                            <div style="font-size:0.85rem;"><?= e($r['event_name'] ?? '—') ?></div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div style="font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Issue Date</div>
                            <div style="font-size:0.85rem;"><?= format_date($r['issue_date']) ?></div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="verify.php?code=<?= e($r['verification_id']) ?>" class="btn btn-success btn-sm btn-pill px-3">
                            <i class="fa-solid fa-shield-halved me-1"></i>Verify
                        </a>
                        <?php if ($r['status'] === 'valid'): ?>
                            <a href="download.php?id=<?= $r['verification_id'] ?>" class="btn btn-primary btn-sm btn-pill px-3">
                                <i class="fa-solid fa-download me-1"></i>Download PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
