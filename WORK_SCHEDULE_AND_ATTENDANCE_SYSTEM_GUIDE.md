# Hướng dẫn xây dựng hệ thống Lịch làm việc & Bảng chấm công

> Tài liệu chi tiết từ A-Z để xây dựng lại hệ thống lịch làm việc (ca), bảng chấm công tự động,  
> và mối quan hệ với máy chấm công. Áp dụng cho bất kỳ dự án Laravel + Vue nào.

**Cập nhật:** 2026-03-04

---

## Mục lục

1. [Tổng quan hệ thống](#1-tổng-quan-hệ-thống)
2. [Sơ đồ quan hệ dữ liệu (ERD)](#2-sơ-đồ-quan-hệ-dữ-liệu-erd)
3. [Database — Migrations chi tiết](#3-database--migrations-chi-tiết)
4. [Backend — Models](#4-backend--models)
5. [Backend — Controllers](#5-backend--controllers)
6. [Backend — TimekeepingService (Lõi tính công)](#6-backend--timekeepingservice-lõi-tính-công)
7. [Backend — Job tính công tự động](#7-backend--job-tính-công-tự-động)
8. [Backend — Artisan Command](#8-backend--artisan-command)
9. [Routes — API & Web](#9-routes--api--web)
10. [Frontend — API Client](#10-frontend--api-client)
11. [Frontend — Màn hình Lịch làm việc](#11-frontend--màn-hình-lịch-làm-việc)
12. [Frontend — Màn hình Bảng chấm công](#12-frontend--màn-hình-bảng-chấm-công)
13. [Mối quan hệ với máy chấm công](#13-mối-quan-hệ-với-máy-chấm-công)
14. [Luồng dữ liệu End-to-End](#14-luồng-dữ-liệu-end-to-end)
15. [Checklist triển khai](#15-checklist-triển-khai)

---

## 1. Tổng quan hệ thống

### 1.1 Các thành phần chính

| Thành phần | Vai trò | Bảng DB |
|------------|---------|---------|
| **Ca làm việc (Shift)** | Định nghĩa mẫu thời gian: giờ vào, giờ ra, cho phép muộn/sớm bao nhiêu phút | `shifts` |
| **Lịch làm việc (Schedule)** | Gán ca cho nhân viên vào ngày cụ thể (hỗ trợ nhiều ca/ngày qua slot) | `employee_work_schedules` |
| **Log chấm công (Attendance Log)** | Dữ liệu thô từ máy chấm công: ai quẹt lúc nào | `attendance_logs` |
| **Bản ghi chấm công (Timekeeping Record)** | Kết quả tính toán: check-in/out thực tế, đi muộn, về sớm, tăng ca | `timekeeping_records` |
| **Cài đặt chấm công (Timekeeping Setting)** | Cấu hình: tha muộn X phút, làm tròn OT, cửa sổ check-in... | `timekeeping_settings` |
| **Ngày lễ (Holiday)** | Danh sách ngày lễ với hệ số lương (x2, x3...) | `holidays` |

### 1.2 Nguyên tắc hoạt động

```
                           ┌─────────────────┐
                           │   Ca làm việc    │  Shift: Hành chính 08:00 - 17:00
                           │   (Shift)        │         Ca tối 18:00 - 22:00
                           └────────┬────────┘
                                    │ gán vào ngày cụ thể
                                    ▼
                           ┌─────────────────┐
                           │  Lịch làm việc   │  Schedule: NV001 ngày 04/03 ca Hành chính
                           │  (Schedule)       │           NV002 ngày 04/03 ca Tối
                           └────────┬────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
              ▼                     ▼                     ▼
    ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐
    │  Log chấm công   │   │  Cài đặt CC     │   │  Ngày lễ        │
    │  (từ máy)        │   │  (Settings)     │   │  (Holiday)      │
    │  employee_id +   │   │  tha muộn 5p    │   │  04/03 → x2     │
    │  punched_at      │   │  OT sau 30p     │   │                 │
    └────────┬────────┘   └────────┬────────┘   └────────┬────────┘
              │                     │                     │
              └─────────────────────┼─────────────────────┘
                                    │
                                    ▼
                        ┌──────────────────────┐
                        │ TimekeepingService    │
                        │ recalculateForRange() │
                        │                       │
                        │ Match log ↔ schedule  │
                        │ Tính: muộn, sớm, OT  │
                        └───────────┬──────────┘
                                    │
                                    ▼
                        ┌──────────────────────┐
                        │  Bản ghi chấm công   │  TimekeepingRecord:
                        │  (Timekeeping Record) │   check_in: 08:05
                        │                       │   check_out: 17:30
                        │  Hiển thị trên UI     │   late: 0p (tha 5p)
                        └──────────────────────┘   OT: 30p
```

---

## 2. Sơ đồ quan hệ dữ liệu (ERD)

```
┌──────────────┐       ┌─────────────────────────┐       ┌────────────────┐
│  employees   │──1:N──│ employee_work_schedules  │──N:1──│    shifts      │
│              │       │                          │       │                │
│ id           │       │ id                       │       │ id             │
│ code         │       │ employee_id  ────────────│───┐   │ name           │
│ attendance_  │       │ warehouse_id              │   │   │ start_time     │
│   code ──────│──┐    │ shift_id ─────────────────│───┘   │ end_time       │
│ name         │  │    │ work_date                 │       │ allow_late_min │
│ warehouse_id │  │    │ slot (1,2,3...)           │       │ allow_early_min│
└──────────────┘  │    │ start_time (override)     │       │ checkin_start  │
                  │    │ end_time (override)        │       │ checkin_end    │
                  │    │ status                     │       │ is_overnight   │
                  │    └─────────────┬─────────────┘       └────────────────┘
                  │                  │
                  │                  │ 1:1 (unique trên schedule_id)
                  │                  │
                  │    ┌─────────────┴──────────────┐
                  │    │   timekeeping_records       │
                  │    │                              │
                  │    │ id                           │
                  │    │ employee_id                  │
                  │    │ employee_work_schedule_id    │──── FK duy nhất
                  │    │ warehouse_id                 │
                  │    │ shift_id                     │
                  │    │ work_date                    │
                  │    │ slot                         │
                  │    │ scheduled_start_at           │ ─── Giờ ca bắt đầu (datetime)
                  │    │ scheduled_end_at             │ ─── Giờ ca kết thúc (datetime)
                  │    │ check_in_at                  │ ─── Giờ vào thực tế
                  │    │ check_out_at                 │ ─── Giờ ra thực tế
                  │    │ source (device/manual/none)  │
                  │    │ attendance_type              │ ─── work / leave_paid / leave_unpaid
                  │    │ manual_override (bool)       │ ─── true = không ghi đè tự động
                  │    │ late_minutes                 │
                  │    │ early_minutes                │
                  │    │ ot_minutes                   │
                  │    │ worked_minutes               │
                  │    │ is_holiday, holiday_multiplier│
                  │    └──────────────────────────────┘
                  │
                  │    ┌──────────────────────────────┐
                  └────│   attendance_logs             │
                       │                              │
                       │ id                           │
                       │ attendance_device_id          │ ─── FK → attendance_devices
                       │ employee_id ─────────────────│ ─── auto-map qua attendance_code
                       │ device_user_id                │ ─── ID trên máy chấm công
                       │ punched_at                    │ ─── Thời điểm quẹt
                       │ event_type (in/out/null)      │
                       │ UNIQUE(device_id, user_id,    │
                       │        punched_at)            │ ─── idempotent
                       └──────────────────────────────┘

┌──────────────────────┐       ┌────────────────────────┐
│ timekeeping_settings │       │      holidays          │
│                      │       │                        │
│ warehouse_id (null=  │       │ holiday_date (unique)  │
│   global)            │       │ name                   │
│ late_grace_minutes   │       │ multiplier (VD: 2.0)   │
│ early_grace_minutes  │       │ paid_leave (bool)      │
│ ot_rounding_minutes  │       │ status                 │
│ ot_after_minutes     │       └────────────────────────┘
│ enforce_shift_       │
│   checkin_window     │
│ use_shift_allowances │
└──────────────────────┘
```

### 2.1 Quan hệ chính

| Quan hệ | Mô tả |
|---------|-------|
| `employees` 1:N `employee_work_schedules` | Mỗi NV có nhiều lịch làm việc |
| `shifts` 1:N `employee_work_schedules` | Mỗi ca được gán cho nhiều NV/ngày |
| `employee_work_schedules` 1:1 `timekeeping_records` | Mỗi lịch có đúng 1 bản ghi chấm công |
| `employees` 1:N `attendance_logs` | Mỗi NV có nhiều log quẹt (qua `attendance_code`) |
| `attendance_logs` → `timekeeping_records` | Service match log thô → tính ra bản ghi CC (không FK trực tiếp) |

### 2.2 Unique Constraints quan trọng

```
employee_work_schedules: UNIQUE(employee_id, work_date, slot)
  → 1 NV có thể nhiều ca/ngày (slot 1, 2, 3...)

timekeeping_records: UNIQUE(employee_work_schedule_id)
  → Mỗi schedule chỉ có 1 record (upsert)

attendance_logs: UNIQUE(attendance_device_id, device_user_id, punched_at)
  → Push log nhiều lần không bị trùng (idempotent)
```

---

## 3. Database — Migrations chi tiết

### 3.1 Bảng `shifts` — Ca làm việc

```php
Schema::create('shifts', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('warehouse_id')->nullable();  // Thuộc kho/chi nhánh nào
    $table->string('name');                                   // "Ca hành chính", "Ca tối"...
    $table->time('start_time');                               // "08:00"
    $table->time('end_time');                                 // "17:00"
    $table->time('checkin_start_time')->nullable();           // Cửa sổ check-in bắt đầu
    $table->time('checkin_end_time')->nullable();             // Cửa sổ check-in kết thúc
    $table->unsignedSmallInteger('allow_late_minutes')->default(0);   // Cho phép muộn X phút
    $table->unsignedSmallInteger('allow_early_minutes')->default(0);  // Cho phép sớm X phút
    $table->unsignedSmallInteger('rounding_minutes')->default(15);    // Làm tròn OT (15p)
    $table->boolean('is_overnight')->default(false);          // Ca đêm (qua ngày)
    $table->string('status')->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['warehouse_id', 'status']);
});
```

**Ví dụ ca làm việc:**
| Tên ca | Giờ vào | Giờ ra | Cho phép muộn | Ca đêm |
|--------|---------|--------|--------------|--------|
| Hành chính | 08:00 | 17:00 | 5 phút | Không |
| Ca sáng | 06:00 | 14:00 | 10 phút | Không |
| Ca tối | 22:00 | 06:00 | 5 phút | **Có** |

### 3.2 Bảng `employee_work_schedules` — Lịch làm việc

```php
Schema::create('employee_work_schedules', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('employee_id');
    $table->unsignedBigInteger('warehouse_id')->nullable();
    $table->unsignedBigInteger('shift_id')->nullable();       // FK → shifts (nếu dùng ca mẫu)
    $table->date('work_date');                                 // Ngày làm việc
    $table->unsignedSmallInteger('slot')->default(1);          // Ca thứ mấy trong ngày
    $table->time('start_time')->nullable();                    // Override giờ vào (nếu khác ca mẫu)
    $table->time('end_time')->nullable();                      // Override giờ ra
    $table->string('shift_name')->nullable();                  // Tên ca hiển thị
    $table->string('status')->default('planned');              // planned / confirmed
    $table->text('notes')->nullable();
    $table->timestamps();

    // ★ Key design: 1 NV có thể làm nhiều ca trong 1 ngày
    $table->unique(['employee_id', 'work_date', 'slot'], 'emp_sched_emp_date_slot_uq');
    $table->index(['work_date', 'status']);
    $table->index(['warehouse_id']);
    $table->index(['shift_id']);
});
```

**Ví dụ:**
| employee_id | work_date | slot | shift_id | shift_name |
|------------|-----------|------|----------|------------|
| 1 | 2026-03-04 | 1 | 1 | Hành chính |
| 1 | 2026-03-04 | 2 | 3 | Ca tối |
| 2 | 2026-03-04 | 1 | 1 | Hành chính |

### 3.3 Bảng `timekeeping_records` — Bản ghi chấm công

```php
Schema::create('timekeeping_records', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('employee_id');
    $table->unsignedBigInteger('employee_work_schedule_id')->nullable();
    $table->unsignedBigInteger('warehouse_id')->nullable();
    $table->unsignedBigInteger('shift_id')->nullable();
    $table->date('work_date');
    $table->unsignedSmallInteger('slot')->default(1);

    // Thời gian theo lịch (tính từ schedule + shift)
    $table->dateTime('scheduled_start_at')->nullable();   // VD: 2026-03-04 08:00:00
    $table->dateTime('scheduled_end_at')->nullable();     // VD: 2026-03-04 17:00:00

    // Thời gian thực tế (từ log máy CC hoặc chỉnh tay)
    $table->dateTime('check_in_at')->nullable();          // VD: 2026-03-04 08:05:00
    $table->dateTime('check_out_at')->nullable();         // VD: 2026-03-04 17:30:00

    // Metadata
    $table->string('source')->default('device');          // 'device' | 'manual' | 'none'
    $table->string('attendance_type')->default('work');   // 'work' | 'leave_paid' | 'leave_unpaid'
    $table->boolean('manual_override')->default(false);   // true = không bị ghi đè khi recalculate

    // Kết quả tính toán
    $table->integer('late_minutes')->default(0);          // Đi muộn (phút)
    $table->integer('early_minutes')->default(0);         // Về sớm (phút)
    $table->integer('ot_minutes')->default(0);            // Tăng ca (phút)
    $table->integer('worked_minutes')->default(0);        // Tổng phút làm việc

    // Cho tính lương
    $table->decimal('work_units', 6, 2)->default(0);     // Số công (0/0.5/1.0)
    $table->boolean('is_holiday')->default(false);
    $table->decimal('holiday_multiplier', 6, 2)->default(1);

    $table->json('raw')->nullable();                      // Lưu log_ids gốc
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['employee_id', 'work_date']);
    $table->index(['warehouse_id', 'work_date']);
    $table->unique(['employee_work_schedule_id'], 'timekeeping_schedule_uq');
});
```

### 3.4 Bảng `timekeeping_settings` — Cài đặt chấm công

```php
Schema::create('timekeeping_settings', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('warehouse_id')->nullable()->unique();  // null = toàn hệ thống

    // Cài đặt cơ bản
    $table->decimal('standard_hours_per_day', 6, 2)->default(8);
    $table->boolean('use_shift_allowances')->default(true);   // Lấy allowance từ shift hay từ setting?

    // Grace periods (tha)
    $table->unsignedSmallInteger('late_grace_minutes')->default(0);   // Tha muộn X phút
    $table->unsignedSmallInteger('early_grace_minutes')->default(0);  // Tha về sớm X phút

    // Tính năng nâng cao
    $table->boolean('allow_multiple_shifts_one_inout')->default(false);
    $table->boolean('enforce_shift_checkin_window')->default(false);  // Bắt buộc check-in trong cửa sổ ca
    $table->unsignedSmallInteger('ot_rounding_minutes')->default(0);  // Làm tròn OT (15/30 phút)
    $table->unsignedSmallInteger('ot_after_minutes')->default(0);     // OT bắt đầu tính sau X phút

    $table->string('status')->default('active');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('updated_by')->nullable();
    $table->timestamps();
});
```

**Giải thích các cài đặt:**

| Cài đặt | Ý nghĩa | Ví dụ |
|---------|---------|-------|
| `use_shift_allowances` | `true` → lấy allow_late từ shift; `false` → lấy từ setting | Ca Sáng cho phép muộn 10p riêng |
| `late_grace_minutes` | Tha muộn toàn hệ thống (khi `use_shift_allowances=false`) | Tất cả tha 5 phút |
| `enforce_shift_checkin_window` | Chỉ chấp nhận check-in trong `checkin_start_time ~ checkin_end_time` của ca | Chỉ nhận từ 07:30-09:00 |
| `ot_rounding_minutes` | Làm tròn phút tăng ca xuống bội số | OT 47p → 45p (làm tròn 15p) |
| `ot_after_minutes` | Chỉ tính OT sau X phút | Hết ca 30p mới tính OT |

### 3.5 Bảng `holidays` — Ngày lễ

```php
Schema::create('holidays', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->date('holiday_date')->unique();
    $table->string('name');                                 // "Quốc khánh 2/9"
    $table->decimal('multiplier', 6, 2)->default(1);       // Hệ số lương (1=thường, 2=gấp đôi)
    $table->boolean('paid_leave')->default(false);          // Nghỉ vẫn tính lương
    $table->string('status')->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

---

## 4. Backend — Models

### 4.1 Shift (Ca làm việc)

```php
class Shift extends Model
{
    protected $fillable = [
        'warehouse_id', 'name', 'start_time', 'end_time',
        'checkin_start_time', 'checkin_end_time',
        'allow_late_minutes', 'allow_early_minutes',
        'rounding_minutes', 'is_overnight', 'status', 'notes',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
        'allow_late_minutes' => 'integer',
        'allow_early_minutes' => 'integer',
        'rounding_minutes' => 'integer',
    ];

    // Accessor tự tính: thời lượng ca (phút)
    protected $appends = ['duration_minutes', 'duration_text', 'work_time_text', 'checkin_time_text'];

    public function getDurationMinutesAttribute(): int
    {
        $startMin = $this->timeToMinutes($this->start_time);
        $endMin = $this->timeToMinutes($this->end_time);
        if ($startMin === null || $endMin === null) return 0;
        $diff = $endMin - $startMin;
        return $diff <= 0 ? $diff + 1440 : $diff;  // 1440 = 24*60 (xử lý ca đêm)
    }

    public function getWorkTimeTextAttribute(): string
    {
        return trim($this->start_time) . ' - ' . trim($this->end_time);
    }

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
```

### 4.2 EmployeeWorkSchedule (Lịch làm việc)

```php
class EmployeeWorkSchedule extends Model
{
    protected $fillable = [
        'employee_id', 'warehouse_id', 'shift_id', 'work_date',
        'slot', 'start_time', 'end_time', 'shift_name', 'status', 'notes',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'slot' => 'integer',
    ];

    public function employee()  { return $this->belongsTo(Employee::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function shift()     { return $this->belongsTo(Shift::class); }

    // ★ Quan hệ 1:1 với TimekeepingRecord
    public function timekeepingRecord()
    {
        return $this->hasOne(TimekeepingRecord::class, 'employee_work_schedule_id');
    }
}
```

**Quan hệ quan trọng:** `timekeepingRecord()` — khi load schedule, ta tự động có kết quả chấm công đi kèm.

### 4.3 TimekeepingRecord (Bản ghi chấm công)

```php
class TimekeepingRecord extends Model
{
    protected $fillable = [
        'employee_id', 'employee_work_schedule_id', 'warehouse_id', 'shift_id',
        'work_date', 'slot', 'scheduled_start_at', 'scheduled_end_at',
        'check_in_at', 'check_out_at', 'source', 'attendance_type',
        'manual_override', 'late_minutes', 'early_minutes', 'ot_minutes',
        'worked_minutes', 'work_units', 'is_holiday', 'holiday_multiplier',
        'raw', 'notes',
    ];

    protected $casts = [
        'work_date'          => 'date',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at'   => 'datetime',
        'check_in_at'        => 'datetime',
        'check_out_at'       => 'datetime',
        'manual_override'    => 'boolean',
        'is_holiday'         => 'boolean',
        'raw'                => 'array',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function schedule() { return $this->belongsTo(EmployeeWorkSchedule::class, 'employee_work_schedule_id'); }
    public function shift()    { return $this->belongsTo(Shift::class); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
}
```

### 4.4 TimekeepingSetting

```php
class TimekeepingSetting extends Model
{
    protected $fillable = [
        'warehouse_id', 'standard_hours_per_day', 'use_shift_allowances',
        'late_grace_minutes', 'early_grace_minutes',
        'allow_multiple_shifts_one_inout', 'enforce_shift_checkin_window',
        'ot_rounding_minutes', 'ot_after_minutes', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'use_shift_allowances' => 'boolean',
        'enforce_shift_checkin_window' => 'boolean',
        'allow_multiple_shifts_one_inout' => 'boolean',
    ];
}
```

### 4.5 Holiday

```php
class Holiday extends Model
{
    protected $fillable = [
        'holiday_date', 'name', 'multiplier', 'paid_leave', 'status', 'notes',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'multiplier'   => 'decimal:2',
        'paid_leave'   => 'boolean',
    ];
}
```

### 4.6 Employee (phần liên quan)

```php
class Employee extends Model
{
    protected $fillable = [
        'code', 'attendance_code', 'name', 'phone', 'email',
        'warehouse_id', 'department', 'title', 'status', /* ... */
    ];

    // Lịch làm việc
    public function schedules() { return $this->hasMany(EmployeeWorkSchedule::class); }

    // Bản ghi chấm công
    public function timekeepingRecords() { return $this->hasMany(TimekeepingRecord::class); }

    // Log chấm công thô (từ máy CC)
    public function attendanceLogs() { return $this->hasMany(AttendanceLog::class); }
}
```

> **`attendance_code`** là cầu nối giữa `device_user_id` (trên máy CC) và nhân viên trong hệ thống.

---

## 5. Backend — Controllers

### 5.1 ShiftController — Quản lý ca

```php
class ShiftController extends Controller
{
    // GET /api/shifts — Danh sách ca (filter theo warehouse, status)
    public function index(Request $request)
    {
        $query = Shift::query()->with(['warehouse:id,name']);
        if ($request->filled('warehouse_id')) $query->where('warehouse_id', $request->integer('warehouse_id'));
        if ($request->filled('status'))       $query->where('status', $request->string('status'));
        return response()->json(['success' => true, 'data' => $query->orderByDesc('id')->get()]);
    }

    // POST /api/shifts — Tạo ca mới
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'start_time'          => 'required|date_format:H:i',
            'end_time'            => 'required|date_format:H:i',
            'checkin_start_time'  => 'nullable|date_format:H:i',
            'checkin_end_time'    => 'nullable|date_format:H:i',
            'allow_late_minutes'  => 'nullable|integer|min:0|max:1440',
            'allow_early_minutes' => 'nullable|integer|min:0|max:1440',
            'is_overnight'        => 'nullable|boolean',
            // ...
        ]);

        // ★ Tự phát hiện ca đêm nếu end_time ≤ start_time
        $isOvernight = $data['is_overnight'] ?? ($this->timeToMinutes($data['end_time']) <= $this->timeToMinutes($data['start_time']));

        $shift = Shift::create([...$data, 'is_overnight' => $isOvernight]);
        return response()->json(['success' => true, 'data' => $shift], 201);
    }

    // PUT /api/shifts/{shift} — Cập nhật ca
    // PATCH /api/shifts/{shift}/toggle — Bật/tắt ca
    // DELETE /api/shifts/{shift} — Xóa ca
}
```

### 5.2 EmployeeWorkScheduleController — Quản lý lịch

```php
class EmployeeWorkScheduleController extends Controller
{
    // GET /api/employee-schedules — Danh sách lịch (filter: employee, warehouse, from/to)
    public function index(Request $request)
    {
        $query = EmployeeWorkSchedule::query()->with([
            'employee:id,code,name',
            'warehouse:id,name',
            'shift:id,name,start_time,end_time',
            // ★ Load kèm kết quả chấm công để frontend hiển thị ngay
            'timekeepingRecord:id,employee_work_schedule_id,attendance_type,manual_override,
              scheduled_start_at,scheduled_end_at,check_in_at,check_out_at,source,
              late_minutes,early_minutes,ot_minutes,worked_minutes,notes',
        ]);

        // Filters...
        if ($request->filled('employee_id')) $query->where('employee_id', $request->integer('employee_id'));
        if ($request->filled('from'))        $query->whereDate('work_date', '>=', $request->date('from'));
        if ($request->filled('to'))          $query->whereDate('work_date', '<=', $request->date('to'));

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('work_date')->paginate(5000)->items(),
        ]);
    }

    // POST /api/employee-schedules — Tạo/cập nhật lịch (upsert theo employee+date+slot)
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'work_date'   => 'required|date',
            'slot'        => 'nullable|integer|min:1|max:20',
            'shift_id'    => 'nullable|integer|exists:shifts,id',
            'start_time'  => 'nullable',
            'end_time'    => 'nullable',
            'shift_name'  => 'nullable|string|max:255',
            'status'      => 'nullable|string',
            'notes'       => 'nullable|string',
        ]);

        $slot = (int) ($data['slot'] ?? 1);

        // ★ Upsert: nếu đã có lịch cho NV + ngày + slot → cập nhật
        $schedule = EmployeeWorkSchedule::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date'], 'slot' => $slot],
            [
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'shift_id'     => $data['shift_id'] ?? null,
                'start_time'   => $data['start_time'] ?? null,
                'end_time'     => $data['end_time'] ?? null,
                'shift_name'   => $data['shift_name'] ?? null,
                'status'       => $data['status'] ?? 'planned',
            ]
        );

        return response()->json(['success' => true, 'data' => $schedule->load(...)]);
    }

    // PUT /api/employee-schedules/{schedule} — Cập nhật
    // DELETE /api/employee-schedules/{schedule} — Xóa
}
```

### 5.3 TimekeepingRecordController — Bảng chấm công

```php
class TimekeepingRecordController extends Controller
{
    public function __construct(private readonly TimekeepingService $timekeepingService) {}

    // GET /api/timekeeping-records — Xem bảng chấm công
    public function index(Request $request) { /* filter employee, warehouse, from, to */ }

    // POST /api/timekeeping-records — Chấm công thủ công
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_work_schedule_id' => 'required|integer|exists:employee_work_schedules,id',
            'attendance_type'           => 'nullable|in:work,leave_paid,leave_unpaid',
            'check_in_time'             => 'nullable|date_format:H:i',
            'check_out_time'            => 'nullable|date_format:H:i',
            'ot_minutes'                => 'nullable|integer|min:0|max:1440',
            'notes'                     => 'nullable|string',
        ]);

        // Load schedule + shift + setting
        $schedule = EmployeeWorkSchedule::with('shift')->findOrFail($data['employee_work_schedule_id']);
        $setting = TimekeepingSetting::where('warehouse_id', $schedule->warehouse_id)->first()
                   ?? TimekeepingSetting::whereNull('warehouse_id')->first();

        // Tính scheduleStart / scheduleEnd
        $scheduleStart = $this->buildScheduleDateTime($schedule->work_date, $schedule->start_time, $schedule->shift?->start_time);
        $scheduleEnd   = $this->buildScheduleDateTime($schedule->work_date, $schedule->end_time, $schedule->shift?->end_time);
        if ($scheduleEnd <= $scheduleStart) $scheduleEnd->addDay(); // ca đêm

        // Tính checkIn / checkOut từ input
        $checkInAt  = $data['check_in_time'] ? Carbon::parse($schedule->work_date . ' ' . $data['check_in_time']) : null;
        $checkOutAt = $data['check_out_time'] ? Carbon::parse($schedule->work_date . ' ' . $data['check_out_time']) : null;
        if ($checkOutAt && $checkInAt && $checkOutAt <= $checkInAt) $checkOutAt->addDay(); // ca đêm

        // Tính late, early, OT, worked
        $lateMinutes  = /* ... */;
        $earlyMinutes = /* ... */;
        $otMinutes    = /* ... */;
        $workedMinutes = $checkInAt && $checkOutAt ? $checkOutAt->diffInMinutes($checkInAt) : 0;

        // ★ Upsert + đánh dấu manual_override = true
        $record = TimekeepingRecord::updateOrCreate(
            ['employee_work_schedule_id' => $schedule->id],
            [
                'employee_id'   => $schedule->employee_id,
                'check_in_at'   => $checkInAt,
                'check_out_at'  => $checkOutAt,
                'source'        => 'manual',
                'manual_override' => true,  // ★ Quan trọng: không bị ghi đè bởi recalculate
                'late_minutes'  => $lateMinutes,
                'early_minutes' => $earlyMinutes,
                'ot_minutes'    => $otMinutes,
                'worked_minutes' => $workedMinutes,
                // ...
            ]
        );

        return response()->json(['success' => true, 'data' => $record]);
    }

    // POST /api/timekeeping-records/recalculate — Tính lại chấm công cho khoảng ngày
    public function recalculate(Request $request)
    {
        $data = $request->validate([
            'from'        => 'required|date',
            'to'          => 'required|date',
            'employee_id' => 'nullable|integer',
        ]);

        $result = $this->timekeepingService->recalculateForRange(
            Carbon::parse($data['from']),
            Carbon::parse($data['to']),
            $data['employee_id'] ?? null
        );

        return response()->json(['success' => true, 'data' => $result]);
    }
}
```

### 5.4 TimekeepingSettingController — Cài đặt chấm công

```php
// GET /api/timekeeping-settings?warehouse_id=1  — Lấy cài đặt (trả default nếu chưa có)
// POST /api/timekeeping-settings                — Upsert cài đặt (tạo mới hoặc cập nhật)
```

### 5.5 HolidayController — Ngày lễ

```php
// GET    /api/holidays                — Danh sách ngày lễ
// POST   /api/holidays                — Tạo ngày lễ  
// PUT    /api/holidays/{id}           — Cập nhật
// DELETE /api/holidays/{id}           — Xóa
// POST   /api/holidays/auto-generate  — Tự tạo ngày lễ Việt Nam cho 1 năm

public function autoGenerate(Request $request)
{
    $year = $request->integer('year', now()->year);
    
    $holidays = [
        ['date' => "{$year}-01-01", 'name' => 'Tết Dương lịch', 'multiplier' => 2],
        ['date' => "{$year}-04-30", 'name' => 'Giải phóng miền Nam', 'multiplier' => 2],
        ['date' => "{$year}-05-01", 'name' => 'Quốc tế Lao động', 'multiplier' => 2],
        ['date' => "{$year}-09-02", 'name' => 'Quốc khánh', 'multiplier' => 2],
        // + Tết Nguyên Đán (5 ngày), Giỗ Tổ Hùng Vương (âm lịch → dương lịch)
    ];
    
    foreach ($holidays as $h) {
        Holiday::updateOrCreate(['holiday_date' => $h['date']], $h);
    }
}
```

---

## 6. Backend — TimekeepingService (Lõi tính công)

### 6.1 Thuật toán `recalculateForRange()`

Đây là **trái tim** của hệ thống — match log chấm công thô vào lịch làm việc để tạo ra bản ghi chấm công.

```php
class TimekeepingService
{
    public function recalculateForRange(Carbon $from, Carbon $to, ?int $employeeId = null): array
    {
        // ═══════════════════════════════════════════
        // BƯỚC 1: Load dữ liệu tham chiếu
        // ═══════════════════════════════════════════
        
        // 1a. Ngày lễ trong khoảng
        $holidayMap = Holiday::whereBetween('holiday_date', [$from, $to])
            ->where('status', 'active')
            ->get()->keyBy(fn($h) => $h->holiday_date->toDateString());

        // 1b. Lịch làm việc trong khoảng
        $schedules = EmployeeWorkSchedule::whereBetween('work_date', [$from, $to])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->orderBy('work_date')->orderBy('slot')
            ->get();

        // 1c. Pre-load shifts và settings (tránh N+1)
        $shifts = Shift::whereIn('id', $schedules->pluck('shift_id')->filter())->get()->keyBy('id');
        $settings = TimekeepingSetting::where('status', 'active')->get()
            ->keyBy(fn($s) => $s->warehouse_id ?? 'global');
        $globalSetting = $settings->get('global');

        $created = 0; $updated = 0;

        // ═══════════════════════════════════════════
        // BƯỚC 2: Xử lý từng schedule
        // ═══════════════════════════════════════════
        foreach ($schedules as $schedule) {
            
            // 2a. ★ Skip bản ghi đã chỉnh tay
            $existing = TimekeepingRecord::where('employee_work_schedule_id', $schedule->id)->first();
            if ($existing && $existing->manual_override) continue;

            $shift = $schedule->shift_id ? $shifts->get($schedule->shift_id) : null;
            $setting = $settings->get((string)$schedule->warehouse_id) ?? $globalSetting;

            // ═══════════════════════════════════════════
            // BƯỚC 3: Xác định thời gian ca
            // ═══════════════════════════════════════════
            // Ưu tiên: schedule.start_time → shift.start_time
            $scheduleStart = $this->buildScheduleDateTime(
                $schedule->work_date,
                $schedule->start_time,     // override từ schedule
                $shift?->start_time        // fallback từ shift
            );
            $scheduleEnd = $this->buildScheduleDateTime(
                $schedule->work_date,
                $schedule->end_time,
                $shift?->end_time
            );

            // ★ Xử lý ca đêm: nếu end ≤ start → cộng 1 ngày
            if ($scheduleStart && $scheduleEnd && $scheduleEnd <= $scheduleStart) {
                $scheduleEnd->addDay();
            }

            // ═══════════════════════════════════════════
            // BƯỚC 4: Tìm log chấm công trong cửa sổ ±8h
            // ═══════════════════════════════════════════
            $windowStart = ($scheduleStart ?? Carbon::parse($schedule->work_date))->copy()->subHours(8);
            $windowEnd   = ($scheduleEnd ?? Carbon::parse($schedule->work_date)->endOfDay())->copy()->addHours(8);

            $logs = AttendanceLog::where('employee_id', $schedule->employee_id)
                ->whereBetween('punched_at', [$windowStart, $windowEnd])
                ->orderBy('punched_at')
                ->get();

            // ═══════════════════════════════════════════
            // BƯỚC 5: Xác định check-in / check-out
            // ═══════════════════════════════════════════
            $checkIn = null; $checkOut = null;

            // 5a. Nếu có cửa sổ check-in (enforce_shift_checkin_window + shift có checkin window)
            if ($setting?->enforce_shift_checkin_window && $shift?->checkin_start_time && $logs->isNotEmpty()) {
                $winStart = $this->buildScheduleDateTime($schedule->work_date, $shift->checkin_start_time, null);
                $winEnd   = $this->buildScheduleDateTime($schedule->work_date, $shift->checkin_end_time, null);
                if ($winEnd <= $winStart) $winEnd->addDay();

                // Tìm log ĐẦU TIÊN trong cửa sổ
                $first = $logs->first(fn($l) => $l->punched_at >= $winStart && $l->punched_at <= $winEnd);
                if ($first) {
                    $checkIn = $first->punched_at;
                    // Check-out = log CUỐI CÙNG sau check-in (khác log check-in)
                    $last = $logs->last(fn($l) => $l->id !== $first->id && $l->punched_at > $checkIn);
                    $checkOut = $last?->punched_at;
                }
            }

            // 5b. Fallback: log đầu = vào, log cuối = ra
            if (!$checkIn && $logs->isNotEmpty()) {
                $checkIn = $logs->first()->punched_at;
                
                // ★ QUAN TRỌNG: Chỉ set check-out nếu có NHIỀU HƠN 1 log
                //   Tránh lỗi check_in == check_out khi chỉ có 1 log
                if ($logs->count() > 1 && $logs->last()->id !== $logs->first()->id) {
                    $checkOut = $logs->last()->punched_at;
                }
            }

            // ═══════════════════════════════════════════
            // BƯỚC 6: Tính toán chỉ số
            // ═══════════════════════════════════════════
            $useShiftAllowances = (bool)($setting?->use_shift_allowances ?? true);
            $allowLate  = $useShiftAllowances ? ($shift?->allow_late_minutes ?? 0) : ($setting?->late_grace_minutes ?? 0);
            $allowEarly = $useShiftAllowances ? ($shift?->allow_early_minutes ?? 0) : ($setting?->early_grace_minutes ?? 0);
            $otAfter    = (int)($setting?->ot_after_minutes ?? 0);
            $otRounding = (int)($setting?->ot_rounding_minutes ?? 0);

            $lateMinutes = $earlyMinutes = $otMinutes = $workedMinutes = 0;

            // Phút làm việc
            if ($checkIn && $checkOut) {
                $workedMinutes = max(0, Carbon::parse($checkOut)->diffInMinutes(Carbon::parse($checkIn)));
            }

            // ★ Đi muộn
            if ($scheduleStart && $checkIn) {
                $diff = Carbon::parse($checkIn)->diffInMinutes($scheduleStart, false);
                $lateMinutes = max(0, $diff - $allowLate);
            }

            // ★ Về sớm vs Tăng ca (FIX QUAN TRỌNG)
            if ($scheduleEnd && $checkOut) {
                $checkOutCarbon = Carbon::parse($checkOut);
                
                if ($checkOutCarbon->lessThan($scheduleEnd)) {
                    // ← Checkout TRƯỚC giờ kết thúc = Về sớm
                    $diffEarly = $scheduleEnd->diffInMinutes($checkOutCarbon);
                    $earlyMinutes = max(0, $diffEarly - $allowEarly);
                } elseif ($checkOutCarbon->greaterThan($scheduleEnd)) {
                    // ← Checkout SAU giờ kết thúc = Tăng ca
                    $rawOt = $checkOutCarbon->diffInMinutes($scheduleEnd);
                    $rawOt = max(0, $rawOt - $otAfter);
                    if ($otRounding > 0) {
                        $rawOt = intdiv($rawOt, $otRounding) * $otRounding;
                    }
                    $otMinutes = $rawOt;
                }
                // == giờ kết thúc → không sớm, không OT
            }

            // ═══════════════════════════════════════════
            // BƯỚC 7: Lưu kết quả (upsert)
            // ═══════════════════════════════════════════
            $holiday = $holidayMap->get(Carbon::parse($schedule->work_date)->toDateString());

            $attributes = [
                'employee_id'               => $schedule->employee_id,
                'employee_work_schedule_id' => $schedule->id,
                'warehouse_id'              => $schedule->warehouse_id,
                'shift_id'                  => $schedule->shift_id,
                'work_date'                 => $schedule->work_date,
                'slot'                      => $schedule->slot ?? 1,
                'scheduled_start_at'        => $scheduleStart,
                'scheduled_end_at'          => $scheduleEnd,
                'check_in_at'               => $checkIn,
                'check_out_at'              => $checkOut,
                'source'                    => $logs->isNotEmpty() ? 'device' : 'none',
                'attendance_type'           => 'work',
                'manual_override'           => false,
                'late_minutes'              => $lateMinutes,
                'early_minutes'             => $earlyMinutes,
                'ot_minutes'                => $otMinutes,
                'worked_minutes'            => $workedMinutes,
                'is_holiday'                => (bool)$holiday,
                'holiday_multiplier'        => $holiday ? (float)$holiday->multiplier : 1,
                'raw' => [
                    'log_ids'    => $logs->pluck('id')->values()->all(),
                    'device_ids' => $logs->pluck('attendance_device_id')->unique()->values()->all(),
                ],
            ];

            if ($existing) {
                $existing->fill($attributes)->save();
                $updated++;
            } else {
                TimekeepingRecord::create($attributes);
                $created++;
            }
        }

        return compact('created', 'updated');
    }

    // Helper: Tạo datetime từ work_date + time string
    private function buildScheduleDateTime($workDate, $scheduleTime, $fallbackShiftTime): ?Carbon
    {
        $time = $scheduleTime ?? $fallbackShiftTime;
        if (!$time) return null;
        return Carbon::parse($workDate)->startOfDay()->setTimeFromTimeString((string)$time);
    }
}
```

### 6.2 Sơ đồ thuật toán

```
Cho mỗi schedule trong khoảng [from, to]:
│
├── Nếu manual_override = true → BỎ QUA (không ghi đè)
│
├── Tính scheduleStart, scheduleEnd (từ schedule hoặc shift, +1 ngày nếu ca đêm)
│
├── Tìm logs: AttendanceLog WHERE employee_id AND punched_at BETWEEN [start-8h, end+8h]
│
├── Nếu enforce_shift_checkin_window:
│   └── Tìm log ĐẦU TIÊN trong checkin_window → checkIn
│       Tìm log CUỐI CÙNG sau checkIn → checkOut
│
├── Nếu không: logs.first() → checkIn, logs.last() → checkOut (≠ first)
│
├── Tính:
│   ├── lateMinutes  = max(0, (checkIn - scheduleStart) - allowLate)
│   ├── earlyMinutes = checkOut < scheduleEnd ? max(0, (scheduleEnd - checkOut) - allowEarly) : 0
│   ├── otMinutes    = checkOut > scheduleEnd ? max(0, (checkOut - scheduleEnd) - otAfter) : 0
│   └── workedMinutes = checkOut - checkIn
│
└── Upsert TimekeepingRecord (unique trên employee_work_schedule_id)
```

### 6.3 Lỗi thường gặp và cách fix

| Lỗi | Nguyên nhân | Fix |
|-----|-------------|-----|
| "Về sớm 79p" khi thực tế ra SAU giờ | `diffInMinutes()` luôn trả dương | Phải check `checkOut.lessThan(scheduleEnd)` TRƯỚC khi tính |
| check_in == check_out | Chỉ có 1 log trong ngày | Chỉ set checkOut khi `logs.count() > 1` VÀ `last.id != first.id` |
| Ca đêm tính sai | scheduleEnd < scheduleStart | Phải `addDay()` cho scheduleEnd khi phát hiện ca đêm |
| manual_override bị ghi đè | Quên check flag | `if ($existing && $existing->manual_override) continue;` |

---

## 7. Backend — Job tính công tự động

```php
class RecalculateTimekeepingForRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly array $employeeIds = [],
    ) {}

    public function handle(TimekeepingService $service): void
    {
        $from = Carbon::parse($this->from);
        $to   = Carbon::parse($this->to);

        if (empty($this->employeeIds)) return;

        foreach ($this->employeeIds as $empId) {
            $service->recalculateForRange($from, $to, $empId);
        }
    }
}
```

**Cách gọi:**

```php
// Chạy đồng bộ (không cần queue worker) — khuyến nghị
RecalculateTimekeepingForRangeJob::dispatchSync($from, $to, $employeeIds);

// Chạy qua queue (cần php artisan queue:listen)
RecalculateTimekeepingForRangeJob::dispatch($from, $to, $employeeIds);
```

> **Lưu ý:** Dùng `dispatchSync()` khi cần kết quả ngay lập tức (VD: sau khi agent push log).

---

## 8. Backend — Artisan Command

```php
// php artisan attendance:refresh [--days=7] [--from=YYYY-MM-DD] [--to=YYYY-MM-DD] [--skip-mapping]

class AttendanceRefreshCommand extends Command
{
    protected $signature = 'attendance:refresh
        {--days=7 : Số ngày gần nhất}
        {--from= : Ngày bắt đầu}
        {--to= : Ngày kết thúc}
        {--skip-mapping : Bỏ qua bước mapping}';

    public function handle(TimekeepingService $service)
    {
        // Bước 1: Map attendance_code → employee_id cho log chưa map
        if (!$this->option('skip-mapping')) {
            // ... UPDATE attendance_logs SET employee_id = ...
        }

        // Bước 2: Tính lại timekeeping_records
        $service->recalculateForRange($from, $to);
    }
}
```

**Dùng khi nào:**
- Debug: `php artisan attendance:refresh --days=30`
- Cron: chạy mỗi 10 phút tự động refresh 3 ngày gần nhất
- Sau khi import nhân viên mới có `attendance_code`

---

## 9. Routes — API & Web

### 9.1 API Routes

```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {

    // ══════ Ca làm việc ══════
    Route::prefix('shifts')->middleware('permission:staff.view')->group(function () {
        Route::get('/', [ShiftController::class, 'index']);
        Route::get('/{shift}', [ShiftController::class, 'show']);
        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/', [ShiftController::class, 'store']);
            Route::put('/{shift}', [ShiftController::class, 'update']);
            Route::patch('/{shift}/toggle', [ShiftController::class, 'toggle']);
            Route::delete('/{shift}', [ShiftController::class, 'destroy']);
        });
    });

    // ══════ Lịch làm việc ══════
    Route::prefix('employee-schedules')->middleware('permission:staff.view')->group(function () {
        Route::get('/', [EmployeeWorkScheduleController::class, 'index']);
        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/', [EmployeeWorkScheduleController::class, 'store']);
            Route::put('/{schedule}', [EmployeeWorkScheduleController::class, 'update']);
            Route::delete('/{schedule}', [EmployeeWorkScheduleController::class, 'destroy']);
        });
    });

    // ══════ Ngày lễ ══════
    Route::prefix('holidays')->middleware('permission:staff.view')->group(function () {
        Route::get('/', [HolidayController::class, 'index']);
        Route::get('/{holiday}', [HolidayController::class, 'show']);
        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/', [HolidayController::class, 'store']);
            Route::post('/auto-generate', [HolidayController::class, 'autoGenerate']);
            Route::put('/{holiday}', [HolidayController::class, 'update']);
            Route::delete('/{holiday}', [HolidayController::class, 'destroy']);
        });
    });

    // ══════ Cài đặt chấm công ══════
    Route::prefix('timekeeping-settings')->middleware('permission:staff.view')->group(function () {
        Route::get('/', [TimekeepingSettingController::class, 'show']);
        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/', [TimekeepingSettingController::class, 'upsert']);
        });
    });

    // ══════ Bảng chấm công ══════
    Route::prefix('timekeeping-records')->middleware('permission:staff.view')->group(function () {
        Route::get('/', [TimekeepingRecordController::class, 'index']);
        Route::get('/{timekeepingRecord}', [TimekeepingRecordController::class, 'show']);
        Route::middleware('permission:staff.manage')->group(function () {
            Route::post('/', [TimekeepingRecordController::class, 'store']);
            Route::put('/{timekeepingRecord}', [TimekeepingRecordController::class, 'update']);
            Route::post('/recalculate', [TimekeepingRecordController::class, 'recalculate']);
        });
    });
});
```

### 9.2 Web Routes

```php
Route::middleware(['auth', 'permission:staff.view'])->group(function () {
    Route::get('/employees/schedules', fn() => view('employees.schedules'));
    Route::get('/employees/attendance', fn() => view('employees.attendance'));
    Route::get('/employees/settings', fn() => view('employees.settings'));
});
```

### 9.3 Vite Entry Points

```js
// vite.config.js
input: [
    'resources/js/employee-schedules-app.js',  // Lịch làm việc
    'resources/js/attendance-app.js',           // Bảng chấm công
]
```

---

## 10. Frontend — API Client

```js
// resources/js/api/employeeApi.js
import axios from 'axios'

