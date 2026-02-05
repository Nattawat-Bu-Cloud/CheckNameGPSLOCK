<?php
header('Content-Type: application/json; charset=utf-8');

// Connect DB
$conn = new mysqli('localhost', 'root', '', 'attendance_system');
$conn->set_charset("utf8");

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Fail']);
    exit;
}

// Check system status first
$status_query = $conn->query("SELECT setting_value FROM system_settings WHERE setting_name = 'checkin_status'");
$sys_status = $status_query->fetch_assoc();

if ($sys_status['setting_value'] !== 'open') {
    echo json_encode(['status' => 'error', 'message' => '⛔ ระบบปิดรับการลงชื่อแล้ว']);
    exit;
}

// Get JSON Input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit;

$name = $input['name'];
$sid  = $input['student_id'];
$did  = $input['device_id'];
$lat  = $input['lat'];
$lon  = $input['lon'];
$date = date('Y-m-d');
$time = date('H:i:s');

// Insert Data
$sql = "INSERT INTO checkin_logs (fullname, student_id, device_id, checkin_date, checkin_time, lat, lon) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssdd", $name, $sid, $did, $date, $time, $lat, $lon);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => '✅ ลงชื่อเรียบร้อย']);
} else {
    // Error 1062 = Duplicate Entry
    if ($conn->errno == 1062) {
        echo json_encode(['status' => 'error', 'message' => '⚠️ เครื่องนี้ลงชื่อไปแล้ววันนี้']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $conn->error]);
    }
}

$stmt->close();
$conn->close();
?>