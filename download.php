<?php
/**
 * download.php - Public Certificate Download with Tracking & Security Gate
 */

session_start();

if (!file_exists(__DIR__ . '/config/db.php')) { die("System not configured."); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

$verification_id = $_GET['id'] ?? '';

if (empty($verification_id)) {
    die("Invalid request.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM certificates WHERE verification_id = :vid AND status = 'valid' LIMIT 1");
    $stmt->execute(['vid' => $verification_id]);
    $cert = $stmt->fetch();

    if (!$cert) {
        echo '<!DOCTYPE html><html><head><title>Download Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css"></head><body data-theme="dark" style="display:flex;align-items:center;justify-content:center;min-height:100vh;"><div class="text-center"><h4 class="fw-bold text-danger mb-2">Download Unavailable</h4><p style="color:var(--text-muted);">This certificate is not available for download. It may be revoked or expired.</p><a href="index.php" class="btn btn-ghost btn-pill px-3 mt-3">Back to Home</a></div></body></html>';
        exit();
    }

    // --- DOWNLOAD GATE LOGIC ---
    $req_fields = $pdo->query("SELECT * FROM custom_fields WHERE require_for_download = 1 ORDER BY sequence ASC")->fetchAll();
    
    if (count($req_fields) > 0) {
        $is_unlocked = $_SESSION['unlocked_certs'][$verification_id] ?? false;
        $gate_error = '';

        if (!$is_unlocked) {
            $p_stmt = $pdo->prepare("SELECT custom_data FROM participants WHERE id = :pid");
            $p_stmt->execute(['pid' => $cert['participant_id']]);
            $participant = $p_stmt->fetch();
            $custom_data = parse_custom_data($participant['custom_data'] ?? '');

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $all_match = true;
                foreach ($req_fields as $rf) {
                    $key = $rf['field_key'];
                    $submitted = trim($_POST[$key] ?? '');
                    $actual = trim($custom_data[$key] ?? '');
                    
                    // Case-insensitive comparison for text fields
                    if (strcasecmp($submitted, $actual) !== 0) {
                        $all_match = false;
                        break;
                    }
                }

                if ($all_match) {
                    $_SESSION['unlocked_certs'][$verification_id] = true;
                    header("Location: download.php?id=" . urlencode($verification_id));
                    exit();
                } else {
                    $gate_error = "The information provided does not match our records. Please try again.";
                }
            }

            // Render Gate Form
            ?>
            <!DOCTYPE html>
            <html lang="en" data-theme="dark">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Secure Download Gate</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <link rel="stylesheet" href="assets/css/app.css">
            </head>
            <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem;">
                <div class="card-glass p-4" style="max-width:500px;width:100%;">
                    <div class="text-center mb-4">
                        <div style="width:60px;height:60px;border-radius:var(--radius-lg);background:rgba(245,158,11,0.1);display:inline-flex;align-items:center;justify-content:center;color:var(--warning);font-size:1.5rem;margin-bottom:1rem;border:1px solid rgba(245,158,11,0.2);">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <h4 class="fw-bold mb-1">Protected Download</h4>
                        <p style="color:var(--text-secondary);font-size:0.9rem;">Please verify your identity to download this certificate.</p>
                    </div>

                    <?php if ($gate_error): ?>
                        <div class="alert alert-danger border-0"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($gate_error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <?php foreach ($req_fields as $rf): ?>
                            <div class="mb-3">
                                <label class="form-label"><?= e($rf['field_name']) ?></label>
                                <?php if ($rf['field_type'] === 'select'): ?>
                                    <select name="<?= e($rf['field_key']) ?>" class="form-select" required>
                                        <option value="">— Select —</option>
                                        <?php 
                                        $opts = array_map('trim', explode(',', $rf['field_options'] ?? ''));
                                        foreach ($opts as $opt): 
                                            if (empty($opt)) continue;
                                        ?>
                                            <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="<?= $rf['field_type'] === 'date' ? 'date' : ($rf['field_type'] === 'email' ? 'email' : 'text') ?>" 
                                           name="<?= e($rf['field_key']) ?>" 
                                           class="form-control" required>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-warning w-100 fw-bold btn-pill mt-2">
                            <i class="fa-solid fa-unlock me-2"></i>Verify & Download
                        </button>
                        <div class="text-center mt-3">
                            <a href="index.php" class="text-muted text-decoration-none" style="font-size:0.85rem;">Cancel</a>
                        </div>
                    </form>
                </div>
            </body>
            </html>
            <?php
            exit();
        }
    }
    // --- END DOWNLOAD GATE LOGIC ---

    // Check if PDF exists
    $pdf_path = CERTIFICATES_DIR . '/' . $cert['pdf_file'];
    if (empty($cert['pdf_file']) || !file_exists($pdf_path)) {
        // Generate PDF Dynamically On-The-Fly!
        require_once __DIR__ . '/includes/pdf_generator.php';
        
        $new_filename = 'CERT_' . $cert['certificate_number'] . '_' . time() . '.pdf';
        $pdf_path = CERTIFICATES_DIR . '/' . $new_filename;
        
        $success = generate_certificate_pdf($pdo, $cert['template_id'], $cert['participant_id'], $pdf_path, null, $cert);
        
        if ($success) {
            // Update database with the new generated PDF filename
            $pdo->prepare("UPDATE certificates SET pdf_file = :pdf WHERE id = :id")
                ->execute(['pdf' => $new_filename, 'id' => $cert['id']]);
            $cert['pdf_file'] = $new_filename;
        } else {
            echo '<!DOCTYPE html><html><head><title>PDF Not Found</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/app.css"></head><body data-theme="dark" style="display:flex;align-items:center;justify-content:center;min-height:100vh;"><div class="text-center"><h4 class="fw-bold text-warning mb-2">PDF Not Generated</h4><p style="color:var(--text-muted);">The certificate PDF could not be generated dynamically.</p><a href="index.php" class="btn btn-ghost btn-pill px-3 mt-3">Back to Home</a></div></body></html>';
            exit();
        }
    }

    // Log download
    $pdo->prepare("INSERT INTO certificate_downloads (certificate_id, ip_address, user_agent) VALUES (:cid, :ip, :ua)")
        ->execute([
            'cid' => $cert['id'],
            'ip' => get_client_ip(),
            'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);

    // Serve file
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Certificate_' . $cert['certificate_number'] . '.pdf"');
    header('Content-Length: ' . filesize($pdf_path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($pdf_path);
    exit();

} catch (PDOException $e) {
    die("An error occurred.");
}
