# Attendance Bridge (C#) – Checklist triển khai đúng (Auto push logs liên tục)

Mục tiêu: Windows Service chạy 24/7, tự đọc log từ máy chấm công và đẩy lên VPS liên tục, không miss log, không spam, và thời gian hiển thị đúng giờ VN.

---

## ⚠️ 0) XỬ LÝ MẤT KẾT NỐI / MÁY TÍNH TẮT (CRITICAL)

### Tình huống

Máy tính chạy Agent bị TẮT 1 tuần (đi công tác, mất điện, restart...).  
Trong thời gian đó, **máy chấm công vẫn lưu log** trong bộ nhớ nội bộ.  
Khi máy tính BẬT lại, Agent **PHẢI đồng bộ toàn bộ log tồn đọng**.

### Yêu cầu bắt buộc

**1. Khi Agent khởi động (startup) → luôn chạy FULL SYNC trước**

```
Startup sequence:
  1. Đọc checkpoint: last_synced_at
  2. Nếu last_synced_at cũ hơn 1 giờ → chạy FULL SYNC
     - GetAllLogs() từ máy chấm công (không lọc thời gian)
     - Hoặc GetLogs(since = last_synced_at - 1 ngày)
  3. Push toàn bộ lên server (batch 500 logs/request)
  4. Cập nhật checkpoint
  5. Sau đó mới chuyển sang incremental sync 30s/lần
```

**Lưu ý**: KHÔNG BAO GIỜ chỉ lấy "log hôm nay" khi startup. Phải lấy từ `last_synced_at`.

**2. Lấy log từ máy chấm công – cách đúng**

```csharp
// ✅ ĐÚNG: Lấy TẤT CẢ log khi startup (hoặc từ checkpoint)
var allLogs = zk.GetAllLogs(); // hoặc SDK tương đương

// Lọc ở C#: chỉ gửi log từ last_synced_at trở đi
var logsToSend = allLogs
    .Where(l => l.PunchedAt > lastSyncedAt.AddMinutes(-5))
    .OrderBy(l => l.PunchedAt)
    .ToList();

// ❌ SAI: Chỉ lấy log "ngày hôm nay"
var todayLogs = zk.GetLogs(DateTime.Today); // MISS toàn bộ ngày trước!
```

**3. Full sync an toàn vì server upsert idempotent**

Server dùng upsert theo key `(device_id, device_user_id, punched_at)`.  
Gửi trùng log cũ → server UPDATE (không duplicate).  
→ Agent có thể gửi lại toàn bộ 30 ngày mà không sợ data bị sai.

**4. Xử lý batch lớn khi catch-up**

Sau 1 tuần tắt máy, có thể tồn đọng hàng nghìn log:

```csharp
// Chia batch 500 logs/request
const int BATCH_SIZE = 500;
for (int i = 0; i < logsToSend.Count; i += BATCH_SIZE)
{
    var batch = logsToSend.Skip(i).Take(BATCH_SIZE).ToList();
    var success = await PushLogs(batch);
    
    if (success)
    {
        // Cập nhật checkpoint từng batch
        checkpoint.LastSyncedAt = batch.Max(l => l.PunchedAt);
        SaveCheckpoint(checkpoint);
    }
    else
    {
        // Dừng, retry sau
        break;
    }
    
    // Delay nhẹ giữa các batch
    await Task.Delay(1000);
}
```

**5. Pseudocode tổng quan Agent startup**

```
OnServiceStart():
  checkpoint = LoadCheckpoint()
  
  // Bật lại sau thời gian dài?
  gap = DateTime.UtcNow - checkpoint.LastSyncedAt
  
  if gap > TimeSpan.FromHours(1):
      Log("Phát hiện offline {gap.TotalHours}h – chạy full sync catch-up")
      logs = device.GetAllLogs()  // ĐỌC HẾT từ máy chấm công
      filtered = logs.Where(l => l.PunchedAt > checkpoint.LastSyncedAt - 5 minutes)
      PushInBatches(filtered, batchSize=500)
      Log("Full sync done: {filtered.Count} logs")
  
  // Chuyển sang incremental loop
  while running:
      sleep(30s)
      newLogs = device.GetLogs(since = checkpoint.LastSyncedAt - 2 minutes)
      if newLogs.Any():
          PushLogs(newLogs)
          checkpoint.LastSyncedAt = newLogs.Max(l => l.PunchedAt)
          SaveCheckpoint(checkpoint)
```

### Checklist kiểm tra (team C# tự test)

| # | Kiểm tra | Kỳ vọng |
|---|----------|---------|
| 1 | Tắt Agent 10 phút, chấm công 3 lần, bật lại | 3 logs phải lên server |
| 2 | Tắt máy tính 1 ngày, bật lại | Toàn bộ log ngày hôm qua phải lên |
| 3 | Tắt 1 tuần, bật lại | Tất cả logs 7 ngày phải đồng bộ đủ |
| 4 | Mất mạng 2 tiếng (máy vẫn bật) | Logs tồn → push khi có mạng |
| 5 | Khởi động lần đầu (chưa có checkpoint) | Full sync toàn bộ log trên máy |

---

## 1) Endpoint backend đang dùng

Base URL: `https://app.cuongdesign.net`

- `POST /api/attendance-agent/push-logs`
  - Upsert idempotent theo key: `(attendance_device_id, device_user_id, punched_at)`
  - Server tự map `employee_id` nếu có `employees.attendance_code == device_user_id`
  - Server bỏ qua log lỗi: `device_user_id = "0"` hoặc `punched_at` quá tương lai

- `POST /api/attendance-agent/refresh-mapping` (khuyến nghị gọi khi admin vừa set attendance_code)

