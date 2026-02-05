<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'root', '', 'attendance_system');

// Simple status check
$result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_name = 'checkin_status'");
$row = $result->fetch_assoc();

echo json_encode(['system_status' => $row['setting_value']]);
$conn->close();
?>