# PHƯƠNG ÁN CHUẨN KIOTVIET CHO NGHIỆP VỤ TRẢ HÀNG NHẬP

## 1. Mục tiêu

Tài liệu này dùng để làm chuẩn triển khai hoặc chỉnh sửa lại nghiệp vụ **Trả hàng nhập** trong hệ thống sao cho hành vi gần với KiotViet nhất ở các mặt:

- luồng thao tác
- trạng thái chứng từ
- tác động tồn kho
- tác động công nợ nhà cung cấp
- thông tin được phép sửa
- quy tắc hủy phiếu
- copy / in / export / tìm kiếm
- khả năng trả hàng theo phiếu nhập hoặc không theo phiếu nhập

Mục tiêu không phải là làm giống giao diện KiotViet, mà là:
**logic nghiệp vụ phải chạy tương đương và đối chiếu được bằng dữ liệu.**

---

## 2. Tổng quan nghiệp vụ chuẩn

KiotViet đang hỗ trợ **2 cách tạo phiếu trả hàng nhập**:

1. **Trả hàng nhập nhanh (không theo phiếu nhập)**
2. **Trả hàng nhập theo phiếu nhập hàng**

Đây là điểm bắt buộc của nghiệp vụ chuẩn.

Nếu hệ thống hiện tại chỉ có một cách trả hàng, hoặc chỉ cho trả từ phiếu nhập mà không có trả nhanh, thì đang thiếu so với chuẩn này.

---

## 3. Luồng chuẩn số 1: Trả hàng nhập nhanh

## 3.1 Mục đích
Dùng khi cần trả hàng cho nhà cung cấp nhưng không muốn hoặc không thể tham chiếu đến một phiếu nhập cụ thể.

## 3.2 Trình tự chuẩn
1. Vào màn hình Trả hàng nhập
2. Nhấn **+ Trả hàng nhập**
3. Thêm hàng hóa vào phiếu bằng:
   - tìm kiếm
   - quét mã vạch
   - import Excel
4. Chọn nhà cung cấp
5. Nhập thông tin tiền nhà cung cấp trả
6. Hoàn thành phiếu

## 3.3 Kết quả chuẩn khi hoàn thành
- hệ thống **trừ tồn kho**
- hệ thống **ghi nhận/cập nhật công nợ nhà cung cấp**
- nếu NCC trả tiền ít hơn số phải trả thì phần chênh lệch **đưa vào công nợ**

---

## 4. Luồng chuẩn số 2: Trả hàng nhập theo phiếu nhập hàng

## 4.1 Mục đích
Dùng khi muốn trả hàng bám đúng một lần nhập hàng cụ thể để đảm bảo:
- đúng hàng
- đúng giá nhập
- đúng NCC
- đúng số lượng được phép trả

## 4.2 Trình tự chuẩn
1. Vào màn hình **Nhập hàng**
2. Mở phiếu nhập gốc
3. Chọn **Trả hàng nhập**
4. Hệ thống mở màn hình trả hàng nhập với:
   - hàng hóa được điền sẵn
   - nhà cung cấp được điền sẵn
5. Người dùng nhập số lượng thực tế cần trả
6. Nhập số tiền NCC trả
7. Hoàn thành phiếu

## 4.3 Quy tắc nghiệp vụ bắt buộc
- số lượng trả của từng dòng **không được vượt quá số lượng đã nhập**
- nếu một mặt hàng trong phiếu nhập có **nhiều mức giá nhập khác nhau** thì khi trả hàng theo phiếu nhập, hệ thống phải hỗ trợ trả theo **nhiều giá trả lại khác nhau**
- khi hoàn thành phiếu:
  - **trừ tồn kho**
  - **cập nhật công nợ NCC**

---

## 5. Các cách thêm hàng vào phiếu trả hàng nhập

Hệ thống chuẩn nên hỗ trợ tối thiểu các cách sau:

### 5.1 Tìm kiếm / quét mã
- nhập mã hàng hoặc tên hàng
- quét mã vạch nếu có

### 5.2 Chế độ nhập thường
- chọn hàng
- con trỏ chuyển sang ô số lượng
- nhập số lượng rồi Enter để thêm

### 5.3 Chế độ nhập nhanh
- mỗi lần quét hoặc Enter thì tăng thêm 1 đơn vị

### 5.4 Import từ Excel
KiotViet hỗ trợ import file Excel mẫu với các cột như:
- Mã hàng
- Tên sản phẩm
- Giá trả lại
- Số lượng trả lại
- Serial/IMEI (nếu có)

