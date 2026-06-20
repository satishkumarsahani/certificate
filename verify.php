<?php
/**
 * verify.php - Public Certificate Verification
 * QR Code scan or manual code entry
 */

session_start();

$setup_completed = file_exists(__DIR__ . '/config/db.php');
if (!$setup_completed) { header("Location: setup.php"); exit(); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

$certificate = null;
$participant = null;
$searched = false;
$code = sanitize($_GET['code'] ?? $_POST['code'] ?? '');

$custom_fields = [];
try { $custom_fields = $pdo->query("SELECT * FROM custom_fields WHERE show_in_verification = 1 ORDER BY sequence ASC")->fetchAll(); } catch (PDOException $e) {}

if (!empty($code)) {
    $searched = true;
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.custom_data, ct.template_name, ct.background_image, e.event_name, e.organizer 
            FROM certificates c 
            JOIN participants p ON c.participant_id = p.id 
            JOIN certificate_templates ct ON c.template_id = ct.id 
            LEFT JOIN events e ON c.event_id = e.id 
            WHERE c.verification_id = :code OR c.certificate_number = :code2
            LIMIT 1
        ");
        $stmt->execute(['code' => $code, 'code2' => $code]);
        $certificate = $stmt->fetch();

        // Log verification attempt
        $result = $certificate ? 'found' : 'not_found';
        $cert_id = $certificate['id'] ?? null;
        $pdo->prepare("INSERT INTO certificate_verifications (certificate_id, search_query, search_type, ip_address, result) VALUES (:cid, :q, 'verification_code', :ip, :res)")
            ->execute(['cid' => $cert_id, 'q' => $code, 'ip' => get_client_ip(), 'res' => $result]);
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate</title>
    <meta name="description" content="Verify the authenticity of a certificate using its verification code or QR code.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { min-height: 100vh; padding: 2rem 1rem; }
        .verify-container { max-width: 700px; margin: 0 auto; }
        .verify-result {
            border-radius: var(--radius-xl);
            overflow: hidden;
        }
        .verify-status-bar {
            padding: 1.5rem;
            text-align: center;
        }
        .verify-status-bar.valid { background: linear-gradient(135deg, #10B981, #059669); }
        .verify-status-bar.revoked { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .verify-status-bar.expired { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .verify-status-bar.not-found { background: linear-gradient(135deg, #64748B, #475569); }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.85rem;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-weight: 500; }
        .detail-value { font-weight: 600; color: var(--text-primary); text-align: right; }
    </style>
</head>
<body>
<div class="verify-container">
    <div class="mb-4">
        <a href="index.php" style="color:var(--text-muted);font-size:0.85rem;"><i class="fa-solid fa-arrow-left me-1"></i>Back to Home</a>
    </div>

    <div class="text-center mb-4">
        <div style="width:55px;height:55px;border-radius:50%;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);display:inline-flex;align-items:center;justify-content:center;color:var(--success);font-size:1.3rem;margin-bottom:1rem;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="fw-bold mb-2" style="font-size:1.75rem;">Verify Certificate</h1>
        <p style="color:var(--text-secondary);font-size:0.9rem;">Enter the verification code from the certificate or scan the QR code.</p>
    </div>

    <!-- Verify Form -->
    <div class="card-glass mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-9">
                    <div class="search-box">
                        <i class="fa-solid fa-key"></i>
                        <input type="text" name="code" class="form-control" placeholder="Enter verification code..." value="<?= e($code) ?>" required autofocus style="height:48px;font-size:1rem;letter-spacing:1px;">
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-success w-100" style="height:48px;font-size:0.95rem;font-weight:700;">
                        <i class="fa-solid fa-shield-halved me-1"></i>Verify
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Result -->
    <?php if ($searched): ?>
        <div class="verify-result card-glass animate-fade-up">
            <?php if ($certificate): ?>
                <?php $cd = parse_custom_data($certificate['custom_data']); ?>
                
                <!-- Status Banner -->
                <div class="verify-status-bar <?= $certificate['status'] ?>">
                    <?php if ($certificate['status'] === 'valid'): ?>
                        <i class="fa-solid fa-circle-check" style="font-size:2.5rem;margin-bottom:0.5rem;display:block;"></i>
                        <h4 class="fw-bold text-white mb-1">Certificate Verified</h4>
                        <p class="text-white-50 mb-0" style="font-size:0.85rem;">This certificate is authentic and valid.</p>
                    <?php elseif ($certificate['status'] === 'revoked'): ?>
                        <i class="fa-solid fa-circle-xmark" style="font-size:2.5rem;margin-bottom:0.5rem;display:block;"></i>
                        <h4 class="fw-bold text-white mb-1">Certificate Revoked</h4>
                        <p class="text-white-50 mb-0" style="font-size:0.85rem;">This certificate has been revoked by the issuing authority.</p>
                    <?php else: ?>
                        <i class="fa-solid fa-clock" style="font-size:2.5rem;margin-bottom:0.5rem;display:block;"></i>
                        <h4 class="fw-bold text-white mb-1">Certificate Expired</h4>
                        <p class="text-white-50 mb-0" style="font-size:0.85rem;">This certificate has exceeded its validity period.</p>
                    <?php endif; ?>
                </div>

                <!-- Details -->
                <div class="p-4">
                    <h6 class="fw-bold mb-3" style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;">
                        <i class="fa-solid fa-id-card me-1"></i>Certificate Details
                    </h6>
                    
                    <?php foreach ($custom_fields as $cf): ?>
                        <?php if (!empty($cd[$cf['field_key']])): ?>
                            <div class="detail-row">
                                <span class="detail-label"><?= e($cf['field_name']) ?></span>
                                <span class="detail-value"><?= e($cd[$cf['field_key']]) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="detail-row">
                        <span class="detail-label">Certificate Number</span>
                        <span class="detail-value"><code><?= e($certificate['certificate_number']) ?></code></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Verification ID</span>
                        <span class="detail-value"><code><?= e($certificate['verification_id']) ?></code></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Event</span>
                        <span class="detail-value"><?= e($certificate['event_name'] ?? '—') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Template</span>
                        <span class="detail-value"><?= e($certificate['template_name']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Issue Date</span>
                        <span class="detail-value"><?= format_date($certificate['issue_date']) ?></span>
                    </div>
                    <?php if ($certificate['organizer']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Issuing Authority</span>
                        <span class="detail-value"><?= e($certificate['organizer']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($certificate['status'] === 'revoked' && $certificate['revoke_reason']): ?>
                        <div class="mt-3 p-3" style="background:rgba(239,68,68,0.1);border-radius:var(--radius-sm);border:1px solid rgba(239,68,68,0.2);">
                            <div style="font-size:0.75rem;color:var(--danger);font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.25rem;">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Revocation Reason
                            </div>
                            <div style="font-size:0.85rem;"><?= e($certificate['revoke_reason']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($certificate['pdf_file'] && $certificate['status'] === 'valid'): ?>
                        <div class="mt-4 text-center">
                            <a href="download.php?id=<?= e($certificate['verification_id']) ?>" class="btn btn-primary btn-pill px-4 py-2">
                                <i class="fa-solid fa-download me-2"></i>Download Certificate PDF
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Not Found -->
                <div class="verify-status-bar not-found">
                    <i class="fa-solid fa-magnifying-glass" style="font-size:2.5rem;margin-bottom:0.5rem;display:block;"></i>
                    <h4 class="fw-bold text-white mb-1">Certificate Not Found</h4>
                    <p class="text-white-50 mb-0" style="font-size:0.85rem;">No certificate matches the provided verification code.</p>
                </div>
                <div class="p-4 text-center">
                    <p style="font-size:0.85rem;color:var(--text-muted);">Please double-check the code and try again, or <a href="search.php">search by other criteria</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
