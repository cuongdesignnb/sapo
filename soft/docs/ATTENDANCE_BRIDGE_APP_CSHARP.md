# Phương án làm “App trung gian” C# (Attendance Bridge) như Kiot

Mục tiêu của tài liệu này: mô tả cách làm một ứng dụng C# chạy tại LAN (máy tính gần máy chấm công) để **đồng bộ dữ liệu 1 chiều (máy → web)** và có thể mở rộng **2 chiều (web → máy)**. Viết theo kiểu “người không biết gì cũng làm được”.

Ngày cập nhật: 2026-01-23

---

## 1) Tổng quan mô hình

### Luồng chính (1 chiều, đang dùng tốt)
1. Máy chấm công (Ronald Jack / ZKTeco…) trong LAN.
2. App trung gian chạy trên Windows trong LAN.
3. App đọc log từ thiết bị qua TCP/UDP.
4. App gửi log lên VPS qua HTTPS API.

### Luồng nâng cấp (2 chiều)
Ngoài việc đẩy log, app còn:
- Lấy danh sách nhân viên từ web (API) → tạo/cập nhật user trên máy chấm công.
- (Tuỳ chọn) Đồng bộ giờ máy chấm công theo giờ chuẩn.

---

## 2) Vì sao nên làm app C# (so với script/agent PHP)

- Dễ “đóng gói” thành file cài đặt (.exe/.msi), người dùng chỉ Next–Next–Finish.
- Dễ chạy 24/7 như Windows Service (tự chạy khi bật máy).
- Dễ auto-update, log, giao diện tối thiểu.
- Kiểm soát mạng/timeout tốt, ít lỗi môi trường PHP.

Gợi ý: vẫn giữ agent PHP làm phương án dự phòng. C# app là hướng sản phẩm hoá.

---

## 3) Kiến trúc đề xuất

### Thành phần
- **AttendanceBridge.Service** (Windows Service): chạy nền, tự động sync.
- **AttendanceBridge.UI** (WinForms/WPF/Tray): cấu hình + xem trạng thái (optional).
- **AttendanceBridge.Core**: thư viện chung (giao tiếp thiết bị, API client, retry, config).

### Dữ liệu cấu hình (1 file)
- `appsettings.json` lưu:
  - `ApiBaseUrl`: ví dụ `https://app.cuongdesign.net`
  - `ApiAuth`: HMAC key (giống middleware attendance.agent) **hoặc** Bearer token (tuỳ chọn)
  - `DeviceIp`, `DevicePort`
  - `SyncIntervalSeconds`
  - `BatchSize` (vd 500)
  - `LastSyncStatePath` (để chỉ đẩy log mới)

### Lưu trạng thái
- Lưu “mốc sync gần nhất” vào:
  - file JSON trong `%ProgramData%\AttendanceBridge\state.json`
  - hoặc SQLite local (ổn định hơn nếu muốn lịch sử).

---

## 4) Yêu cầu môi trường (cho người dùng cuối)

### Windows hỗ trợ
- Windows 10/11, hoặc Windows Server 2019/2022.

### Phần mềm cần cài
Khuyến nghị **không bắt người dùng cài .NET** bằng cách publish self-contained.

Có 2 lựa chọn phát hành:

#### Lựa chọn A (khuyến nghị): self-contained
- App đi kèm .NET runtime → người dùng chỉ cài app.
- File phát hành lớn hơn nhưng “cài phát chạy”.

#### Lựa chọn B: cài .NET Runtime
- Cài **.NET Desktop Runtime** (hoặc .NET Runtime) trước.
- App nhẹ hơn.

---

## 5) Quy trình cài đặt cho người không biết gì (Step-by-step)

### Bước 0: Chuẩn bị thông tin
- IP máy chấm công: ví dụ `192.168.1.222`
- Port máy chấm công: thường `4370`
- Domain web: `https://app.cuongdesign.net`
- Khoá HMAC hoặc token (do admin cấp)

### Bước 1: Tải bộ cài
- Tải `AttendanceBridgeSetup.msi` (hoặc `AttendanceBridgeSetup.exe`).