Auth: HMAC qua middleware `attendance.agent` (headers: `X-Device-Id`, `X-Timestamp`, `X-Signature`).

## 2) Loop chuẩn (incremental sync) – BẮT BUỘC

### 2.1 Lưu checkpoint

Lưu theo từng `device_id`:
- `last_synced_at` (khuyến nghị lưu UTC)
- Có thể lưu file JSON hoặc SQLite local:
  - File: `%ProgramData%\\ViteSoftAttendanceBridge\\state.json`
  - Nội dung tối thiểu:
    - `device_id`
    - `last_synced_at`
    - `last_success_at`
    - `last_error`

### 2.2 Chu kỳ chạy

- Khuyến nghị: 30s (ổn định, gần realtime)
- Nếu nhiều máy/log: 60s

### 2.3 Overlap để không miss

Khi đọc log:
- `since = last_synced_at - 2 phút`
- Lấy các log có `punched_at > since` (lọc ở C# nếu SDK không hỗ trợ lọc theo time)

Lý do: tránh miss do lệch đồng hồ máy, trễ mạng, hoặc SDK trả log chậm.

### 2.4 Batch + retry

- Batch gửi: 200–500 logs/lần POST
- Nếu lỗi HTTP/timeout:
  - không cập nhật checkpoint
  - retry backoff: 5s → 10s → 30s → 60s (giới hạn)

### 2.5 Update checkpoint

Chỉ update khi server trả OK:
- `last_synced_at = max(punched_at của logs gửi thành công)`

## 3) Chuẩn hóa thời gian (để web hiển thị đúng)

### 3.1 Quy tắc

- Gửi `punched_at` theo ISO8601 có timezone offset.
- Nếu thời gian lấy từ máy là giờ VN local:
  - Gửi dạng: `2026-02-02T08:37:00+07:00`

Không gửi `Z` nếu bạn đang dùng giờ local +07 (vì `Z` là UTC, dễ bị lệch khi người xem).

### 3.2 Ví dụ log payload chuẩn

```json
{
  "device_id": "ronaldjack-1",
  "logs": [
    {
      "device_id": "ronaldjack-1",
      "device_user_id": "16",
      "punched_at": "2026-02-02T08:37:00+07:00",
      "event_type": "in",
      "raw": {
        "source": "zk",
        "verify_mode": 1
      }
    }
  ]
}
```

## 4) `event_type` (in/out) – khuyến nghị mạnh

- Nếu SDK có trạng thái vào/ra: map sang `event_type = "in" | "out"`.
- Nếu SDK không có: để `null` (server vẫn nhận), nhưng tính giờ vào/ra sẽ kém chính xác nếu nhiều lần chấm.

## 5) Idempotent / chống trùng

Backend đã upsert theo `(device_id, device_user_id, punched_at)` nên có thể gửi trùng an toàn.
Nhưng C# vẫn nên lọc để giảm tải.

## 6) Khi nào gọi `refresh-mapping`

- Khi admin vừa cập nhật `employees.attendance_code`
- Khi tool sync users xuống máy và phát hiện có nhân viên mới

Không cần gọi mỗi vòng 30s.

## 7) Logging + quan sát (bắt buộc khi chạy thật)

Ghi log local (rolling file):
- Mỗi vòng: số log đọc được, số log gửi, latency, status code
- Khi lỗi: in exception + response body

Expose health endpoint local (tùy chọn):
- `http://127.0.0.1:port/health`
  - last_success_at
  - last_synced_at
  - last_error

## 8) Test nhanh (để team tự kiểm)

- Đẩy 1 log mẫu → vào web kiểm:
  - `https://app.cuongdesign.net/employees/attendance/unmapped-users`

- Nếu log lên nhưng chưa hiện trong ô chấm công:
  - đảm bảo đã set `attendance_code` cho nhân viên và refresh mapping
  - đảm bảo có lịch làm việc trong tuần đó

---

# Phần web backend đã làm để hỗ trợ C#

- Upsert logs idempotent
- Map theo `employees.attendance_code`
- Trang web xem unmapped users và nút refresh mapping
- Tự động tính lại công ngay (dispatchSync) sau khi push logs / refresh mapping
- Artisan command: `php artisan attendance:refresh` để force refresh thủ công

## Debug endpoints (không cần auth)

| Endpoint | Mô tả |
|----------|-------|
| `GET /api/attendance-agent/recent-logs` | Xem 20 logs gần nhất |
| `GET /api/attendance-agent/debug-status` | Tổng quan: logs đã map, schedules, timekeeping |
| `POST /api/attendance-agent/force-recalculate` | Force tính lại công (body: `{"from":"...","to":"..."}`) |

---

## 9) Sơ đồ luồng hoàn chỉnh

```
┌──────────────┐     ┌───────────┐     ┌──────────────────────────────┐     ┌────────┐
│ Máy chấm công│────►│ C# Agent  │────►│ Server (push-logs API)       │────►│ Web UI │
│ (ZK device)  │     │ (Windows) │     │                              │     │        │
│              │     │           │     │ 1. Lưu attendance_logs       │     │ Hiển   │
│ Lưu log     │     │ Đọc log   │     │ 2. Map employee_id           │     │ thị    │
│ nội bộ      │     │ Push batch│     │ 3. Tính timekeeping_records  │     │ chấm   │
│ 24/7        │     │ 30s/lần   │     │    (tự động, sync mode)      │     │ công   │
└──────────────┘     └───────────┘     └──────────────────────────────┘     └────────┘
                          │
                     Lưu checkpoint
                     (last_synced_at)
                          │
                     Khi tắt/bật lại:
                     → Full sync catch-up
                     → Push hết log tồn
```
