<?php
session_start();

require_once __DIR__ . '/src/backend/db/Database.php';
require_once __DIR__ . '/src/backend/db/DriversRepository.php';

$error = '';
$success = '';
$loginType = 'manager'; // default
$returnTo = '';

function isSafeReturnTarget(string $target): bool
{
    if ($target === '') {
        return false;
    }

    if (strpos($target, '://') !== false || substr($target, 0, 2) === '//') {
        return false;
    }

    return true;
}

// if suer already logged in
if (isset($_SESSION['username']) || isset($_SESSION['driver_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
        header('Location: src/frontend/pages/tracking.php');
    } else {
        header('Location: src/frontend/pages/index.php');
    }
    exit();
}

// logout message
$logout = isset($_GET['logout']) ? (int)$_GET['logout'] : 0;
if ($logout === 1) {
    $success = 'You have been logged out successfully.';
}

// Handle query parameters for errors
$err = isset($_GET['err']) ? (int)$_GET['err'] : 0;
if ($err === 2) {
    $error = 'Session expired. Please log in again.';
}

if (isset($_GET['return']) && isSafeReturnTarget((string)$_GET['return'])) {
    $returnTo = trim((string)$_GET['return']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginType = isset($_POST['login_type']) ? $_POST['login_type'] : 'manager';
    if (isset($_POST['return']) && isSafeReturnTarget((string)$_POST['return'])) {
        $returnTo = trim((string)$_POST['return']);
    }

    if ($loginType === 'manager') {
        // Manager login
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';

        if (empty($username) || empty($password)) {
            $error = 'Username and password are required.';
        } else {
            // Simple manager authentication (in production, use hashed passwords)
            // For demo purposes, accepting manager credentials
            $managerUsername = 'admin';
            $managerPassword = 'admin123';

            if ($username === $managerUsername && $password === $managerPassword) {
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'manager';
                $_SESSION['login_time'] = time();
                
                $success = 'Manager login successful!';
                header('Location: ' . ($returnTo !== '' ? $returnTo : 'src/frontend/pages/index.php'));
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        }
    } else {
        // Driver login
        $driver_id = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;

        if ($driver_id <= 0) {
            $error = 'Please select a valid driver.';
        } else {
            try {
                $driversRepo = new DriversRepository();
                $driver = $driversRepo->findById($driver_id);

                if ($driver === null) {
                    $error = 'Driver not found.';
                } else {
                    $_SESSION['driver_id'] = $driver_id;
                    $_SESSION['driver_name'] = $driver['name'];
                    $_SESSION['role'] = 'driver';
                    $_SESSION['login_time'] = time();
                    
                    $success = 'Driver login successful!';
                    header('Location: ' . ($returnTo !== '' ? $returnTo : 'src/frontend/pages/tracking.php'));
                    exit();
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get all drivers for driver login dropdown
$drivers = [];
try {
    $driversRepo = new DriversRepository();
    $drivers = $driversRepo->getAll();
} catch (Exception $e) {
    $drivers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>MediRun - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Sans:wght@300;400;500;600;700&family=Orbitron:wght@400;500;700&family=Unica+One&family=Anta&display=swap" rel="stylesheet"/>
    <link href="src/frontend/css/style.css" rel="stylesheet"/>
    <style>
        /* Login page specific styles */
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: var(--nuit);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 40px 36px;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(61,106,193,0.3), transparent);
            filter: blur(40px);
            pointer-events: none;
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
            position: relative;
            z-index: 1;
        }

        .logo-box {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            font-weight: 700;
            color: var(--abysse);
        }

        .login-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .login-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-family: 'Unica One', sans-serif;
        }

        .login-form {
            position: relative;
            z-index: 1;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-family: 'Unica One', sans-serif;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255, 247, 227, 0.04);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-main);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            transition: all 0.2s;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--horizon);
            background: rgba(61, 106, 193, 0.1);
            box-shadow: 0 0 0 3px rgba(61, 106, 193, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        /* Tabs for login type */
        .login-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .login-tab {
            flex: 1;
            padding: 12px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-family: 'Anta', sans-serif;
            font-size: 13px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .login-tab:hover {
            color: var(--text-main);
        }

        .login-tab.active {
            color: var(--horizon);
            border-bottom-color: var(--horizon);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .error-message {
            background: rgba(224, 82, 82, 0.1);
            border-left: 3px solid var(--red);
            padding: 12px 14px;
            border-radius: 6px;
            color: var(--red);
            font-size: 13px;
            margin-bottom: 18px;
            font-family: 'DM Sans', sans-serif;
        }

        .success-message {
            background: rgba(46, 204, 143, 0.1);
            border-left: 3px solid var(--green);
            padding: 12px 14px;
            border-radius: 6px;
            color: var(--green);
            font-size: 13px;
            margin-bottom: 18px;
            font-family: 'DM Sans', sans-serif;
        }

        .login-btn {
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, var(--marine), var(--horizon));
            border: none;
            border-radius: 8px;
            color: #fff;
            font-family: 'Anta', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            letter-spacing: 0.03em;
        }

        .login-btn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
        }

        .demo-info {
            margin-top: 24px;
            padding: 12px 14px;
            background: rgba(61, 106, 193, 0.08);
            border: 1px solid rgba(61, 106, 193, 0.15);
            border-radius: 6px;
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .demo-label {
            font-weight: 600;
            color: var(--horizon);
            margin-bottom: 4px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 20px;
            }

            .login-title {
                font-size: 24px;
            }

            .form-input,
            .form-select {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-box">🚚</div>
                <div class="login-title">MediRun</div>
                <div class="login-subtitle">Delivery Management</div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Login Type Tabs -->
            <div class="login-tabs">
                <button class="login-tab active" data-tab="manager" onclick="switchTab(event, 'manager')">
                    👔 Manager
                </button>
                <button class="login-tab" data-tab="driver" onclick="switchTab(event, 'driver')">
                    🧑‍💼 Driver
                </button>
            </div>

            <form class="login-form" method="POST">
                <input type="hidden" name="login_type" id="loginTypeInput" value="manager">
                <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnTo); ?>">

                <!-- Manager Login Tab -->
                <div id="manager" class="tab-content active">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-input" 
                            placeholder="Enter your username"
                            required
                        />
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter your password"
                            required
                        />
                    </div>

                    <button type="submit" class="login-btn">Login as Manager</button>
                </div>

                <!-- Driver Login Tab -->
                <div id="driver" class="tab-content">
                    <div class="form-group">
                        <label class="form-label">Select Driver</label>
                        <select name="driver_id" class="form-select">
                            <option value="">-- Choose your name --</option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?php echo htmlspecialchars($driver['id']); ?>">
                                    <?php echo htmlspecialchars($driver['name']); ?>
                                    (ID: <?php echo htmlspecialchars($driver['id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="login-btn">Login as Driver</button>
                </div>
            </form>

            <!-- Demo Information -->
            <div class="demo-info">
                <div class="demo-label">Demo Credentials:</div>
                <div>Manager: admin / admin123</div>
                <div>Driver: Select any from the list</div>
            </div>

            <div class="login-footer">
                <p>© 2026 MediRun. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        function switchTab(event, tab) {
            event.preventDefault();

            // Update active tabs - use closest() in case icon inside button was clicked
            document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
            const btn = event.target.closest('.login-tab');
            if (btn) btn.classList.add('active');

            // Update active content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab).classList.add('active');

            // Update hidden input
            document.getElementById('loginTypeInput').value = tab;

            // Update form requirements
            const form = document.querySelector('.login-form');
            const usernameInput = form.querySelector('input[name="username"]');
            const passwordInput = form.querySelector('input[name="password"]');
            const driverSelect = form.querySelector('select[name="driver_id"]');

            if (tab === 'manager') {
                usernameInput.required = true;
                passwordInput.required = true;
                driverSelect.required = false;
            } else {
                usernameInput.required = false;
                passwordInput.required = false;
                driverSelect.required = true;
            }
        }

        // Auto-focus first input on page load
        document.addEventListener('DOMContentLoaded', () => {
            const firstInput = document.querySelector('.tab-content.active input, .tab-content.active select');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Allow Enter key to submit
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.querySelector('.login-btn').click();
                }
            });
        });
    </script>
</body>
</html>