### Bước 2: Cài đặt
- Mở file cài đặt → Next → Install.
- Sau khi cài xong, app sẽ có:
  - Shortcut “Attendance Bridge”
  - Service “AttendanceBridge”

### Bước 3: Mở cấu hình lần đầu
- Mở app cấu hình (UI) hoặc mở file cấu hình:
  - `%ProgramData%\AttendanceBridge\appsettings.json`

Ví dụ cấu hình tối thiểu:
```json
{
  "ApiBaseUrl": "https://app.cuongdesign.net",
  "Agent": {
    "HmacKey": "...",
    "DeviceId": "ronaldjack-1"
  },
  "Device": {
    "Ip": "192.168.1.222",
    "Port": 4370,
    "TimeoutMs": 8000
  },
  "Sync": {
    "IntervalSeconds": 60,
    "BatchSize": 500,
    "OnlyNew": true
  }
}
```

### Bước 4: Cho phép firewall Windows (nếu hỏi)
- Chọn Allow access.

### Bước 5: Test kết nối
Trong UI bấm:
- “Test Device” (ping + handshake)
- “Test API” (gọi `/api/test` hoặc endpoint agent)

### Bước 6: Start service
- UI có nút Start/Stop, hoặc mở Services.msc → start “AttendanceBridge”.

---

## 6) Quy trình vận hành (người dùng chỉ cần nhìn màu)

### Trạng thái hiển thị
- **Green**: OK (đang sync, request 200)
- **Yellow**: warning (API chậm, retry)
- **Red**: lỗi (mất mạng, sai key, sai IP thiết bị)

### Log
- Log file: `%ProgramData%\AttendanceBridge\logs\yyyy-MM-dd.log`
- Có nút “Copy log” để gửi kỹ thuật.

---

## 7) API phía server cần có (để app C# hoạt động)

### 7.1. Push logs (đã có)
- `POST /api/attendance-agent/push-logs`
  - Auth: HMAC signature (khuyến nghị)
  - Payload: batch logs

### 7.2. (Tuỳ chọn) Pull users để sync 2 chiều
- `GET /api/attendance-agent/users`
  - Trả danh sách nhân viên: `{ attendance_code, name, ... }`

### 7.3. (Tuỳ chọn) Push sync result
- `POST /api/attendance-agent/sync-status`
  - Gửi thống kê: số log gửi, số user tạo/cập nhật, lỗi.

---

## 8) Đồng bộ 2 chiều: quy trình chi tiết

### A) Máy → Web (logs)
1. Service đọc log từ thiết bị.
2. Lọc log mới theo “mốc sync” (timestamp hoặc id).
3. Chia batch 500.
4. Ký HMAC, POST lên API.
5. Server upsert vào `attendance_logs`.
6. Service cập nhật state.json.

### B) Web → Máy (users)
1. Service gọi `GET /api/attendance-agent/users`.
2. Đọc danh sách user trên máy chấm công.
3. So sánh:
   - Nếu thiếu: create user.
   - Nếu tên thay đổi: update user.
   - Không xoá user mặc định (an toàn).
4. Ghi log thống kê.

---

## 9) Bảo mật

Khuyến nghị dùng **HMAC** (giống middleware hiện tại) thay vì bearer token.

- Không lưu key trong registry dạng plain text.
- `appsettings.json` nên chỉ đọc được bởi admin.
- Thêm “DeviceId” để server biết máy nào đang push.
- Rate limit + replay protection (timestamp + nonce).

---

## 10) Build/Release cho dev (nội bộ)

### Yêu cầu cho dev
- Visual Studio 2022 hoặc `dotnet` CLI.
- .NET SDK 8 (khuyến nghị).

### Publish self-contained (Windows x64)
```bash
dotnet publish -c Release -r win-x64 --self-contained true /p:PublishSingleFile=true
```

### Đóng gói cài đặt
- Dùng WiX Toolset hoặc Advanced Installer.
- Tạo Windows Service (sc.exe) trong bước install.

---

## 10.1) Quy trình DEV chi tiết: từ cài môi trường → viết app chạy được

Phần này dành cho dev (người mới cũng làm theo được).

### A) Cài môi trường lập trình (máy dev)

