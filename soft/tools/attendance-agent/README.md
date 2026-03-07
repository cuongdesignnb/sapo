# Attendance Agent (LAN)

Mục tiêu: chạy 1 script trong LAN (cùng mạng với máy chấm công) để đọc log qua UDP 4370 và **đẩy log lên VPS**.

## Ý tưởng (đúng như Gemini gợi ý)

Gemini nói đúng bản chất: Web/PHP trên VPS **không thể** gọi trực tiếp máy chấm công ở LAN (IP kiểu `192.168.x.x`) nếu không có VPN/port-forward.

- Cách 1 (Thư viện PHP qua UDP): đọc log bằng thư viện kiểu ZK/ZKTeco (giao thức UDP 4370).
- Cách 2 (Tool trung gian): chạy 1 app/script trong LAN (Windows) để đọc log rồi đẩy lên server.

Giải pháp repo hiện tại là **kết hợp 2 ý**:

- Dùng thư viện PHP `wnasich/php_zklib` (giống Cách 1)
- Nhưng chạy dưới dạng “agent/tool” trong LAN và push lên VPS (giống Cách 2)

## 1) Chuẩn bị trên VPS

- Thêm vào `.env` (VPS):
    - `ATTENDANCE_AGENT_SECRET=...` (tự đặt chuỗi dài, khó đoán)

Sau khi deploy code, nhớ chạy:

- `php artisan config:clear`

## 2) Chuẩn bị trên máy Windows trong cửa hàng

Yêu cầu:

- PHP 8.2+ (có `sockets` + `curl`)
- Composer

### Cài PHP trên Windows (nhanh)

**Cách A (khuyến nghị): dùng Scoop**

1. Mở PowerShell (Run as Administrator) và chạy:

- `Set-ExecutionPolicy RemoteSigned -Scope CurrentUser`
- `irm get.scoop.sh | iex`

2. Cài PHP:

- `scoop install php`

3. Kiểm tra:

- `php -v`

**Cách B: tải bản ZIP chính thức**

1. Tải PHP 8.2+ (Non Thread Safe) tại:

- https://windows.php.net/download/

2. Giải nén vào `C:\php`
3. Thêm `C:\php` vào PATH của Windows
4. Copy `php.ini-production` -> `php.ini`
5. Mở `php.ini`, bật extensions:

- `extension=curl`
- `extension=sockets`

6. Mở CMD mới và chạy `php -v`

### Cài Composer trên Windows

1. Tải và cài: https://getcomposer.org/Composer-Setup.exe
2. Sau khi cài, mở CMD mới và chạy `composer -V`

Lưu ý:

- Máy Windows nên đồng bộ giờ (NTP) để chữ ký request không bị lệch thời gian.
- Nếu máy chấm công có đặt `Comm Key / Communication Password` khác 0, một số thư viện PHP có thể báo `UNAUTH`.
    - Cách xử lý nhanh nhất: kiểm tra/đặt `Comm Key` về `0` trên máy chấm công (nếu chính sách cho phép)
    - Hoặc chuyển sang thư viện hỗ trợ Comm Key tốt hơn (ví dụ: `tad-php` / các SDK khác), hoặc dùng app C# theo SDK hãng.

Trong thư mục `tools/attendance-agent`:

- Chạy `composer install`

### Cách chạy nhanh bằng file .bat

1. Copy file cấu hình:

- `agent.config.example.bat` -> `agent.config.bat`
- Sửa các biến `SERVER_URL`, `DEVICE_ID`, `DEVICE_IP`, `AGENT_SECRET`

2. Chạy:

- `run-agent.bat`

## 3) Chạy agent

Ví dụ:

`php agent.php --server=https://app.cuongdesign.net --device-id=1 --device-ip=192.168.1.222 --port=4370 --secret=YOUR_SECRET`

Gợi ý:

- Đặt lịch chạy bằng Windows Task Scheduler mỗi 1-5 phút.

Endpoint push (trên VPS):

- `POST /api/attendance-agent/push-logs`
- Headers:
    - `X-Attendance-Agent-Timestamp`: unix timestamp
    - `X-Attendance-Agent-Signature`: `hash_hmac('sha256', timestamp . '.' . rawBody, ATTENDANCE_AGENT_SECRET)`

## 4) Troubleshooting (Windows)

**Lỗi 10040 / 10045 khi chạy agent**

- Đây là lỗi buffer UDP nhỏ hoặc `MSG_WAITALL` không tương thích trên Windows.
- Dùng bản agent mới (đã override `ZKLib` để tăng buffer & bỏ `MSG_WAITALL` trên Windows).
- Sau khi cập nhật folder agent, chạy lại:
    - `composer install` (hoặc `composer dump-autoload`)
    - rồi chạy lại `run-agent.bat`

**Lỗi “Class ZKLib\FreeSize not found”**

- Dùng bản agent mới (đã bỏ kiểm tra `FreeSize` trong `getAttendance()` để tránh lỗi autoload trên Windows).
- Sau khi cập nhật folder agent, chạy lại `composer dump-autoload`.

**Lỗi SSL: “unable to get local issuer certificate (20)”**

- Cách nhanh (không khuyến nghị lâu dài): mở `agent.config.bat` và đặt `INSECURE=1` để tắt verify SSL.
- Cách chuẩn: tải file CA bundle (cacert.pem) và đặt đường dẫn vào `CA_BUNDLE=...`.

**Lỗi timeout: “Operation timed out … with 0 bytes received”**

- Tăng thời gian chờ HTTP trong `agent.config.bat`:
    - `HTTP_TIMEOUT=40`
    - `CONNECT_TIMEOUT=20`
- Kiểm tra từ máy khách có truy cập được `https://app.cuongdesign.net` không (trình duyệt).

**Lỗi 404 nginx dù /api/test hoạt động**

- Thường do máy khách resolve IPv6 trỏ vào vhost khác.
- Đặt `IP_RESOLVE=v4` trong `agent.config.bat` để ép IPv4.