const API_BASE = '/api'
const getHeaders = () => {
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json' }
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken
    const token = sessionStorage.getItem('api_token') || document.querySelector('meta[name="api-token"]')?.getAttribute('content')
    if (token) headers['Authorization'] = `Bearer ${token}`
    return headers
}

export const employeeApi = {
    // Nhân viên
    getEmployees: (params = {}) => axios.get(`${API_BASE}/employees`, { params, headers: getHeaders() }),

    // Ca làm việc
    getShifts:   (params = {}) => axios.get(`${API_BASE}/shifts`, { params, headers: getHeaders() }),
    createShift: (data) => axios.post(`${API_BASE}/shifts`, data, { headers: getHeaders() }),
    updateShift: (id, data) => axios.put(`${API_BASE}/shifts/${id}`, data, { headers: getHeaders() }),
    toggleShift: (id) => axios.patch(`${API_BASE}/shifts/${id}/toggle`, {}, { headers: getHeaders() }),
    deleteShift: (id) => axios.delete(`${API_BASE}/shifts/${id}`, { headers: getHeaders() }),

    // Lịch làm việc
    getSchedules:    (params = {}) => axios.get(`${API_BASE}/employee-schedules`, { params, headers: getHeaders() }),
    saveSchedule:    (data) => axios.post(`${API_BASE}/employee-schedules`, data, { headers: getHeaders() }),
    updateSchedule:  (id, data) => axios.put(`${API_BASE}/employee-schedules/${id}`, data, { headers: getHeaders() }),
    deleteSchedule:  (id) => axios.delete(`${API_BASE}/employee-schedules/${id}`, { headers: getHeaders() }),

    // Log chấm công (thô)
    getAttendanceLogs: (params = {}) => axios.get(`${API_BASE}/attendance-logs`, { params, headers: getHeaders() }),

    // Bảng chấm công (đã tính)
    getTimekeepingRecords:    (params = {}) => axios.get(`${API_BASE}/timekeeping-records`, { params, headers: getHeaders() }),
    upsertTimekeepingRecord:  (data) => axios.post(`${API_BASE}/timekeeping-records`, data, { headers: getHeaders() }),
    recalculateTimekeeping:   (data) => axios.post(`${API_BASE}/timekeeping-records/recalculate`, data, { headers: getHeaders() }),
}