### Yêu cầu hệ thống của mình
Nên hỗ trợ đủ cả 4 cách trên, hoặc ít nhất:
- search / barcode
- import Excel
- nhập thường / nhập nhanh

---

## 6. Thông tin cần có trên phiếu trả hàng nhập

Một phiếu trả hàng nhập chuẩn nên có tối thiểu các trường sau:

## 6.1 Thông tin đầu phiếu
- Mã phiếu trả hàng nhập
- Ngày giờ tạo phiếu
- Nhà cung cấp
- Người tạo / Người trả
- Chi nhánh
- Ghi chú
- Trạng thái phiếu

## 6.2 Thông tin dòng hàng
- Mã hàng
- Tên hàng
- Đơn vị tính
- Số lượng trả
- Giá trả lại
- Thành tiền
- Serial/IMEI / lô / hạn dùng nếu có
- Tham chiếu dòng nhập gốc nếu trả theo phiếu nhập

## 6.3 Thông tin thanh toán / công nợ
- Tổng tiền hàng
- Nhà cung cấp cần trả
- Tiền nhà cung cấp trả thực tế
- Tính vào công nợ
- Phương thức thanh toán
- Mã chứng từ thanh toán liên quan nếu có

---

## 7. Công thức nghiệp vụ chuẩn

## 7.1 Tổng tiền hàng
```text
Tổng tiền hàng = tổng( số lượng trả × giá trả lại ) của tất cả dòng hàng hợp lệ
```

## 7.2 Nhà cung cấp cần trả
Thông thường bằng tổng giá trị hàng trả lại sau các điều chỉnh liên quan.

```text
NCC cần trả = Tổng tiền hàng
```

Nếu sau này hệ thống có thêm:
- thuế
- giảm giá
- phụ phí
thì cần khóa lại công thức riêng, nhưng bản chuẩn cơ bản hiện tại có thể bắt đầu từ:

```text
NCC cần trả = Tổng tiền hàng thuần của phiếu trả
```

## 7.3 Tính vào công nợ
```text
Tính vào công nợ = NCC cần trả - Tiền NCC trả thực tế
```

### Ý nghĩa
- nếu NCC trả đủ:
  - công nợ phát sinh thêm = 0
- nếu NCC trả một phần:
  - phần còn lại ghi nhận vào công nợ phải thu từ NCC / giảm nợ phải trả cho NCC tùy mô hình hạch toán của hệ thống

---

## 8. Tác động dữ liệu khi hoàn thành phiếu

Khi nhấn **Hoàn thành / Trả hàng nhập**, hệ thống chuẩn phải làm đủ 3 việc:

## 8.1 Tồn kho
- **trừ tồn kho** theo hàng, kho, chi nhánh, serial/lô tương ứng

### Gợi ý bút toán kho
```text
stock_movement.type = purchase_return
qty_out = số lượng trả
warehouse_id = kho chứa hàng bị trả
reference_type = purchase_return
reference_id = id phiếu trả hàng nhập
```

## 8.2 Công nợ nhà cung cấp
Phải ghi nhận thay đổi công nợ dựa trên:
- tổng số NCC phải trả lại
- số NCC trả thực tế
- phần chênh lệch vào công nợ

## 8.3 Thanh toán / quỹ
Nếu có nhận tiền từ NCC ngay tại thời điểm trả hàng:
- sinh giao dịch thu tương ứng
- gắn với phiếu trả hàng nhập
- lưu phương thức thanh toán

---

## 9. Trạng thái phiếu nên có

Để bám sát hành vi KiotViet và dễ quản lý hơn, nên có tối thiểu:

- `draft` / `temp` — Phiếu tạm
- `completed` — Đã trả hàng
- `canceled` — Đã hủy

## 9.1 Phiếu tạm
- chưa tác động tồn kho
- chưa tác động công nợ
- được phép sửa toàn bộ

## 9.2 Đã trả hàng
- đã trừ tồn
- đã cập nhật công nợ
- chỉ cho sửa thông tin hành chính giới hạn

## 9.3 Đã hủy
- phiếu bị vô hiệu
- tồn kho và công nợ phải được đảo ngược về trạng thái trước khi hoàn thành

---

## 10. Quy tắc cập nhật phiếu đã hoàn thành

Theo hành vi chuẩn KiotViet:

Với phiếu trả hàng nhập ở trạng thái **Đã trả**:
- **không được sửa các thông tin liên quan đến hàng hóa**
- chỉ được đổi một số thông tin như:
  - Người tạo phiếu trả hàng nhập
  - thời gian tạo
  - ghi chú

### Kết luận triển khai
Sau khi phiếu đã hoàn thành:
- khóa sửa danh sách dòng hàng
- khóa sửa số lượng
- khóa sửa giá trả lại
- khóa sửa NCC
- khóa sửa kho
- chỉ cho sửa metadata hành chính