1) Cài Visual Studio 2022 (Community cũng được)
- Chọn workload:
  - **.NET desktop development**
  - **ASP.NET and web development** (không bắt buộc, nhưng có ích)
  - **Desktop development with C++** (không bắt buộc)

2) Cài .NET SDK
- Khuyến nghị: **.NET SDK 8.x**
- Kiểm tra sau khi cài:
```powershell
dotnet --info
```

3) Công cụ đóng gói
- Chọn 1 trong 2:
  - **WiX Toolset v4** (free)
  - **Advanced Installer** (dễ dùng, có bản free/paid)

4) (Tuỳ chọn) Công cụ log
- Khuyến nghị: Serilog

### B) Tạo solution & project skeleton

Tạo 1 solution gồm 3 project:

1) Core library
```powershell
mkdir AttendanceBridge; cd AttendanceBridge
dotnet new sln -n AttendanceBridge
dotnet new classlib -n AttendanceBridge.Core
dotnet sln add .\AttendanceBridge.Core\AttendanceBridge.Core.csproj
```

2) Worker Service chạy nền (khuyến nghị nhất cho Windows Service)
```powershell
dotnet new worker -n AttendanceBridge.Service
dotnet sln add .\AttendanceBridge.Service\AttendanceBridge.Service.csproj
dotnet add .\AttendanceBridge.Service\AttendanceBridge.Service.csproj reference .\AttendanceBridge.Core\AttendanceBridge.Core.csproj
```

3) UI cấu hình (tuỳ chọn)
- Nếu cần UI nhanh: WinForms
```powershell
dotnet new winforms -n AttendanceBridge.UI
dotnet sln add .\AttendanceBridge.UI\AttendanceBridge.UI.csproj
dotnet add .\AttendanceBridge.UI\AttendanceBridge.UI.csproj reference .\AttendanceBridge.Core\AttendanceBridge.Core.csproj
```

### C) Viết Windows Service (bản chạy được)

Mục tiêu: chạy như Windows Service thật sự.

1) Trong `AttendanceBridge.Service`, bật chế độ Windows Service
- Trong `Program.cs` dùng `UseWindowsService()`.

2) Thiết kế vòng lặp sync
- Cứ mỗi `IntervalSeconds`:
  - đọc log từ máy chấm công
  - filter log mới (dựa state)
  - gửi batch lên API
  - cập nhật state

3) Cấu hình bằng `appsettings.json`
- Cho phép override bằng file ở `%ProgramData%\AttendanceBridge\appsettings.json`.

4) Logging
- Ghi ra `%ProgramData%\AttendanceBridge\logs\...`.

### D) Giao tiếp máy chấm công (phần khó nhất) – 2 phương án thực tế

Bạn sẽ chọn 1 trong 2 đường:

#### Phương án 1 (khuyến nghị nếu có SDK): dùng SDK hãng (ZKTeco/Realand/Ronald Jack)
- Ưu điểm: ổn định, ít tự xử lý protocol.
- Nhược: phụ thuộc SDK/COM/driver, đôi khi chỉ chạy Windows.

Quy trình:
1) Xin/cài SDK chính hãng (thường là DLL/COM `zkemkeeper` hoặc tương tự).
2) Tạo wrapper trong `AttendanceBridge.Core`:
   - `IAttendanceDeviceClient`
   - `Connect()`, `GetLogs(from)`, `GetUsers()`, `UpsertUser()`
3) Trong service chỉ gọi interface (để dễ thay SDK).

#### Phương án 2: tự implement protocol (giống bạn đã làm với PHP ZKLib)
- Ưu điểm: chủ động, không phụ thuộc COM.
- Nhược: tốn công, phải test kỹ.

Quy trình:
1) Dựa theo logic đã ổn ở PHP agent (`CMD_PREPARE_DATA`, UDP buffer, chunking...).
2) Port sang C# (TCP/UDP) và test với thiết bị thật.

Gợi ý: nếu mục tiêu là “ra sản phẩm nhanh”, thường chọn SDK.

### E) Viết API client (đẩy log lên server)