export default employeeApi
```

---

## 11. Frontend — Màn hình Lịch làm việc

### 11.1 Cấu trúc

```
resources/js/employee-schedules-app.js  ← Vue entry
  └── EmployeeSchedules.vue             ← Component chính
```

### 11.2 Tính năng

| Tính năng | Mô tả |
|-----------|-------|
| **Bảng tuần** | Hàng = nhân viên, cột = thứ 2 → CN. Click ô để thêm/sửa lịch |
| **Chọn ca** | Dropdown danh sách ca. Tạo ca mới ngay trong modal (nested modal) |
| **Multi-slot** | Mỗi ô hiển thị nhiều ca (slot 1, 2, 3...) |
| **Lặp tuần** | Option "Lặp lại hàng tuần" → tự tạo lịch 4 tuần tiếp theo |
| **Áp cho NV khác** | Option chọn NV khác → tạo lịch giống hệt cho họ |
| **Điều hướng** | Tuần trước ‹ | Tuần hiện tại | › Tuần sau |
| **Filter** | Tìm NV, lọc theo NV cụ thể |

### 11.3 Luồng dữ liệu

```
Mount → loadEmployees() + loadShifts() + load()
         │                  │               │
         ▼                  ▼               ▼
   GET /employees    GET /shifts    GET /employee-schedules?from=...&to=...
                                            │
                          ┌─────────────────┘
                          ▼
              Render bảng tuần (EmployeeSchedules.vue)
                          │
              User click ô → openAddFromGrid(employee, date)
                          │
              User chọn ca + options → save()
                          │
                          ▼
              POST /employee-schedules (upsert)
              Nếu repeatWeekly → tạo thêm 4 tuần
              Nếu applyToOthers → tạo cho NV khác
                          │
                          ▼
              load() → refresh bảng
