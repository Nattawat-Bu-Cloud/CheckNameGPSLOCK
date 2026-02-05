// ==========================================
// 1. ส่วนตั้งค่า (CONFIGURATION)
// ==========================================
// แก้ไขพิกัดตรงนี้ให้เป็นจุดที่ต้องการเช็คชื่อ
const TARGET_LAT = 13.756331;  // <--- ใส่ละติจูดจริง
const TARGET_LON = 100.501765; // <--- ใส่ลองจิจูดจริง
const ALLOWED_RADIUS = 200;    // รัศมีที่ยอมรับ (เมตร)

// ลิงก์ไปยังไฟล์ PHP หลังบ้าน
const API_URL = 'save.php';
const STATUS_URL = 'check_status.php';

// ==========================================
// 2. ส่วนเริ่มต้นทำงาน (INITIALIZATION)
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
    // แสดงวันที่ปัจจุบันแบบภาษาไทย
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateEl = document.getElementById('date-display');
    if(dateEl) dateEl.innerText = new Date().toLocaleDateString('th-TH', options);

    // ซ่อนหน้าจอโหลด (Splash Screen)
    setTimeout(() => {
        const splash = document.getElementById('splash-screen');
        if (splash) {
            splash.style.opacity = '0';
            setTimeout(() => splash.style.display = 'none', 500);
        }
    }, 1000);

    // ตรวจสอบว่าอาจารย์เปิดระบบหรือยัง
    await checkSystemStatus();
});

// ==========================================
// 3. ฟังก์ชันหลัก (CORE FUNCTIONS)
// ==========================================

// เช็คสถานะระบบ (เปิด/ปิด)
async function checkSystemStatus() {
    try {
        const res = await fetch(STATUS_URL);
        const data = await res.json();
        if (data.system_status === 'closed') {
            disableSystem("⛔ ขณะนี้ระบบปิดรับการลงชื่อแล้ว");
        }
    } catch (e) {
        console.error("Status check failed");
        // ถ้าเช็คไม่ได้ ให้ถือว่าทำงานต่อ (หรือจะล็อคก็ได้แล้วแต่ Policy)
    }
}

// ล็อคระบบเมื่อปิดรับ
function disableSystem(msg) {
    const btn = document.getElementById('submitBtn');
    const inputs = document.querySelectorAll('input');
    
    if(btn) {
        btn.disabled = true;
        btn.innerHTML = `<span>${msg}</span>`;
        btn.style.background = '#333';
        btn.style.cursor = 'not-allowed';
    }
    
    inputs.forEach(inp => inp.disabled = true);
    showStatus(msg, 'error');
}

// เริ่มต้นกระบวนการเช็คชื่อ (เมื่อกดปุ่ม)
async function startCheckin() {
    const nameInput = document.getElementById('fullname');
    const sidInput = document.getElementById('student_id');
    const btn = document.getElementById('submitBtn');

    const name = nameInput.value.trim();
    const sid = sidInput.value.trim();

    // 1. ตรวจสอบว่ากรอกครบไหม
    if (!name || !sid) {
        shakeCard();
        showStatus("⚠️ กรุณากรอกชื่อและรหัสนักศึกษาให้ครบ", "error");
        return;
    }

    // 2. ล็อคปุ่มและแสดงสถานะโหลด
    btn.disabled = true;
    btn.innerHTML = '<div class="loader" style="width:20px; height:20px; border-width:2px;"></div><span> กำลังค้นหาตำแหน่ง...</span>';
    showStatus("🛰️ กำลังดึงพิกัด GPS...", "normal");

    // 3. ขอพิกัด GPS
    if (!navigator.geolocation) {
        showStatus("❌ อุปกรณ์ของคุณไม่รองรับ GPS", "error");
        resetBtn();
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async (position) => {
            const userLat = position.coords.latitude;
            const userLon = position.coords.longitude;
            
            // 4. คำนวณระยะทาง (Geofencing)
            const dist = getDistance(userLat, userLon, TARGET_LAT, TARGET_LON);
            // console.log(`Distance Check: ${dist} meters`); // Uncomment เพื่อ Debug

            if (dist > ALLOWED_RADIUS) {
                showStatus(`❌ คุณอยู่นอกพื้นที่ (${Math.round(dist)} เมตร)`, "error");
                shakeCard();
                resetBtn();
                return;
            }

            // 5. ผ่านเกณฑ์ -> ส่งข้อมูลไปบันทึก
            showStatus("🔄 กำลังบันทึกข้อมูล...", "normal");
            await sendData(name, sid, userLat, userLon);
        },
        (err) => {
            let msg = "❌ ไม่สามารถระบุตำแหน่งได้";
            if(err.code === 1) msg = "❌ กรุณากด 'อนุญาต' (Allow) ให้เว็บเข้าถึงตำแหน่ง";
            else if(err.code === 2) msg = "❌ สัญญาณ GPS ไม่ดี กรุณาออกไปที่โล่ง";
            else if(err.code === 3) msg = "❌ หมดเวลาในการดึงพิกัด (Timeout)";
            
            showStatus(msg, "error");
            shakeCard();
            resetBtn();
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

// ส่งข้อมูลไปที่ PHP
async function sendData(name, sid, lat, lon) {
    const deviceId = getCanvasFingerprint();
    
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                student_id: sid,
                device_id: deviceId,
                lat: lat,
                lon: lon
            })
        });

        const result = await response.json();

        if (result.status === 'success') {
            showSuccessView();
        } else {
            showStatus("⚠️ " + result.message, "error");
            shakeCard();
            resetBtn();
        }
    } catch (error) {
        showStatus("❌ เชื่อมต่อ Server ไม่ได้", "error");
        resetBtn();
    }
}

