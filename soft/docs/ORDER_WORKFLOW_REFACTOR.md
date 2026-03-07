# QUY TRÌNH ĐƠN HÀNG MỚI - 5 BƯỚC ĐƠN GIẢN HÓA

## Tổng quan

Quy trình mới được thiết kế để đơn giản hóa việc quản lý đơn hàng từ 8 trạng thái phức tạp xuống còn 5 bước cơ bản, dễ hiểu và dễ sử dụng.

## So sánh Quy trình Cũ vs Mới

### Quy trình Cũ (8 trạng thái)

```
pending → confirmed → processing → shipping → delivered → completed
                   ↘ cancelled ↗            ↘ refunded
```

### Quy trình Mới (5 bước)

```
ordered → approved → shipping_created → delivered → completed
     ↘         ↘              ↘             ↗
        cancelled (có thể hủy đến bước 3)
```

## Chi tiết 5 Bước

### BƯỚC 1: TẠO ĐƠN HÀNG (ordered)

**Mục đích:** Tạo đơn hàng với thông tin cơ bản
**Trạng thái:** `ordered`
**Màu sắc:** Vàng
**API:** `POST /api/orders`

**Dữ liệu cần thiết:**

-   Thông tin khách hàng
-   Danh sách sản phẩm
-   Thông tin giao hàng (tùy chọn)
-   KHÔNG cần thông tin thanh toán và vận chuyển

**Thay đổi:**

-   Đơn giản hóa form tạo đơn hàng
-   Không cần nhập shipping ngay từ đầu
-   Mặc định `paid = 0`, `debt = total`
-   Tạo customer debt tự động

### BƯỚC 2: DUYỆT ĐƠN HÀNG (approved)

**Mục đích:** Xác nhận đơn hàng và kiểm tra tồn kho
**Trạng thái:** `approved`
**Màu sắc:** Xanh dương
**API:** `POST /api/orders/{id}/approve`

**Chức năng:**

-   Kiểm tra tồn kho lần cuối
-   Xác nhận thông tin đơn hàng
-   Cho phép thêm ghi chú

**Giao diện:**

-   Nút "Duyệt đơn hàng" ở góc phải màn hình
-   Modal xác nhận với option ghi chú

### BƯỚC 3: TẠO ĐƠN VẬN CHUYỂN (shipping_created)

**Mục đích:** Quyết định phương thức giao hàng
**Trạng thái:** `shipping_created` (hoặc `delivered` nếu chọn pickup)
**Màu sắc:** Tím
**API:** `POST /api/orders/{id}/create-shipping`

**3 Tùy chọn:**

#### A. Gửi cho bên giao hàng (third_party)

-   Nhập đơn vị vận chuyển (Grab, GiaoHangNhanh, v.v.)
-   Nhập giá cước (không bắt buộc)
-   Lưu thông tin để tham khảo

#### B. Tự giao hàng (self_delivery)

-   Tùy chọn nhập thông tin người nhận
-   Nếu không nhập sẽ dùng thông tin khách hàng
-   Phù hợp cho cửa hàng tự giao

#### C. Nhận tại cửa hàng (pickup)

-   Chuyển thẳng sang BƯỚC 4 (delivered)
-   Bỏ qua việc tạo shipping record
-   Phù hợp cho khách đến lấy hàng

**Giao diện:**

-   Nút "Tạo đơn vận chuyển" với dropdown 3 option
-   Modal chọn phương thức với form tương ứng

### BƯỚC 4: XUẤT KHO (delivered)

**Mục đích:** Trừ tồn kho và xác nhận giao hàng
**Trạng thái:** `delivered`
**Màu sắc:** Xanh lá
**API:** `POST /api/orders/{id}/export-stock`

**Chức năng:**

-   Trừ số lượng sản phẩm khỏi kho
-   Đánh dấu đơn hàng đã giao
-   Cập nhật shipping status thành "delivered"

**Giao diện:**

-   Nút "Xuất kho" ở góc phải
-   Hiển thị thêm nút "Đổi trả hàng" sau khi xuất kho