```

### 11.4 Code quan trọng

**Tạo lịch lặp tuần + cho NV khác:**
```js
const save = async () => {
    // Danh sách NV: NV hiện tại + NV khác (nếu chọn)
    const employeeIds = unique([form.employee_id, ...(options.applyToOthers ? options.otherEmployeeIds : [])])
    
    // Danh sách ngày: ngày hiện tại + 4 tuần (nếu repeatWeekly)
    const dates = buildDatesForCreate(form.work_date, options.repeatWeekly)
    
    // Gửi song song tất cả
    await Promise.all(
        employeeIds.flatMap(employeeId =>
            dates.map(dateYmd => employeeApi.saveSchedule({
                employee_id: employeeId,
                work_date: dateYmd,
                slot: form.slot,
                shift_id: form.shift_id,
                // ...
            }))
        )
    )
}
```

---

## 12. Frontend — Màn hình Bảng chấm công

### 12.1 Cấu trúc

```
resources/js/attendance-app.js  ← Vue entry
  └── AttendanceSheet.vue       ← Component chính
```

### 12.2 Tính năng

| Tính năng | Mô tả |
|-----------|-------|
| **Bảng tuần theo ca** | Hàng = ca làm việc, cột = thứ 2 → CN. Mỗi ô = nhân viên trong ca đó |
| **Màu trạng thái** | Xanh = đúng giờ, Tím = muộn/sớm, Đỏ = thiếu log, Cam = chưa CC, Xám = nghỉ |
| **Modal chấm công** | Click vào thẻ NV → mở modal: chọn type (đi làm/nghỉ phép), nhập giờ vào/ra, OT |
| **Tab lịch sử** | Xem log chấm công thô từ máy trong ngày đó |
| **Duyệt CC** | Nút "Duyệt chấm công" → gọi recalculate cho cả tuần |
| **Điều hướng** | Tuần trước/sau, tuần hiện tại |

### 12.3 Cách hiển thị dữ liệu

```
API trả schedules (kèm timekeepingRecord):
[
  {
    id: 1, employee_id: 1, shift_id: 1, work_date: "2026-03-04",
    employee: { id: 1, name: "Nguyễn A", code: "NV001" },
    shift: { id: 1, name: "Hành chính", start_time: "08:00", end_time: "17:00" },
    timekeepingRecord: {
      check_in_at: "2026-03-04 08:05:00",
      check_out_at: "2026-03-04 17:30:00",
      late_minutes: 0,
      early_minutes: 0,
      ot_minutes: 30,
      attendance_type: "work"
    }
  }
]

