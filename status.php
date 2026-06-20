<?php
/**
 * status.php - Check Registration Status
 */

session_start();

$setup_completed = file_exists(__DIR__ . '/config/db.php');
if (!$setup_completed) { header("Location: setup.php"); exit(); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

$ref_query = sanitize($_GET['ref'] ?? '');
$participant = null;
$certificate = null;
$error = '';
$custom_fields = [];

if ($ref_query) {
    try {
        $custom_fields = $pdo->query("SELECT * FROM custom_fields ORDER BY sequence ASC")->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT p.*, e.event_name 
            FROM participants p 
            LEFT JOIN events e ON p.event_id = e.id 
            LEFT JOIN certificates c ON c.participant_id = p.id
            WHERE p.reference_number = :ref OR c.certificate_number = :ref
        ");
        $stmt->execute(['ref' => $ref_query]);
        $participant = $stmt->fetch();
        
        if ($participant) {
            // Check for certificate
            $c_stmt = $pdo->prepare("SELECT id, certificate_number, verification_id, status FROM certificates WHERE participant_id = :pid ORDER BY id DESC LIMIT 1");
            $c_stmt->execute(['pid' => $participant['id']]);
            $certificate = $c_stmt->fetch();
        } else {
            $error = "No registration found with this Reference or Certificate Number.";
        }
    } catch (PDOException $e) {
        $error = "An error occurred while checking status.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Registration Status</title>
    <meta name="description" content="Check your event registration and certificate status.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { min-height: 100vh; padding: 2rem 1rem; }
        .status-container { max-width: 600px; margin: 0 auto; }
        .form-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
        }
    </style>
</head>
<body>
    <div class="status-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="btn btn-ghost btn-sm btn-pill px-3">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Hub
            </a>
        </div>

        <div class="text-center mb-4">
            <div style="width:60px;height:60px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--info),#0891B2);display:inline-flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;margin-bottom:1rem;box-shadow:0 0 25px rgba(6,182,212,0.3);">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>
            <h1 class="fw-bold mb-2" style="font-family:var(--font-heading);font-weight:800;letter-spacing:-0.5px;">Track Status</h1>
            <p style="color:var(--text-secondary);font-size:0.95rem;">Enter your Registration Reference Number or Certificate Number to check your status or download your certificate.</p>
        </div>

        <div class="form-card animate-fade-up">
            <form method="GET" action="status.php" class="mb-4">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0" style="border-color:var(--border-color);"><i class="fa-solid fa-hashtag text-muted"></i></span>
                    <input type="text" name="ref" class="form-control border-start-0 ps-0" placeholder="e.g. REG-2026-001 or CERT-2026-001" value="<?= e($ref_query) ?>" required style="border-color:var(--border-color);">
                    <button class="btn btn-info px-4 fw-bold text-white" type="submit">Track</button>
                </div>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-danger border-0 d-flex align-items-center" style="background:rgba(239,68,68,0.15);">
                    <i class="fa-solid fa-circle-exclamation me-2"></i> <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($participant): ?>
                <?php $cd = parse_custom_data($participant['custom_data']); ?>
                <div class="p-4" style="background:var(--bg-sidebar);border-radius:var(--radius-lg);border:1px solid var(--border-color);">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><?= e(get_participant_name($cd, $custom_fields)) ?></h5>
                            <div style="font-size:0.85rem;color:var(--text-secondary);"><i class="fa-solid fa-calendar me-1"></i> <?= e($participant['event_name'] ?? 'General Event') ?></div>
                        </div>
                        <span class="badge bg-primary" style="font-size:0.75rem;">Registration Found</span>
                    </div>

                    <div class="mb-4">
                        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:0.5rem;">Registration Date</div>
                        <div class="fw-semibold"><?= format_date($participant['created_at']) ?></div>
                    </div>

                    <hr style="border-color:var(--border-color);">

                    <div class="mt-4">
                        <div style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem;">Certificate Status</div>
                        
                        <?php if ($certificate): ?>
                            <?php if ($certificate['status'] === 'valid'): ?>
                                <div class="d-flex align-items-center p-3 mb-3" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius);">
                                    <i class="fa-solid fa-certificate fa-2x text-success me-3"></i>
                                    <div>
                                        <div class="fw-bold text-success mb-1">Ready to Download</div>
                                        <div style="font-size:0.8rem;color:var(--text-muted);">Cert #: <?= e($certificate['certificate_number']) ?></div>
                                    </div>
                                </div>
                                <a href="download.php?id=<?= e($certificate['verification_id']) ?>" class="btn btn-success w-100 fw-bold btn-pill py-2">
                                    <i class="fa-solid fa-download me-2"></i>Download Certificate
                                </a>
                            <?php else: ?>
                                <div class="alert alert-warning border-0">
                                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Your certificate is currently marked as <strong><?= e($certificate['status']) ?></strong>. Please contact the administrator.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="d-flex align-items-center p-3" style="background:rgba(245,158,11,0.1);border:1px dashed rgba(245,158,11,0.3);border-radius:var(--radius);">
                                <i class="fa-solid fa-hourglass-half fa-2x text-warning me-3"></i>
                                <div>
                                    <div class="fw-bold text-warning mb-1">Processing</div>
                                    <div style="font-size:0.85rem;color:var(--text-muted);">Your registration is being processed. The certificate will appear here once generated.</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