// ==========================================
// 4. ฟังก์ชันช่วยคำนวณ (UTILITIES)
// ==========================================

// สูตร Haversine คำนวณระยะห่างโลก
function getDistance(lat1, lon1, lat2, lon2) {
    const R = 6371e3; // รัศมีโลก (เมตร)
    const p1 = lat1 * Math.PI/180;
    const p2 = lat2 * Math.PI/180;
    const dp = (lat2-lat1) * Math.PI/180;
    const dl = (lon2-lon1) * Math.PI/180;
    const a = Math.sin(dp/2)**2 + Math.cos(p1)*Math.cos(p2)*Math.sin(dl/2)**2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// สร้าง Fingerprint จาก Canvas (ระบุตัวตนอุปกรณ์)
function getCanvasFingerprint() {
    try {
        const c = document.createElement('canvas');
        const ctx = c.getContext('2d');
        const txt = "Browser-Checkin-v1.0";
        ctx.textBaseline = "top";
        ctx.font = "14px 'Arial'";
        ctx.textBaseline = "alphabetic";
        ctx.fillStyle = "#f60";
        ctx.fillRect(125,1,62,20);
        ctx.fillStyle = "#069";
        ctx.fillText(txt, 2, 15);
        ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
        ctx.fillText(txt, 4, 17);
        
        let hash = 0;
        const dataURL = c.toDataURL();
        for (let i = 0; i < dataURL.length; i++) {
            const char = dataURL.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(16);
    } catch (e) {
        return "unknown-device-" + Math.random().toString(36).substring(7);
    }
}

// ==========================================
// 5. ฟังก์ชันจัดการ UI (UI HELPERS)
// ==========================================

function showStatus(msg, type) {
    const el = document.getElementById('status-msg');
    if(el) {
        el.innerHTML = msg;
        el.style.opacity = '1';
        el.style.color = type === 'error' ? '#ff4444' : '#aaa';
    }
}

function showSuccessView() {
    const formView = document.getElementById('form-view');
    const statusMsg = document.getElementById('status-msg');
    const successView = document.getElementById('success-view');

    if(formView) formView.style.display = 'none'; // ใช้ style.display แทน class เพื่อความชัวร์
    if(statusMsg) statusMsg.style.display = 'none';
    if(successView) successView.style.display = 'flex';
}

function resetBtn() {
    const btn = document.getElementById('submitBtn');
    if(btn) {
        btn.disabled = false;
        btn.innerHTML = '<span>ยืนยันพิกัดและลงชื่อ</span> <i class="fas fa-location-arrow"></i>';
    }
}

function shakeCard() {
    const card = document.getElementById('main-card');
    if(card) {
        card.classList.remove('shake');
        void card.offsetWidth; // Trigger Reflow
        card.classList.add('shake');
    }
}