→ buildDerivedState(schedule, record) → { state: 'on_time', label: 'Đúng giờ', checkInText: '08:05', checkOutText: '17:30', meta: 'Làm thêm 0h 30p' }
→ Render thẻ xanh: "Nguyễn A | 08:05 - 17:30 | Làm thêm 0h 30p"
```

### 12.4 Trạng thái hiển thị

```js
function buildDerivedState(schedule, record) {
    const attendanceType = record?.attendance_type || 'work'

    // Nghỉ
    if (attendanceType === 'leave_paid')   return { state: 'leave', label: 'Nghỉ có phép', ... }
    if (attendanceType === 'leave_unpaid') return { state: 'leave', label: 'Nghỉ không phép', ... }

    // Chưa chấm công
    if (!record?.check_in_at && !record?.check_out_at) return { state: 'not_checked', ... }

    // Thiếu log (chỉ có vào hoặc chỉ có ra)
    if (!!record?.check_in_at !== !!record?.check_out_at) return { state: 'missing', ... }

    // Đi muộn / về sớm
    if (record.late_minutes > 0 || record.early_minutes > 0) return { state: 'late_early', ... }

    // Đúng giờ (có thể + OT)
    return { state: 'on_time', ... }
}
```

### 12.5 Modal chấm công thủ công

```
User click thẻ NV → openAttendance(item)

