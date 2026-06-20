<?php
/**
 * index.php - Public Landing Page / Portal Hub
 */
session_start();
$setup_completed = file_exists(__DIR__ . '/config/db.php');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Management System</title>
    <meta name="description" content="Issue, manage, and verify academic certificates. Search and download your certificates instantly.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
        }
        body::before {
            content: '';
            position: absolute;
            width: 100%; height: 100%;
            top: 0; left: 0;
            background-image: linear-gradient(rgba(255,255,255,0.012) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.012) 1px, transparent 1px);
            background-size: 35px 35px;
            pointer-events: none;
            z-index: 1;
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
            pointer-events: none;
            animation: float 8s ease-in-out infinite;
        }
        .orb-1 { width: 400px; height: 400px; background: var(--primary); top: -100px; right: -100px; animation-delay: 0s; }
        .orb-2 { width: 350px; height: 350px; background: var(--success); bottom: -80px; left: -80px; animation-delay: 3s; }
        .orb-3 { width: 250px; height: 250px; background: var(--warning); top: 50%; left: 50%; animation-delay: 5s; }
        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }
        .hub-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            z-index: 10;
            width: 100%;
            max-width: 900px;
            overflow: hidden;
        }
        .portal-card {
            background: var(--bg-sidebar);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
        }
        .portal-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        .portal-card.admin:hover { border-color: var(--primary); background: rgba(37,99,235,0.05); }
        .portal-card.verify:hover { border-color: var(--success); background: rgba(16,185,129,0.05); }
        .portal-card.register:hover { border-color: var(--warning); background: rgba(245,158,11,0.05); }
        .portal-card.search:hover { border-color: var(--info); background: rgba(6,182,212,0.05); }
        .portal-card.status:hover { border-color: var(--success); background: rgba(16,185,129,0.05); }
        .portal-icon {
            width: 50px; height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center; justify-content: center;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
            font-size: 1.2rem;
        }
        .portal-card:hover .portal-icon { transform: scale(1.1) rotate(5deg); }
        .portal-icon.admin { background: rgba(37,99,235,0.1); color: var(--primary-light); border: 1px solid rgba(37,99,235,0.2); }
        .portal-icon.verify { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .portal-icon.register { background: rgba(245,158,11,0.1); color: var(--warning); border: 1px solid rgba(245,158,11,0.2); }
        .portal-icon.search { background: rgba(6,182,212,0.1); color: var(--info); border: 1px solid rgba(6,182,212,0.2); }
        .portal-icon.status { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .btn-portal {
            border-radius: var(--radius); padding: 0.5rem 1.5rem;
            font-weight: 600; width: 100%; margin-top: auto; transition: var(--transition);
        }
        .btn-admin { background: var(--primary); border: none; color: #fff; box-shadow: 0 4px 15px rgba(37,99,235,0.3); }
        .btn-admin:hover { background: var(--primary-dark); color: #fff; box-shadow: 0 6px 20px rgba(37,99,235,0.5); }
        .btn-verify { background: var(--success); border: none; color: #fff; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
        .btn-verify:hover { background: #059669; color: #fff; }
        .btn-register { background: var(--warning); border: none; color: #1e1e2d; box-shadow: 0 4px 15px rgba(245,158,11,0.3); }
        .btn-register:hover { background: #D97706; color: #fff; }
        .btn-search { background: var(--info); border: none; color: #fff; box-shadow: 0 4px 15px rgba(6,182,212,0.3); }
        .btn-search:hover { background: #0891B2; color: #fff; }
        .btn-status { background: var(--success); border: none; color: #fff; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
        .btn-status:hover { background: #059669; color: #fff; }
    </style>
</head>
<body>
    <!-- Animated orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="container d-flex justify-content-center p-2">
        <div class="hub-card p-4 animate-fade-up">
            <?php if (!$setup_completed): ?>
                <div class="alert mb-3 py-2 px-3 d-flex align-items-center justify-content-between" style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius);color:var(--text-primary);">
                    <div>
                        <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>
                        <strong>Setup Required</strong> — The system hasn't been configured yet.
                    </div>
                    <a href="setup.php" class="btn btn-warning btn-sm btn-pill px-3 fw-bold">Run Setup</a>
                </div>
            <?php endif; ?>

            <div class="text-center mb-4">
                <div style="width:50px;height:50px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:inline-flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;margin-bottom:0.8rem;box-shadow:0 0 20px rgba(37,99,235,0.3);">
                    <i class="fa-solid fa-certificate"></i>
                </div>
                <h2 class="fw-bold mb-1" style="font-family:var(--font-heading);font-weight:800;letter-spacing:-0.5px;">Certificate Management Hub</h2>
                <p style="color:var(--text-secondary);max-width:550px;margin:0 auto;font-size:0.9rem;">Upload templates, design certificates dynamically, issue secure verifiable credentials, and allow students to search and download them.</p>
            </div>

            <div class="row g-3 mb-2">
                <!-- Admin Portal -->
                <div class="col-md-6">
                    <div class="portal-card admin" onclick="location.href='admin/login.php'">
                        <div class="portal-icon admin">
                            <i class="fa-solid fa-user-shield"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Admin Portal</h6>
                        <p style="color:var(--text-secondary);font-size:0.75rem;min-height:35px;margin-bottom:1rem;">Manage events, design templates, import data, and issue certificates securely.</p>
                        <a href="admin/login.php" class="btn btn-admin btn-portal">Enter Admin</a>
                    </div>
                </div>
                <!-- Search Portal -->
                <div class="col-md-6">
                    <div class="portal-card search" onclick="location.href='search.php'">
                        <div class="portal-icon search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Search Certificate</h6>
                        <p style="color:var(--text-secondary);font-size:0.75rem;min-height:35px;margin-bottom:1rem;">Search by name, roll number, registration number, or certificate ID.</p>
                        <a href="search.php" class="btn btn-search btn-portal">Search Now</a>
                    </div>
                </div>
                <!-- Register Portal -->
                <div class="col-md-6">
                    <div class="portal-card register" onclick="location.href='register.php'">
                        <div class="portal-icon register">
                            <i class="fa-solid fa-user-plus"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Event Registration</h6>
                        <p style="color:var(--text-secondary);font-size:0.75rem;min-height:35px;margin-bottom:1rem;">Register for upcoming events, seminars, or competitions online.</p>
                        <a href="register.php" class="btn btn-register btn-portal">Register Now</a>
                    </div>
                </div>
                <!-- Track Status Portal -->
                <div class="col-md-6">
                    <div class="portal-card status" onclick="location.href='status.php'">
                        <div class="portal-icon status">
                            <i class="fa-solid fa-satellite-dish"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Track Status</h6>
                        <p style="color:var(--text-secondary);font-size:0.75rem;min-height:35px;margin-bottom:1rem;">Check your registration status and download your certificate using your Reference Number.</p>
                        <a href="status.php" class="btn btn-status btn-portal">Check Status</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
