# Tổng kết triển khai chấm công

Ngày cập nhật: 2026-01-22

## 1) Mục tiêu
- Đồng bộ log chấm công từ máy Ronald Jack lên VPS.
- Lưu log vào hệ thống, liên kết với nhân viên, hiển thị trên bảng chấm công.
- Hỗ trợ chạy tự động trên máy Windows tại LAN.

## 2) Agent đồng bộ (Windows)
- Tạo bộ công cụ attendance-agent để đọc log từ thiết bị và đẩy lên API.
- Vá thư viện ZKLib để chạy ổn định trên Windows (UDP, buffer, CMD_PREPARE_DATA).
- Thêm tùy chọn cấu hình: timeout, SSL, CA bundle, IP resolve (v4/v6).
- Thêm batch sync: chia nhỏ 500 bản ghi/lần để tránh giới hạn kích thước request.
- Lưu trạng thái lần sync gần nhất để lần sau chỉ gửi log mới.

Các file chính:
- tools/attendance-agent/agent.php
- tools/attendance-agent/run-agent.bat
- tools/attendance-agent/agent.config.example.bat
- tools/attendance-agent/src/ZKLib/ZKLib.php
- tools/attendance-agent/README.md

## 3) API nhận log trên VPS
- API endpoint nhận log: POST /api/attendance-agent/push-logs
- Xác thực bằng chữ ký HMAC (middleware attendance.agent).
- Xử lý dữ liệu log và upsert vào bảng attendance_logs.
- Sửa lỗi Array to string conversion bằng cách JSON-encode trường raw trước khi upsert.

File chính:
- app/Http/Controllers/Api/AttendanceAgentController.php

## 4) Xử lý hiển thị log
- Đã có sẵn UI bảng chấm công tại /employees/attendance (Vue + Blade).
- API danh sách log có sẵn: GET /api/attendance-logs

File liên quan:
- resources/views/employees/attendance.blade.php
- resources/js/attendance-app.js
- resources/js/views/AttendanceSheet.vue
- app/Http/Controllers/Api/AttendanceLogController.php

## 5) Mapping nhân viên
- Log lưu theo device_user_id.
- Nhân viên cần nhập attendance_code khớp device_user_id.
- Khi mapping đúng, log sẽ hiển thị theo nhân viên trên bảng chấm công.

File liên quan:
- app/Models/Employee.php (attendance_code)
- database/migrations/2026_01_11_000002_employee_profile_upgrade.php

## 6) API hỗ trợ mapping và rà soát
Đã bổ sung các API mới:
- GET /api/attendance-logs?unmapped=1: lọc log chưa map nhân viên
- GET /api/attendance-logs/unmapped-users: danh sách device_user_id chưa map
- POST /api/attendance-logs/refresh-mapping: cập nhật lại employee_id cho log đã có

File cập nhật:
- app/Http/Controllers/Api/AttendanceLogController.php
- routes/api.php

## 7) Nginx
- Đề xuất tăng client_max_body_size để hỗ trợ payload lớn.
- Tuy nhiên giải pháp chính là batch 500 record/lần để ổn định hơn.

## 8) Kết quả hiện tại
- Agent chạy thành công, batch trả HTTP 200.
- Logs đã được lưu vào attendance_logs.
- Hệ thống đã sẵn sàng hiển thị log sau khi map attendance_code cho nhân viên.

## 9) Việc còn lại nếu cần
- Tạo UI hỗ trợ mapping device_user_id với nhân viên hàng loạt.
- Tạo báo cáo tổng hợp theo ngày/tuần/tháng.
- Tự động hóa chạy agent bằng Task Scheduler.