Modal hiện:
┌────────────────────────────────────────────┐
│ Chấm công         NV001 - Nguyễn Văn A    │
│                                            │
│ Thứ 3, 04/03/2026  Ca: Hành chính 08-17   │
│                                            │
│ ○ Đi làm   ○ Nghỉ có phép   ○ Nghỉ KP    │
│                                            │
│ ☑ Vào:  [08:05]    ☑ Ra:  [17:30]         │
│ ☐ Làm thêm: [0] giờ [30] phút            │
│                                            │
│ Ghi chú: [_________________________]       │
│                                            │
│                    [Bỏ qua] [Lưu]         │
└────────────────────────────────────────────┘

User bấm Lưu → POST /api/timekeeping-records
  payload: {
    employee_work_schedule_id: 1,
    attendance_type: 'work',
    check_in_time: '08:05',
    check_out_time: '17:30',
    ot_minutes: 30,
    notes: null
  }

→ Server tính late/early/OT, lưu manual_override = true
→ UI update in-place (không cần reload)
```

---

## 13. Mối quan hệ với máy chấm công

### 13.1 Luồng kết nối đầy đủ

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          HỆ THỐNG CHẤM CÔNG                                │
│                                                                             │
│  ┌─────────┐        ┌──────────┐        ┌──────────────────────────────┐   │
│  │ Máy CC  │──UDP──►│  Agent   │──HTTPS──►│ AttendanceAgentController │   │
│  │ (ZK)    │        │ (LAN)    │  HMAC   │                            │   │
│  └─────────┘        └──────────┘         │  pushLogs() {             │   │
│                                           │    1. Validate            │   │
│                                           │    2. Upsert logs         │──┐│
│                                           │    3. Auto-map employee   │  ││
│                                           │    4. dispatchSync(       │  ││
│                                           │       Recalculate...)     │  ││
│                                           │  }                        │  ││
│                                           └──────────────────────────┘  ││
│                                                                          ││
│  ═══════════════════════════════════════════════════════════════════════  ││
│                                                                          ▼│
│  ┌──────────────┐    ┌──────────────────────┐    ┌───────────────────┐   │
│  │ attendance_  │    │ TimekeepingService    │    │ timekeeping_     │   │
│  │ logs         │──►│ recalculateForRange() │──►│ records          │   │
│  │              │    │                       │    │                  │   │
│  │ employee_id  │    │ Match log ↔ schedule  │    │ check_in/out    │   │
│  │ punched_at   │    │ ±8h window            │    │ late/early/OT   │   │
│  │ device_user_ │    │ Tính lat/early/OT    │    │ source='device' │   │
│  │ id           │    └───────────┬───────────┘    └────────┬────────┘   │
│  └──────────────┘                │                          │            │
│                                  │                          │            │
│  ┌──────────────┐    ┌──────────┴──────────┐               │            │
│  │ employee_    │    │                     │               │            │
│  │ work_        │──►│ schedule → record   │               │            │
│  │ schedules    │    │ (1:1 qua schedule_  │               │            │
│  │              │    │  id unique)         │               │            │
│  │ employee_id  │    └─────────────────────┘               │            │
│  │ shift_id     │                                          │            │
│  │ work_date    │                                          │            │
│  │ slot         │                                          │            │
│  └──────────────┘                                          │            │
│                                                             │            │
│  ═══════════════════════════════════════════════════════════│════════════ │
│                                                             ▼            │
│  ┌─────────────────────────────────────────────────────────────────────┐ │
│  │                     Frontend (Vue)                                  │ │
│  │                                                                     │ │
│  │  AttendanceSheet.vue (Bảng chấm công)                              │ │
│  │  GET /employee-schedules?from=...&to=...                           │ │
│  │  → Schedules kèm timekeepingRecord                                 │ │
│  │  → Hiển thị check-in/out, late, OT, trạng thái màu sắc           │ │
│  │  → Click → Modal chấm công thủ công (manual_override=true)        │ │
│  │  → Nút "Duyệt" → POST /timekeeping-records/recalculate           │ │
│  └─────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 13.2 Điểm kết nối chính

| Từ | Đến | Qua | Mô tả |
|----|-----|-----|-------|
| Máy CC | `attendance_logs` | Agent + push-logs API | Log thô: ai quẹt lúc nào |
| `attendance_logs` | `employees` | `attendance_code = device_user_id` | Map log → nhân viên |
| `employee_work_schedules` | `timekeeping_records` | `TimekeepingService` | Match schedule + log → tính kết quả |
| `attendance_logs` | `timekeeping_records` | `TimekeepingService` ±8h window | Tìm log phù hợp với mỗi schedule |
| `timekeeping_records` | Frontend | API `/employee-schedules` (include relation) | Hiển thị trên bảng chấm công |

### 13.3 Khi nào recalculate được gọi?

| Trigger | Cách gọi | Mô tả |
|---------|----------|-------|
| Agent push log | `dispatchSync()` trong `pushLogs()` | Tự động sau mỗi lần push |
| Admin bấm "Duyệt" | Frontend → `POST /recalculate` | Tính lại cho tuần đang xem |
| Admin bấm "Refresh Mapping" | `refreshMapping()` rồi `dispatchSync()` | Sau khi map lại employee_id |
| Artisan command | `php artisan attendance:refresh` | Chạy thủ công hoặc cron |
| Cron (nếu cấu hình) | `attendance:refresh --days=3` mỗi 10p | Tự dynamic refresh |

### 13.4 Không có máy chấm công — vẫn hoạt động

Hệ thống lịch + chấm công hoạt động **ĐỘC LẬP** với máy CC:

- Tạo ca → Tạo lịch → Chấm công thủ công (source='manual')
- Không cần Agent, không cần `attendance_logs`
- TimekeepingService chỉ tìm log nếu có, không có thì `source='none'`

---

## 14. Luồng dữ liệu End-to-End

### Scenario 1: Nhân viên quẹt vân tay, hệ thống tự tính

```
08:05 - NV quẹt vào     → Máy CC lưu: user_id=5, timestamp=08:05
17:30 - NV quẹt ra       → Máy CC lưu: user_id=5, timestamp=17:30