1) Chuẩn hoá model gửi lên server
- Mỗi record tối thiểu:
  - `device_id`
  - `device_user_id`
  - `punched_at` (ISO8601)
  - `event_type` (in/out)
  - `raw` (object)

2) Auth
- Khuyến nghị dùng **HMAC** giống middleware `attendance.agent`.
- App ký request: timestamp + body + device_id.

3) Retry/backoff
- Nếu API lỗi 5xx: retry.
- Nếu 4xx: log và dừng retry (thường sai key).

### F) State “chỉ sync log mới” (bắt buộc)

Lưu state:
- `last_log_id` hoặc `last_punched_at` theo từng device.

Tối thiểu:
- File JSON ở `%ProgramData%\AttendanceBridge\state.json`.

### G) Chạy thử ở chế độ Console trước khi làm Service

Trong quá trình dev, chạy:
```powershell
dotnet run --project .\AttendanceBridge.Service
```

Khi OK rồi mới publish và install service.

---

## 10.2) Cài đặt Windows Service (cụ thể lệnh)

### Cách 1: publish rồi dùng `sc.exe` (nhanh)

1) Publish
```powershell
dotnet publish .\AttendanceBridge.Service\AttendanceBridge.Service.csproj -c Release -r win-x64 --self-contained true
```

2) Copy thư mục publish lên máy chạy thật
- Ví dụ: `C:\Program Files\AttendanceBridge\`

3) Tạo service
```powershell
sc.exe create AttendanceBridge binPath= "\"C:\\Program Files\\AttendanceBridge\\AttendanceBridge.Service.exe\"" start= auto
```

4) Start service
```powershell
sc.exe start AttendanceBridge
```

5) Xem log
- `%ProgramData%\AttendanceBridge\logs\...`

6) Gỡ service
```powershell
sc.exe stop AttendanceBridge
sc.exe delete AttendanceBridge
```

### Cách 2: cài qua MSI (khuyến nghị cho người dùng)

Trong installer:
- Copy file app
- Tạo folder `%ProgramData%\AttendanceBridge\`
- Drop `appsettings.json` mẫu
- Create service + start

---

## 10.3) Tối thiểu cần làm để giống “Kiot app”

Để trải nghiệm giống Kiot (một app trung gian có icon):

1) Tray app (UI) nhỏ gọn
- Chỉ cần:
  - Status: Connected/Disconnected
  - Last sync time
  - Buttons: Test Device, Test API, Start/Stop
  - Open logs

2) Auto-update (giai đoạn 2)
- App ping endpoint `/api/attendance-agent/bridge-latest` để biết version mới.
- Download file mới, restart service.


---

## 11) Troubleshooting (các lỗi hay gặp)

### Lỗi: không đọc được log
- Kiểm tra IP/port thiết bị.
- Kiểm tra máy chấm công có bật TCP 4370.
- Kiểm tra firewall LAN.

### Lỗi: web không thấy log
- Kiểm tra app có POST 200 không.
- Kiểm tra server nhận log (DB có record).
- Mapping nhân viên: `attendance_code` phải trùng `device_user_id`.

### Lỗi giờ log sai (năm 213x)
- Kiểm tra giờ trên máy chấm công.
- Thêm chức năng “Sync time” để set giờ máy.

---

## 12) Lộ trình triển khai thực tế (khuyến nghị)

1. MVP (1–2 ngày): Service C# đẩy log 1 chiều + config + log.
2. Hệ thống hoá: auto-retry, batch, state, UI tối thiểu.
3. Nâng cấp 2 chiều: sync users.
4. Auto-update: background updater.

---

## 13) Checklist nghiệm thu

- [ ] Cài app trên máy Windows mới, không cần cài thêm gì.
- [ ] Test Device OK.
- [ ] Test API OK.
- [ ] Đẩy log lên server (200) và có record DB.
- [ ] Sau khi map `attendance_code`, web hiển thị đúng theo nhân viên.
- [ ] Service tự chạy khi restart máy.

---

Nếu cần mình có thể viết tiếp 2 phần:
1) Spec cụ thể cho endpoint `GET /api/attendance-agent/users` và payload chuẩn.
2) Wireframe UI đơn giản (Tray + màn hình cấu hình).
