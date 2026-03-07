# Attendance Bridge – Spec gửi team (Sync users 2 chiều + Auto‑update + MSI)

Ngày: 2026-01-24

Mục tiêu: triển khai app C# “Attendance Bridge” chạy tại LAN để:
- Máy chấm công → Web: push logs (đã có)
- Web → Máy chấm công: sync users (mở rộng 2 chiều)
- App tự update (auto‑update)
- Đóng gói cài đặt MSI (WiX v4)

---

## 0) Quy ước chung

### Auth khuyến nghị
Dùng **HMAC** (giống middleware `attendance.agent` đang dùng cho push logs) để tránh phải cấp bearer token cho máy khách.

### Headers chuẩn
- `X-Device-Id`: string (vd `ronaldjack-1`)
- `X-Timestamp`: unix seconds hoặc ISO8601
- `X-Signature`: HMAC SHA256
- `Content-Type: application/json`
- `Accept: application/json`

### Nguyên tắc idempotency
- App có thể retry → API cần idempotent (ít nhất ở mức “nhận lại cũng không nhân đôi”).
- Với log: upsert theo (device_id, device_user_id, punched_at, event_type) hoặc server tự quy ước key.
- Với sync-status: server nhận nhiều lần thì update theo (device_id, started_at, sync_type) hoặc tạo record mới theo policy.

---

## 1) API: Sync users (Web → App → Máy chấm công)

### 1.1 GET users
**Endpoint**
- `GET /api/attendance-agent/users`

**Query params**
- `updated_since` (ISO8601, optional): chỉ lấy nhân viên thay đổi sau mốc này
- `per_page` (default 200)
- `page` (default 1)

**Quy ước lọc**
- Chỉ trả nhân viên có `attendance_code` != null/empty.
- `status != active` → app có thể disable user trên máy (không xoá).

**Response (JSON mẫu)**
```json
{
  "success": true,
  "data": [
    {
      "employee_id": 123,
      "attendance_code": "3380",
      "name": "Nguyễn Văn A",
      "status": "active",
      "department": "Kho",
      "updated_at": "2026-01-24T02:10:00Z"
    }
  ],
  "pagination": { "page": 1, "per_page": 200, "total": 1 },
  "meta": {
    "server_time": "2026-01-24T02:12:00Z",
    "next_updated_since": "2026-01-24T02:10:00Z"
  }
}
```

**Status codes**
- `200`: OK
- `401/403`: auth fail
- `429`: rate limit
- `500`: server error

### 1.2 POST sync status
**Endpoint**
- `POST /api/attendance-agent/sync-status`

**Mục tiêu**
App báo kết quả sync để admin xem lịch sử/điều tra lỗi.

**Body (JSON mẫu)**
```json
{
  "device_id": "ronaldjack-1",
  "app_version": "1.0.0",
  "sync_type": "users",
  "started_at": "2026-01-24T02:12:00Z",
  "finished_at": "2026-01-24T02:12:20Z",
  "result": "ok",
  "counts": {
    "fetched": 200,
    "created": 10,
    "updated": 5,
    "skipped": 180,
    "failed": 5
  },
  "errors": [
    { "attendance_code": "3380", "message": "Device refused update" }
  ]
}
```

**Response**
```json
{ "success": true }
```

---

## 2) Auto‑update (App tự kiểm tra bản mới + tải file)

### 2.1 GET latest version
**Endpoint**
- `GET /api/attendance-agent/bridge/latest`

**Query params**
- `channel` = `stable` | `beta` (default `stable`)
- `current_version` (optional): để server quyết định có cần update không

**Response (JSON mẫu)**
```json
{
  "success": true,
  "data": {
    "version": "1.0.3",
    "channel": "stable",
    "mandatory": false,
    "min_supported": "1.0.0",
    "released_at": "2026-01-24T00:00:00Z",
    "notes": "Fix timeout + improve batching",
    "download": {
      "url": "https://app.cuongdesign.net/downloads/AttendanceBridge/AttendanceBridge-1.0.3.msi",
      "sha256": "HEX_SHA256...",
      "size_bytes": 52428800
    }
  }
}
```

### 2.2 Nơi tải file
- Ưu tiên: static file qua Nginx (hoặc object storage S3/R2).
- App tải xong phải verify `sha256`.

### 2.3 Quy trình update phía app (gợi ý)
1) Poll `bridge/latest` mỗi 6–12 giờ.
2) Nếu `version > current_version`:
   - download MSI
   - verify `sha256`
   - chạy silent install
   - restart service

**Silent install gợi ý**
- `msiexec /i AttendanceBridge-1.0.3.msi /qn /norestart`

---

## 3) MSI Installer

### 3.1 Tool lựa chọn
- Chọn **WiX Toolset v4**.

### 3.2 MSI phải làm gì
- Copy binaries vào: `C:\Program Files\AttendanceBridge\`
- Tạo folder data: `%ProgramData%\AttendanceBridge\`
- Tạo file config: `%ProgramData%\AttendanceBridge\appsettings.json` (nếu chưa tồn tại)
- Create + Start Windows Service: `AttendanceBridge`
- Uninstall:
  - Stop + delete service
  - (Policy) giữ lại config/log hoặc xoá tuỳ option

### 3.3 Quy ước đường dẫn log/state
- Logs: `%ProgramData%\AttendanceBridge\logs\yyyy-MM-dd.log`
- State: `%ProgramData%\AttendanceBridge\state.json`

---

## 4) Câu hỏi cần chốt với team (để code đồng bộ)

- “DeviceId” đặt theo từng chi nhánh/thiết bị hay theo máy chạy app?
- Mapping users: `attendance_code` có luôn là số không? có cho phép chữ?
- Disable user: dùng flag nào ở thiết bị? (tuỳ SDK)
- App có cần UI tray ngay giai đoạn 1 không, hay service-only trước?

---

## 5) Checklist nghiệm thu nhanh

- [ ] GET users trả đúng danh sách nhân viên có `attendance_code`
- [ ] App sync tạo/update user trên máy chấm công
- [ ] POST sync-status ghi nhận kết quả
- [ ] Auto‑update tải đúng MSI + verify SHA256 + update thành công
- [ ] MSI cài xong service tự chạy, reboot vẫn chạy