Agent chạy (30s/lần):
  → Đọc log từ máy CC
  → POST /api/attendance-agent/push-logs
    Body: [
      { device_user_id: "5", punched_at: "2026-03-04T08:05:00+07:00" },
      { device_user_id: "5", punched_at: "2026-03-04T17:30:00+07:00" }
    ]

Server xử lý:
  → Upsert 2 dòng vào attendance_logs
  → Map: employee(attendance_code="5") → employee_id = 1
  → Tự động recalculate:
    - Tìm schedule: NV id=1, ngày 04/03, ca Hành chính 08:00-17:00
    - Tìm 2 log trong window ±8h
    - checkIn = 08:05, checkOut = 17:30
    - late = max(0, 5 - 5) = 0 (tha 5p)
    - early = 0 (checkOut > scheduleEnd)
    - OT = max(0, 30 - 0) = 30p
    - worked = 17:30 - 08:05 = 565p
  → Upsert timekeeping_record: source='device', late=0, early=0, ot=30

Frontend hiện: 🟦 Nguyễn A | 08:05 - 17:30 | Làm thêm 0h 30p
```

### Scenario 2: Admin chấm công thủ công

```
Admin mở Bảng chấm công → Click vào thẻ NV B (ngày 04/03)
  → Chọn "Đi làm" → Nhập: Vào 08:00, Ra 17:00
  → Bấm Lưu

