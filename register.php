<?php
/**
 * register.php - Public Event Registration
 */

session_start();

$setup_completed = file_exists(__DIR__ . '/config/db.php');
if (!$setup_completed) { header("Location: setup.php"); exit(); }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

// Fetch active events
$events = [];
try {
    $events = $pdo->query("SELECT id, event_name, start_date, end_date FROM events WHERE status = 'active' ORDER BY start_date DESC")->fetchAll();
} catch (PDOException $e) {}

// Fetch custom fields
$custom_fields = [];
try {
    $custom_fields = $pdo->query("SELECT * FROM custom_fields ORDER BY sequence ASC, id ASC")->fetchAll();
} catch (PDOException $e) {}

$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_validate()) {
        $event_id = intval($_POST['event_id'] ?? 0);
        $has_error = false;
        
        if ($event_id <= 0) {
            flash('error', 'Please select a valid event.');
            $has_error = true;
        }
        
        $custom_data = [];
        $post_data = $_POST; // Make it easier to fetch parent values

        foreach ($custom_fields as $field) {
            $val = sanitize($_POST[$field['field_key']] ?? '');
            
            // Check dependency
            $is_active = true;
            if (!empty($field['depends_on_field_id'])) {
                // Find parent key
                $parent_key = '';
                foreach ($custom_fields as $cf) {
                    if ($cf['id'] == $field['depends_on_field_id']) {
                        $parent_key = $cf['field_key'];
                        break;
                    }
                }
                $parent_val = sanitize($_POST[$parent_key] ?? '');
                if ($parent_val !== $field['depends_on_value']) {
                    $is_active = false;
                    $val = ''; // Clear value if dependency not met
                }
            }

            if ($is_active && $field['is_required'] && empty($val)) {
                flash('error', "Field '" . e($field['field_name']) . "' is required.");
                $has_error = true;
            }
            $custom_data[$field['field_key']] = $val;
        }

        if (!$has_error) {
            $year = date('Y');
            $ref_prefix = 'REG-' . $year . '-';
            $attempts = 0;
            
            while (true) {
                $stmt = $pdo->prepare("SELECT reference_number FROM participants WHERE reference_number LIKE :prefix ORDER BY id DESC LIMIT 1");
                $stmt->execute(['prefix' => $ref_prefix . '%']);
                $last_ref = $stmt->fetchColumn();

                if ($last_ref) {
                    $parts = explode('-', $last_ref);
                    $num = intval(end($parts)) + 1;
                } else {
                    $num = 1;
                }
                
                $num += $attempts;
                $ref_num = $ref_prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO participants (event_id, reference_number, custom_data, status) VALUES (:eid, :ref, :data, 'active')");
                    $stmt->execute(['eid' => $event_id, 'ref' => $ref_num, 'data' => json_encode($custom_data)]);
                    $success_msg = "Successfully registered! Your Reference Number is: " . $ref_num;
                    $success_ref = $ref_num;
                    break;
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Unique constraint violation
                        $attempts++;
                        if ($attempts > 10) {
                            flash('error', 'Registration failed due to a database collision. Please try again.');
                            break;
                        }
                    } else {
                        flash('error', 'Registration failed due to a database error.');
                        break;
                    }
                }
            }
        }
    } else {
        flash('error', 'Security verification failed.');
    }
    csrf_regenerate();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Registration</title>
    <meta name="description" content="Register for upcoming events securely.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .register-container {
            max-width: 700px;
            margin: 0 auto;
        }
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
    <div class="register-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="btn btn-ghost btn-sm btn-pill px-3">
                <i class="fa-solid fa-arrow-left me-2"></i>Back to Hub
            </a>
        </div>

        <div class="text-center mb-4">
            <div style="width:60px;height:60px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--warning),#D97706);display:inline-flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;margin-bottom:1rem;box-shadow:0 0 25px rgba(245,158,11,0.3);">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h1 class="fw-bold mb-2" style="font-family:var(--font-heading);font-weight:800;letter-spacing:-0.5px;">Event Registration</h1>
            <p style="color:var(--text-secondary);font-size:0.95rem;">Fill out the form below to register for an upcoming event.</p>
        </div>

        <?= render_flash_messages() ?>

        <?php if ($success_msg): ?>
            <div class="alert alert-success border-0 p-4" style="background:rgba(16,185,129,0.15);border-radius:var(--radius-lg);">
                <div class="d-flex align-items-center mb-3">
                    <i class="fa-solid fa-circle-check fa-2x me-3 text-success"></i>
                    <div>
                        <h5 class="fw-bold text-success mb-1">Registration Complete!</h5>
                        <p class="mb-0 text-success" style="opacity:0.9;font-size:0.9rem;">We have successfully received your details.</p>
                    </div>
                </div>
                <div class="p-3 mt-3 text-center" style="background:rgba(255,255,255,0.05);border-radius:var(--radius);border:1px dashed rgba(16,185,129,0.5);">
                    <div style="font-size:0.85rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:0.5rem;">Your Reference Number</div>
                    <div class="fw-bold font-monospace" style="font-size:1.5rem;letter-spacing:2px;color:var(--text-primary);"><?= e($success_ref) ?></div>
                    <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.5rem;">Please save this number. You can use it to check your status later.</div>
                </div>
                <div class="text-center mt-4">
                    <a href="status.php?ref=<?= urlencode($success_ref) ?>" class="btn btn-success btn-pill px-4 fw-bold me-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Check Status</a>
                    <a href="register.php" class="btn btn-ghost btn-pill px-4">Register Another</a>
                </div>
            </div>
        <?php else: ?>
            <div class="form-card animate-fade-up">
                <?php if (empty($events)): ?>
                    <div class="text-center py-5">
                        <i class="fa-solid fa-calendar-xmark fa-3x text-muted mb-3" style="opacity:0.5;"></i>
                        <h5 class="fw-bold">No Active Events</h5>
                        <p class="text-muted" style="font-size:0.9rem;">There are currently no events open for registration.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="register.php">
                        <?= csrf_field() ?>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold"><i class="fa-solid fa-calendar me-2 text-primary"></i>Select Event *</label>
                            <select name="event_id" class="form-select form-select-lg" required>
                                <option value="">— Choose an Event —</option>
                                <?php foreach ($events as $evt): ?>
                                    <option value="<?= $evt['id'] ?>">
                                        <?= e($evt['event_name']) ?> 
                                        <?php if ($evt['start_date']) echo " (" . format_date($evt['start_date']) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr style="border-color:var(--border-color);margin:2rem 0;">

                        <div class="row g-3">
                            <?php 
                            $field_map = [];
                            foreach ($custom_fields as $cf) { $field_map[$cf['id']] = $cf['field_key']; }
                            foreach ($custom_fields as $field): 
                                $dep_key = !empty($field['depends_on_field_id']) ? ($field_map[$field['depends_on_field_id']] ?? '') : '';
                                $dep_val = $field['depends_on_value'] ?? '';
                                $container_class = $dep_key ? 'col-md-6 conditional-field' : 'col-md-6';
                                $container_style = $dep_key ? 'display:none;' : '';
                                $container_attrs = $dep_key ? "data-depends-on=\"{$dep_key}\" data-depends-val=\"" . e($dep_val) . "\" data-was-required=\"" . ($field['is_required'] ? '1' : '0') . "\"" : "";
                            ?>
                                <?php 
                                    $type = match($field['field_type']) {
                                        'number' => 'number',
                                        'date' => 'date',
                                        'email' => 'email',
                                        'phone' => 'tel',
                                        default => 'text'
                                    };
                                    $req = $field['is_required'] ? 'required' : '';
                                    $star = $field['is_required'] ? ' <span class="text-danger">*</span>' : '';
                                ?>
                                <div class="<?= $container_class ?>" style="<?= $container_style ?>" <?= $container_attrs ?>>
                                    <label class="form-label fw-semibold" style="font-size:0.85rem;color:var(--text-secondary);"><?= e($field['field_name']) ?><?= $star ?></label>
                                    <?php if ($field['field_type'] === 'select'): ?>
                                        <select name="<?= e($field['field_key']) ?>" class="form-select custom-input" <?= $req ?>>
                                            <option value="">— Select —</option>
                                            <?php 
                                            $opts = array_map('trim', explode(',', $field['field_options'] ?? ''));
                                            foreach ($opts as $opt): 
                                                if (empty($opt)) continue;
                                            ?>
                                                <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?= $type ?>" name="<?= e($field['field_key']) ?>" class="form-control custom-input" <?= $req ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 pt-2">
                            <button type="submit" class="btn btn-warning w-100 btn-pill py-3 fw-bold" style="font-size:1.05rem;">
                                <i class="fa-solid fa-paper-plane me-2"></i>Submit Registration
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.custom-input');
            const conditionalFields = document.querySelectorAll('.conditional-field');

            function evaluateConditions() {
                conditionalFields.forEach(field => {
                    const dependsOnKey = field.dataset.dependsOn;
                    const dependsOnVal = field.dataset.dependsVal;
                    const wasRequired = field.dataset.wasRequired === '1';
                    
                    const parentInput = document.querySelector(`[name="${dependsOnKey}"]`);
                    const inputInside = field.querySelector('.custom-input');

                    if (parentInput && parentInput.value === dependsOnVal) {
                        field.style.display = 'block';
                        if (wasRequired && inputInside) inputInside.required = true;
                    } else {
                        field.style.display = 'none';
                        if (inputInside) {
                            inputInside.required = false;
                            inputInside.value = ''; // clear value if hidden
                        }
                    }
                });
            }

            inputs.forEach(input => {
                input.addEventListener('change', evaluateConditions);
                input.addEventListener('input', evaluateConditions);
            });

            // Run once on load
            evaluateConditions();
        });
    </script>
</body>
</html>