### BƯỚC 5: THANH TOÁN (completed)

**Mục đích:** Hoàn tất thanh toán và đóng đơn hàng
**Trạng thái:** `completed` (khi thanh toán đủ)
**Màu sắc:** Xanh lá
**API:** `POST /api/orders/{id}/complete-payment`

**Chức năng:**

-   Nhập thông tin thanh toán
-   Cập nhật debt và paid
-   Tạo OrderPayment record
-   Cập nhật CustomerDebt

**Giao diện:**

-   Nút "Thanh toán" với modal nhập thông tin
-   Hiển thị số tiền còn lại
-   Cho phép thanh toán từng phần

## Cải tiến Giao diện

### 1. Thanh tiến trình (Progress Bar)

```
[✓] Đặt hàng → [✓] Duyệt → [●] Vận chuyển → [ ] Xuất kho → [ ] Hoàn thành
```

### 2. Nút hành động động (Dynamic Action Button)

-   Góc phải màn hình
-   Thay đổi theo trạng thái hiện tại
-   Màu sắc phù hợp với từng bước

### 3. Tìm kiếm sản phẩm cải tiến

-   Tìm theo tên, mã, barcode
-   Autocomplete với highlighting
-   Hiển thị tồn kho realtime

### 4. Định dạng số tiền

-   Thêm dấu phân cách hàng nghìn
-   Format theo chuẩn Việt Nam
-   Highlight số âm/dương

## API Endpoints Mới

```php
// Lấy hành động tiếp theo
GET /api/orders/{id}/next-action

// Quy trình 5 bước
POST /api/orders/{id}/approve
POST /api/orders/{id}/create-shipping
POST /api/orders/{id}/export-stock
POST /api/orders/{id}/complete-payment
POST /api/orders/{id}/cancel
```

## Tương thích ngược

Hệ thống mới vẫn hỗ trợ các trạng thái cũ để đảm bảo không bị lỗi với dữ liệu hiện có:

```php
// Mapping trạng thái cũ → mới
'pending' → 'ordered'
'confirmed' → 'approved'
'processing' → 'shipping_created'
// 'shipping', 'delivered', 'completed' giữ nguyên
```

## Cách triển khai

### 1. Database Migration

```bash
php artisan migrate
```

### 2. Update Frontend Components

-   Thay thế OrderDetail.vue bằng OrderWorkflow.vue
-   Cập nhật OrderList.vue để hiển thị trạng thái mới
-   Thêm ShippingMethodModal.vue

### 3. Testing

-   Test quy trình từ đầu đến cuối
-   Kiểm tra tương thích ngược
-   Validate business logic

## Lợi ích

### 1. Đơn giản hóa

-   Giảm từ 8 xuống 5 trạng thái chính
-   Quy trình tuyến tính, dễ hiểu
-   Ít bước nhảy trạng thái phức tạp

### 2. Trải nghiệm người dùng

-   Giao diện trực quan với thanh tiến trình
-   Nút hành động rõ ràng
-   Modal contextual cho từng bước

### 3. Tính linh hoạt

-   3 tùy chọn vận chuyển
-   Thanh toán linh hoạt
-   Có thể hủy đến bước 3

### 4. Maintainability

-   Code sạch hơn với method riêng biệt
-   Dễ debug và modify
-   API RESTful chuẩn

## Migration Plan

### Phase 1: Backend Implementation ✅

-   Tạo constants và methods mới trong OrderController
-   Update Order model với trạng thái mới
-   Tạo API routes
-   Database migration

### Phase 2: Frontend Implementation

-   Tạo OrderWorkflow.vue component
-   Tạo shipping method modal
-   Update orderApi.js
-   Integrate với routing

### Phase 3: Testing & Rollout

-   Unit tests cho các method mới
-   Integration testing
-   User acceptance testing
-   Production deployment

---

**Lưu ý:** Quy trình mới được thiết kế dựa trên phân tích giao diện Sapo - đơn giản, trực quan nhưng đầy đủ chức năng.