---

## 11. Quy tắc hủy phiếu

KiotViet cho phép hủy phiếu trả hàng nhập đã hoàn thành.

### Khi hủy, hệ thống phải:
- **cộng lại tồn kho**
- **cập nhật lại công nợ**

### Triển khai chuẩn
Khi cancel:
1. tạo bút toán đảo kho
2. đảo công nợ
3. đảo hoặc vô hiệu chứng từ thu tiền liên quan nếu có
4. đổi trạng thái phiếu sang `canceled`
5. giữ nguyên lịch sử, không xóa cứng

### Không được làm
- xóa cứng phiếu
- chỉ đổi status nhưng không hoàn tồn
- hoàn tồn nhưng quên đảo công nợ
- hủy phiếu khi đã có liên kết phụ mà không xử lý hậu quả

---

## 12. Quy tắc sao chép phiếu

KiotViet hỗ trợ **Sao chép** phiếu trả hàng nhập.

### Hành vi chuẩn
- tạo phiếu mới
- trạng thái phiếu mới nên là `draft`
- copy thông tin từ phiếu cũ:
  - NCC
  - dòng hàng
  - giá
  - ghi chú (nếu muốn)
- không copy chứng từ thanh toán đã phát sinh
- không copy trạng thái hoàn thành
- không copy lịch sử hủy

---

## 13. In / Xuất file / Danh sách

Hệ thống chuẩn nên có đủ:

## 13.1 Xem danh sách phiếu
List phải hiển thị được:
- mã phiếu
- thời gian
- nhà cung cấp
- tổng tiền
- trạng thái
- người tạo
- chi nhánh

## 13.2 Tìm kiếm / lọc
KiotViet đang cho tìm theo:
- mã phiếu trả
- mã phiếu nhập gốc
- tên hoặc mã hàng hóa
- tên hoặc mã nhà cung cấp
- ghi chú
- người tạo
- trạng thái
- chi nhánh
- thời gian

### Kết luận cho hệ thống của mình
List trả hàng nhập phải có tối thiểu các filter:
- keyword
- date_from/date_to
- supplier_id
- status
- branch_id
- created_by

## 13.3 Sắp xếp
KiotViet cho sort theo tiêu đề cột như:
- mã phiếu
- thời gian
- nhà cung cấp
- tổng cộng

## 13.4 Xuất file
Nên hỗ trợ:
- xuất file chi tiết 1 phiếu
- xuất file hàng loạt
- file tổng quan
- file chi tiết

### Quy tắc export cần bám
- nếu user chọn phiếu thì export đúng phiếu đã chọn
- nếu không chọn phiếu thì export theo toàn bộ tập dữ liệu đang lọc trên màn hình

---

## 14. Quy tắc dữ liệu khi trả theo phiếu nhập

Đây là đoạn rất quan trọng nếu muốn “đúng logic KiotViet”.

Khi tạo trả hàng từ phiếu nhập:
- phải lưu được tham chiếu về phiếu nhập gốc
- nên lưu được tham chiếu về dòng nhập gốc
- số lượng trả tối đa không vượt lượng còn có thể trả của dòng nhập

## 14.1 Công thức số lượng còn có thể trả
Khuyến nghị:

```text
returnable_qty = purchased_qty - returned_qty_non_canceled
```

Trong đó:
- `purchased_qty` = số đã nhập của dòng gốc
- `returned_qty_non_canceled` = tổng số đã trả từ các phiếu trả hàng nhập chưa hủy

### Rule bắt buộc
```text
new_return_qty <= returnable_qty
```

Nếu vượt:
- chặn lưu
- báo lỗi rõ ràng

---

## 15. Quy tắc giá trả lại

KiotViet public thể hiện:
- trả theo phiếu nhập thì hỗ trợ giá trả lại gắn với giá nhập
- nếu hàng có nhiều mức giá nhập khác nhau thì hỗ trợ nhiều giá trả lại khác nhau

### Kết luận triển khai
Hệ thống nên xử lý theo 2 mode:

## 15.1 Trả nhanh
- cho phép nhập giá trả lại thủ công
- nhưng phải validate không âm và không vô lý theo rule nội bộ

## 15.2 Trả theo phiếu nhập
- mặc định lấy giá từ dòng nhập
- nếu một hàng có nhiều dòng nhập / nhiều giá nhập thì phải trả theo từng dòng giá
- tuyệt đối không gộp cứng về một giá bình quân nếu muốn bám KiotViet

---

## 16. Quy tắc serial / IMEI / lô / hạn sử dụng