→ POST /api/timekeeping-records
  Body: { employee_work_schedule_id: 5, attendance_type: 'work', check_in_time: '08:00', check_out_time: '17:00' }

→ Server: updateOrCreate timekeeping_record:
  - source = 'manual'
  - manual_override = true   ← ★ không bị ghi đè bởi recalculate
  - late = 0, early = 0, OT = 0, worked = 540p

Frontend cập nhật ngay: 🟦 Nguyễn B | 08:00 - 17:00
```

### Scenario 3: Admin đánh dấu nghỉ phép

```
Admin click thẻ NV C → Chọn "Nghỉ có phép" → Lưu

→ POST timekeeping-records: attendance_type = 'leave_paid', manual_override = true

Frontend hiện: ⬜ Nguyễn C | Nghỉ có phép
```

---

## 15. Checklist triển khai

### 15.1 Database

- [ ] Migration: `shifts` (ca làm việc)
- [ ] Migration: `employee_work_schedules` (thêm `warehouse_id`, `shift_id`, `slot`, unique constraint)
- [ ] Migration: `timekeeping_records`
- [ ] Migration: `timekeeping_settings`
- [ ] Migration: `holidays`
- [ ] Migration: thêm `attendance_type`, `manual_override` vào `timekeeping_records`
- [ ] Migration: thêm `checkin_start_time`, `checkin_end_time` vào `shifts`
- [ ] Chạy `php artisan migrate`

### 15.2 Backend

- [ ] Model: `Shift` (với appends: duration, work_time_text)
- [ ] Model: `EmployeeWorkSchedule` (với relation `timekeepingRecord`)
- [ ] Model: `TimekeepingRecord`
- [ ] Model: `TimekeepingSetting`
- [ ] Model: `Holiday`
- [ ] Controller: `ShiftController` (CRUD + toggle)
- [ ] Controller: `EmployeeWorkScheduleController` (CRUD + upsert)
- [ ] Controller: `TimekeepingRecordController` (CRUD + store manual + recalculate)
- [ ] Controller: `TimekeepingSettingController` (show + upsert)
- [ ] Controller: `HolidayController` (CRUD + auto-generate)
- [ ] Service: `TimekeepingService::recalculateForRange()`
- [ ] Job: `RecalculateTimekeepingForRangeJob`
- [ ] Command: `AttendanceRefreshCommand`
- [ ] Routes API đăng ký đầy đủ

### 15.3 Frontend

- [ ] `employeeApi.js` — tất cả endpoint functions
- [ ] `EmployeeSchedules.vue` — màn lịch làm việc (bảng tuần, modal tạo/sửa, tạo ca mới, lặp tuần)
- [ ] `AttendanceSheet.vue` — bảng chấm công (bảng tuần theo ca, modal CC thủ công, tab lịch sử, duyệt CC)
- [ ] Blade views: `schedules.blade.php`, `attendance.blade.php`
- [ ] Vite entry: `employee-schedules-app.js`, `attendance-app.js`
- [ ] Web routes: `/employees/schedules`, `/employees/attendance`

### 15.4 Dữ liệu khởi tạo

- [ ] Tạo ít nhất 1 ca làm việc (VD: Hành chính 08:00-17:00)
- [ ] Tạo lịch làm việc cho nhân viên (ít nhất 1 tuần)
- [ ] Cấu hình timekeeping_settings (tha muộn, OT...)
- [ ] (Tùy chọn) Seed ngày lễ: `php artisan db:seed --class=HolidaySeeder`

### 15.5 Kết nối máy chấm công (tùy chọn)

> Xem file: **ATTENDANCE_DEVICE_INTEGRATION_GUIDE.md**

- [ ] Cài Agent (PHP hoặc C#) trên máy Windows LAN
- [ ] Cấu hình `ATTENDANCE_AGENT_SECRET` trên server
- [ ] Nhập `attendance_code` cho nhân viên (khớp User ID trên máy)
- [ ] Agent push log → Server auto recalculate → Bảng CC hiện kết quả

---

## Phụ lục: Tổng hợp API

| Method | Endpoint | Mục đích |
|--------|----------|----------|
| GET | `/api/shifts` | Danh sách ca |
| POST | `/api/shifts` | Tạo ca |
| PUT | `/api/shifts/{id}` | Sửa ca |
| PATCH | `/api/shifts/{id}/toggle` | Bật/tắt ca |
| DELETE | `/api/shifts/{id}` | Xóa ca |
| GET | `/api/employee-schedules` | Danh sách lịch (filter: employee, from, to) |
| POST | `/api/employee-schedules` | Tạo/upsert lịch |
| PUT | `/api/employee-schedules/{id}` | Sửa lịch |
| DELETE | `/api/employee-schedules/{id}` | Xóa lịch |
| GET | `/api/holidays` | Danh sách ngày lễ |
| POST | `/api/holidays` | Tạo ngày lễ |
| POST | `/api/holidays/auto-generate` | Auto-tạo lễ VN |
| GET | `/api/timekeeping-settings` | Lấy cài đặt |
| POST | `/api/timekeeping-settings` | Lưu cài đặt |
| GET | `/api/timekeeping-records` | Bảng chấm công |
| POST | `/api/timekeeping-records` | Chấm công thủ công |
| PUT | `/api/timekeeping-records/{id}` | Sửa bản ghi CC |
| POST | `/api/timekeeping-records/recalculate` | Tính lại CC cho khoảng ngày |
| GET | `/api/attendance-logs` | Log thô từ máy CC |
