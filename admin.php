<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// --- Config ---
$ADMIN_USER = "admin";
$ADMIN_PASS = "1234";
$host = 'localhost'; $user = 'root'; $pass = ''; $db = 'attendance_system';

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8");

// --- API Handler ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    // Login logic
    if ($action === 'login') {
        if ($_POST['user'] === $ADMIN_USER && $_POST['pass'] === $ADMIN_PASS) {
            $_SESSION['is_admin'] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Login Failed']);
        }
        exit;
    }

    // Auth Check
    if (!isset($_SESSION['is_admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    // Get/Set System Status
    if ($action === 'get_status') {
        $res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_name = 'checkin_status'");
        echo json_encode(['status' => 'success', 'data' => $res->fetch_assoc()['setting_value']]);
        exit;
    }
    if ($action === 'toggle_status') {
        $new_val = $_POST['val'];
        $conn->query("UPDATE system_settings SET setting_value = '$new_val' WHERE setting_name = 'checkin_status'");
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Get Checkin Dates
    if ($action === 'get_dates') {
        $sql = "SELECT DISTINCT checkin_date FROM checkin_logs ORDER BY checkin_date DESC";
        $result = $conn->query($sql);
        $dates = [];
        while($row = $result->fetch_assoc()) {
            $dates[] = [
                'raw' => $row['checkin_date'],
                'display' => date('d/m/Y', strtotime($row['checkin_date']))
            ];
        }
        echo json_encode(['status' => 'success', 'data' => $dates]);
        exit;
    }

    // Get Logs
    if ($action === 'get_logs') {
        $date = $_POST['date'];
        $sql = "SELECT * FROM checkin_logs WHERE checkin_date = ? ORDER BY checkin_time ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $res = $stmt->get_result();
        $logs = [];
        while($row = $res->fetch_assoc()) $logs[] = $row;
        echo json_encode(['status' => 'success', 'data' => $logs]);
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success']);
        exit;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root { --primary: #6C63FF; --secondary: #2A2D3E; --bg: #1F2029; --text: #fff; --success: #00C851; --danger: #ff4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Kanit', sans-serif; background: var(--bg); color: var(--text); overflow: hidden; height: 100vh; }

        /* Splash */
        #splash-screen { position: fixed; width: 100%; height: 100%; background: var(--bg); z-index: 999; display: flex; justify-content: center; align-items: center; flex-direction: column; transition: opacity 0.5s; }
        .loader { width: 50px; height: 50px; border: 5px solid #FFF; border-bottom-color: var(--primary); border-radius: 50%; animation: rot 1s linear infinite; }
        @keyframes rot { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Login */
        #login-view { display: none; height: 100vh; justify-content: center; align-items: center; background: radial-gradient(circle, #2b2e44, #1f2029); }
        .login-card { background: rgba(255,255,255,0.05); padding: 40px; border-radius: 20px; width: 350px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
        input { width: 100%; padding: 12px; margin: 10px 0; background: rgba(0,0,0,0.2); border: 1px solid #444; color: #fff; border-radius: 8px; outline: none; }
        input:focus { border-color: var(--primary); }
        .btn { background: var(--primary); color: #fff; padding: 12px; width: 100%; border: none; border-radius: 50px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .btn:hover { transform: scale(1.05); }

        /* Dashboard */
        #dashboard-view { display: none; height: 100vh; display: flex; }
        .main { flex: 1; padding: 20px; display: flex; flex-direction: column; position: relative; }
        .sidebar { width: 250px; background: var(--secondary); padding: 20px; border-left: 1px solid #333; overflow-y: auto; }

        /* Toggle Switch */
        .system-toggle { position: absolute; top: 20px; left: 20px; display: flex; align-items: center; background: rgba(0,0,0,0.3); padding: 10px 20px; border-radius: 30px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; margin-right: 10px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--danger); transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background: var(--success); }
        input:checked + .slider:before { transform: translateX(24px); }

        /* Table */
        .table-wrap { margin-top: 60px; height: 100%; overflow-y: auto; background: rgba(255,255,255,0.02); border-radius: 15px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { color: #888; }
        
        .date-item { padding: 15px; margin-bottom: 10px; background: rgba(255,255,255,0.05); border-radius: 10px; cursor: pointer; display: flex; justify-content: space-between; }
        .date-item:hover, .date-item.active { background: var(--primary); }
        .logout-btn { position: absolute; top: 20px; right: 20px; background: transparent; color: var(--danger); border: 1px solid var(--danger); padding: 5px 15px; border-radius: 20px; cursor: pointer; }
    </style>
</head>
<body>

    <div id="splash-screen">
        <div class="loader"></div>
        <h3 style="margin-top:20px;">CHECK-IN ADMIN</h3>
    </div>

    <div id="login-view">
        <div class="login-card">
            <h1 style="margin-bottom:20px;">Login</h1>
            <input type="text" id="user" placeholder="Username">
            <input type="password" id="pass" placeholder="Password">
            <button class="btn" onclick="doLogin()">ENTER</button>
        </div>
    </div>

    <div id="dashboard-view">
        <div class="main">
            <div class="system-toggle">
                <label class="switch">
                    <input type="checkbox" id="sysToggle" onchange="toggleSystem()">
                    <span class="slider"></span>
                </label>
                <span id="statusText">Closed</span>
            </div>
            
            <button class="logout-btn" onclick="doLogout()">Logout</button>

            <div class="table-wrap">
                <h2 id="tableTitle" style="color:var(--primary); margin-bottom:20px;">Select Date...</h2>
                <table>
                    <thead><tr><th>Time</th><th>ID</th><th>Name</th><th>Device Hash</th></tr></thead>
                    <tbody id="logsBody"></tbody>
                </table>
            </div>
        </div>

        <div class="sidebar">
            <h3 style="margin-bottom:20px; text-align:center;">History</h3>
            <div id="dateList"></div>
        </div>
    </div>

    <script>
        const API = 'admin.php';

        window.onload = () => {
            setTimeout(() => {
                document.getElementById('splash-screen').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('splash-screen').style.display = 'none';
                    checkSession();
                }, 500);
            }, 1000);
        };

        function switchView(view) {
            document.getElementById('login-view').style.display = view === 'login' ? 'flex' : 'none';
            document.getElementById('dashboard-view').style.display = view === 'dash' ? 'flex' : 'none';
            if (view === 'dash') initDash();
        }

        async function callApi(data) {
            const res = await fetch(API, { method: 'POST', body: data });
            return await res.json();
        }

        async function doLogin() {
            const fd = new FormData();
            fd.append('action', 'login');
            fd.append('user', document.getElementById('user').value);
            fd.append('pass', document.getElementById('pass').value);
            
            const res = await callApi(fd);
            if (res.status === 'success') switchView('dash');
            else alert('Login Failed');
        }

        function checkSession() {
            <?php if(isset($_SESSION['is_admin'])): ?>
                switchView('dash');
            <?php else: ?>
                switchView('login');
            <?php endif; ?>
        }

        // --- Dash Logic ---
        function initDash() {
            loadStatus();
            loadDates();
        }

        async function loadStatus() {
            const fd = new FormData(); fd.append('action', 'get_status');
            const res = await callApi(fd);
            const open = res.data === 'open';
            
            document.getElementById('sysToggle').checked = open;
            const txt = document.getElementById('statusText');
            txt.innerText = open ? 'Open' : 'Closed';
            txt.style.color = open ? '#00C851' : '#ff4444';
        }

        async function toggleSystem() {
            const val = document.getElementById('sysToggle').checked ? 'open' : 'closed';
            const fd = new FormData(); 
            fd.append('action', 'toggle_status'); fd.append('val', val);
            await callApi(fd);
            loadStatus();
        }

        async function loadDates() {
            const fd = new FormData(); fd.append('action', 'get_dates');
            const res = await callApi(fd);
            const list = document.getElementById('dateList');
            list.innerHTML = '';
            
            res.data.forEach(d => {
                const el = document.createElement('div');
                el.className = 'date-item';
                el.innerHTML = `<span>${d.display}</span> <i class="fas fa-chevron-right"></i>`;
                el.onclick = () => loadLogs(d.raw, el);
                list.appendChild(el);
            });
            // Auto load first
            if(res.data.length > 0) loadLogs(res.data[0].raw, list.firstChild);
        }

        async function loadLogs(date, el) {
            document.querySelectorAll('.date-item').forEach(e => e.classList.remove('active'));
            if(el) el.classList.add('active');
            
            document.getElementById('tableTitle').innerText = 'Data: ' + date;
            
            const fd = new FormData();
            fd.append('action', 'get_logs'); fd.append('date', date);
            const res = await callApi(fd);
            
            const body = document.getElementById('logsBody');
            body.innerHTML = '';
            
            if(res.data.length === 0) body.innerHTML = '<tr><td colspan="4">No Data</td></tr>';
            
            res.data.forEach(r => {
                body.innerHTML += `
                    <tr>
                        <td>${r.checkin_time}</td>
                        <td><span style="background:#6C63FF; padding:2px 8px; border-radius:4px;">${r.student_id}</span></td>
                        <td>${r.fullname}</td>
                        <td style="color:#888; font-family:monospace;">${r.device_id.substring(0,8)}...</td>
                    </tr>`;
            });
        }

        async function doLogout() {
            const fd = new FormData(); fd.append('action', 'logout');
            await callApi(fd);
            location.reload();
        }
    </script>
</body>
</html>