Nếu sản phẩm đang quản lý:
- Serial/IMEI
- Lô / hạn sử dụng

thì phiếu trả hàng nhập phải xử lý theo cùng đơn vị quản lý đó.

## 16.1 Với serial / IMEI
- phải chọn đúng serial đã nhập trước đó
- không cho trả serial không tồn tại trong kho

## 16.2 Với lô / hạn dùng
- phải chỉ ra lô nào bị trả
- trừ đúng số lượng ở đúng lô

---

## 17. Phân quyền đề xuất

Tối thiểu cần có các quyền:

- Xem danh sách trả hàng nhập
- Tạo phiếu trả hàng nhập
- Hoàn thành phiếu trả hàng nhập
- Sửa thông tin phiếu đã hoàn thành (metadata)
- Hủy phiếu trả hàng nhập
- Sao chép phiếu
- In phiếu
- Xuất file

### Rule quan trọng
User không có quyền hủy thì:
- UI không hiện nút
- API cũng phải chặn

---

## 18. Nhật ký thao tác / audit log

Mọi thao tác sau phải có log:

- tạo phiếu
- lưu tạm
- hoàn thành
- sửa metadata
- hủy phiếu
- sao chép phiếu
- in / export nếu hệ thống muốn theo dõi

Log nên ghi:
- ai thao tác
- thời gian
- hành động
- mã phiếu
- dữ liệu trước/sau với các field quan trọng

---

## 19. Checklist rà soát hệ thống hiện tại

## 19.1 Luồng tạo phiếu
- có trả nhanh không
- có trả theo phiếu nhập không
- có prefill từ phiếu nhập không
- có validate số lượng tối đa không

## 19.2 Dòng hàng
- có search / barcode không
- có import Excel không
- có serial / lô / hạn dùng không
- có nhiều giá trả lại không

## 19.3 Thanh toán / công nợ
- có trường `Tiền NCC trả` không
- có phần chênh lệch vào công nợ không
- có nhiều phương thức thanh toán không

## 19.4 Trạng thái
- có phiếu tạm không
- có đã trả không
- có đã hủy không

## 19.5 Hậu quả dữ liệu
- hoàn thành có trừ tồn không
- hoàn thành có cập nhật công nợ không
- hủy có hoàn tồn không
- hủy có đảo công nợ không

## 19.6 Quản trị
- có filter danh sách không
- có sort không
- có copy không
- có in / export không
- có phân quyền không
- có audit log không

---

## 20. Kế hoạch chỉnh sửa theo thứ tự

## Giai đoạn 1 — Khóa rule nghiệp vụ
- chốt 2 kiểu trả hàng
- chốt state machine
- chốt công thức công nợ
- chốt rule số lượng trả tối đa
- chốt rule giá trả lại

## Giai đoạn 2 — Chỉnh data model
Cần rà các bảng:
- purchase_returns
- purchase_return_items
- stock_movements
- supplier_ledger / payable_ledger
- payment_receipts / cashbook
- purchase_receipt_items_ref nếu trả theo phiếu nhập

## Giai đoạn 3 — Chỉnh service nghiệp vụ
- createPurchaseReturnDraft()
- completePurchaseReturn()
- cancelPurchaseReturn()
- copyPurchaseReturn()
- getReturnableQuantity()

## Giai đoạn 4 — Chỉnh UI
- form tạo phiếu
- form từ phiếu nhập
- list filter
- detail view
- print/export

## Giai đoạn 5 — Viết test
- trả nhanh
- trả theo phiếu nhập
- trả một phần
- trả nhiều giá
- trả có công nợ
- trả đủ tiền
- hủy phiếu
- copy phiếu
- filter list

---

## 21. Kết luận

Nếu muốn làm “đúng như KiotViet”, thì nghiệp vụ trả hàng nhập của hệ thống phải đạt tối thiểu:

1. Có **2 luồng tạo phiếu**: trả nhanh và trả theo phiếu nhập
2. Khi hoàn thành thì **trừ tồn kho + cập nhật công nợ NCC**
3. Có trường **Tiền nhà cung cấp trả**, phần chênh lệch **tính vào công nợ**
4. Với phiếu đã hoàn thành chỉ cho sửa **metadata**, không cho sửa hàng hóa
5. Hủy phiếu phải **cộng lại tồn kho + cập nhật lại công nợ**
6. Danh sách phải có **search/filter/sort/export**
7. Nên hỗ trợ **copy phiếu**, **import Excel**, **serial/lô** nếu hệ thống có quản lý

Đây là bộ chuẩn đầu tiên cần khóa trước khi đi vào code sửa hệ thống.
