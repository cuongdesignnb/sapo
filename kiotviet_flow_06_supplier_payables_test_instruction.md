# KiotViet Flow 06 — Công nợ nhà cung cấp / Thanh toán nhà cung cấp

## Mục tiêu
Luồng này dùng để kiểm tra hệ thống có vận hành đúng **quản lý công nợ phải trả nhà cung cấp sau nhập hàng** theo hành vi tham chiếu của KiotViet hay không.

Agent phải kiểm tra đồng thời 4 lớp:
1. **Luồng nghiệp vụ trên UI/API** có đi đúng trình tự không.
2. **Dữ liệu công nợ / phiếu chi / lịch sử giao dịch** có cập nhật đúng không.
3. **Phân bổ thanh toán** vào các phiếu nhập còn nợ có đúng không.
4. **Các thao tác nâng cao** như chiết khấu thanh toán, điều chỉnh công nợ, quản lý theo chi nhánh có hoạt động đúng không.

Luồng này chỉ tập trung vào **công nợ nhà cung cấp**. Không mở rộng sang kiểm kho, báo cáo tổng hợp, trả hàng bán, hủy hóa đơn bán hoặc các flow ngoài phạm vi hiện tại nếu không thật sự cần cho case này.

---

## Nguồn chuẩn tham chiếu của KiotViet
Agent phải coi các hành vi sau là chuẩn tham chiếu:

1. Trong màn hình **Nhà cung cấp**, có thể xem chi tiết ở tab **Công nợ** hoặc **Nợ cần trả NCC** để theo dõi các phiếu nhập còn nợ.
2. Khi thanh toán công nợ, người dùng có thể nhập tổng tiền vào ô **Số tiền** để hệ thống **tự phân bổ** hoặc nhập trực tiếp vào từng dòng phiếu nhập cần thanh toán.
3. Sau khi xác nhận thanh toán, hệ thống tạo **phiếu chi**.
4. Có thể ghi nhận **chiết khấu thanh toán** để giảm trừ công nợ với nhà cung cấp.
5. Có thể **điều chỉnh công nợ** thủ công, hệ thống tạo phiếu điều chỉnh vào đúng thời gian điều chỉnh.
6. Có thể xem **lịch sử nhập / trả hàng** của nhà cung cấp để đối soát.
7. Nếu bật **quản lý nhà cung cấp theo chi nhánh**, nhà cung cấp chỉ giao dịch được tại chi nhánh đã tạo; giao dịch và báo cáo được lọc theo chi nhánh.
8. Nhà cung cấp bị **ngừng hoạt động** thì không nên phát sinh giao dịch mới; nếu **xóa**, hệ thống vẫn giữ lịch sử giao dịch cũ và tên NCC cũ có hậu tố `{DEL}`.

---

## Nguyên tắc làm việc bắt buộc
1. Chỉ kiểm thử **một flow duy nhất**: Flow 06.
2. Không tự ý mở rộng sang flow khác.
3. Phải đọc source hiện tại trước khi kết luận đúng/sai.
4. Phải xác định rõ các thành phần liên quan trước khi chạy test:
   - model / entity nhà cung cấp
   - bảng công nợ NCC
   - bảng phiếu nhập hàng
   - bảng phiếu chi / thanh toán NCC
   - bảng điều chỉnh công nợ / chiết khấu thanh toán nếu có
   - service / controller / API / UI liên quan
5. Nếu hệ thống chưa có đúng hành vi như KiotViet, agent được phép **bổ sung hoặc sửa tối thiểu** để đạt đúng flow.
6. Mọi sửa đổi phải theo nguyên tắc:
   - sửa ít nhất có thể
   - không phá flow đã pass trước đó
   - không đổi tên hoặc tái cấu trúc lớn nếu chưa bắt buộc
7. Sau mỗi sửa đổi phải **re-test đúng case lỗi** và ghi lại kết quả.
8. Nếu có chỗ không thể sửa an toàn trong phạm vi nhỏ, phải nêu rõ lý do và đề xuất hướng xử lý.

---

## Đầu vào bắt buộc trước khi test
Agent phải kiểm tra và nếu thiếu thì tự tạo dữ liệu test tối thiểu sau:

### Dữ liệu nền
- 1 chi nhánh mặc định: `CN01`
- 1 kho hoạt động: `KHO_TONG`
- 1 nhà cung cấp: `NCC001 - Công ty Minh Phát`
- 2 sản phẩm hàng hóa vật lý:
  - `SP001 - Nước suối 500ml`
  - `SP002 - Bánh quy hộp`

### Dữ liệu giao dịch cần có sẵn
Agent phải tạo hoặc seed các phiếu nhập đã hoàn tất để sinh công nợ NCC:

#### Phiếu nhập A
- Mã tham chiếu: `PN_A`
- NCC: `NCC001`
- Tổng tiền hàng: 1.000.000
- Đã trả NCC: 400.000
- Còn nợ: 600.000
- Trạng thái: hoàn tất / đã nhập hàng

#### Phiếu nhập B
- Mã tham chiếu: `PN_B`
- NCC: `NCC001`
- Tổng tiền hàng: 800.000
- Đã trả NCC: 0
- Còn nợ: 800.000
- Trạng thái: hoàn tất / đã nhập hàng

#### Tổng công nợ mong đợi ban đầu
- Nợ cần trả NCC001 = 1.400.000

Nếu source hiện tại không có cơ chế seed/test data, agent được phép:
- tạo fixture
- tạo migration / seeder test-only
- hoặc tạo dữ liệu bằng API nội bộ

Nhưng phải ghi rõ cách tạo.

---

## Kết quả cuối cùng cần đạt
Sau khi flow pass, hệ thống phải đảm bảo:

1. Xem được công nợ phải trả của `NCC001`.
2. Thanh toán công nợ toàn phần hoặc một phần được ghi nhận đúng.
3. Có thể thanh toán theo 2 cách:
   - nhập tổng số tiền để hệ thống tự phân bổ
   - nhập trực tiếp vào từng phiếu nhập
4. Mỗi lần thanh toán phải sinh **phiếu chi** hoặc bản ghi tương đương.
5. Sau thanh toán, số dư công nợ NCC phải giảm đúng.
6. Ghi nhận **chiết khấu thanh toán** phải giảm công nợ đúng.
7. Ghi nhận **điều chỉnh công nợ** phải tạo chứng từ điều chỉnh đúng.
8. Xem được lịch sử nhập / trả hàng của NCC để đối soát.
9. Nếu hệ thống có cấu hình theo chi nhánh, hành vi phải phù hợp logic chi nhánh.

---

## Checklist đọc source trước khi test
Agent phải tìm và ghi lại tối thiểu:

1. Nơi định nghĩa nhà cung cấp.
2. Nơi định nghĩa phiếu nhập.
3. Nơi lưu công nợ NCC hoặc số dư nợ.
4. Nơi tạo phiếu chi / thanh toán NCC.
5. Logic phân bổ tiền thanh toán vào phiếu nhập.
6. Logic chiết khấu thanh toán.
7. Logic điều chỉnh công nợ.
8. Logic lọc theo chi nhánh (nếu có).
9. API / route / màn hình dùng để:
   - xem công nợ NCC
   - thanh toán NCC
   - điều chỉnh công nợ NCC
   - xem lịch sử giao dịch NCC

Nếu thiếu một trong các phần trên, phải ghi rõ trong báo cáo.

---

## Bộ case test bắt buộc

### Case 06A — Xem chi tiết công nợ nhà cung cấp
**Mục tiêu**: kiểm tra có xem được số dư công nợ và danh sách phiếu nhập còn nợ hay không.

**Bước test**
1. Mở danh sách nhà cung cấp.
2. Chọn `NCC001`.
3. Vào tab công nợ hoặc nợ cần trả.
4. Kiểm tra có hiển thị `PN_A`, `PN_B` và tổng nợ 1.400.000 hay không.

**Pass khi**
- nhìn thấy tổng nợ đúng
- nhìn thấy từng phiếu nhập còn nợ
- số còn nợ từng phiếu khớp dữ liệu chuẩn

**Fail nếu**
- không xem được chi tiết công nợ
- tổng nợ không khớp
- thiếu phiếu nhập còn nợ

---

### Case 06B — Thanh toán một phần bằng tổng số tiền, hệ thống tự phân bổ
**Mục tiêu**: kiểm tra nhập tổng tiền và hệ thống tự phân bổ vào các phiếu nhập còn nợ.

**Dữ liệu**
- Công nợ ban đầu: `PN_A = 600.000`, `PN_B = 800.000`

**Bước test**
1. Tại chi tiết `NCC001`, chọn Thanh toán.
2. Nhập `Số tiền = 500.000`.
3. Chọn phương thức thanh toán hợp lệ.
4. Xác nhận tạo phiếu chi.

**Kỳ vọng chuẩn**
- hệ thống tạo 1 phiếu chi
- hệ thống tự phân bổ 500.000 vào các phiếu còn nợ theo logic hiện hành
- tổng nợ mới = 900.000
- tổng phân bổ = 500.000

**Pass khi**
- có phiếu chi
- công nợ giảm đúng 500.000
- có dấu vết phân bổ rõ ràng vào phiếu nhập

**Nếu hệ thống không hỗ trợ phân bổ tự động**
- agent phải ghi là sai lệch so với KiotViet
- nếu có thể sửa nhỏ, hãy bổ sung logic tự phân bổ

---

### Case 06C — Thanh toán trực tiếp vào từng phiếu nhập
**Mục tiêu**: kiểm tra người dùng có thể nhập tiền trực tiếp cho từng phiếu thay vì nhập tổng số tiền.

**Bước test**
1. Tại chi tiết `NCC001`, chọn Thanh toán.
2. Ở dòng `PN_A`, nhập `200.000`.
3. Ở dòng `PN_B`, nhập `300.000`.
4. Không nhập thêm tổng tiền khác nếu UI tách riêng.
5. Xác nhận tạo phiếu chi.

**Kỳ vọng chuẩn**
- tổng thanh toán = 500.000
- `PN_A` còn nợ 400.000
- `PN_B` còn nợ 500.000
- tổng nợ mới = 900.000
- tạo phiếu chi tương ứng

**Pass khi**
- phân bổ đúng từng phiếu
- không bị phân bổ nhầm sang phiếu khác
- tổng nợ cập nhật chuẩn

---

### Case 06D — Thanh toán hết toàn bộ công nợ
**Mục tiêu**: kiểm tra thanh toán toàn bộ làm về 0 công nợ.

**Bước test**
1. Dùng dữ liệu reset hoặc tạo một NCC có đúng nợ 1.400.000.
2. Chọn Thanh toán.
3. Nhập số tiền = 1.400.000.
4. Xác nhận.

**Pass khi**
- tất cả phiếu nhập còn nợ về 0
- tổng công nợ NCC về 0
- có phiếu chi ghi nhận đúng
- tab công nợ không còn hiển thị phiếu còn nợ hoặc hiển thị 0 đúng logic hệ thống

**Fail nếu**
- còn dư nợ sai
- thanh toán hết nhưng không sinh phiếu chi

---

### Case 06E — Ghi nhận chiết khấu thanh toán
**Mục tiêu**: kiểm tra chiết khấu thanh toán làm giảm công nợ đúng mà không ghi nhận như một khoản trả tiền mặt thông thường.

**Bước test**
1. Tại chi tiết `NCC001`, mở tab công nợ.
2. Chọn Chiết khấu thanh toán.
3. Nhập `100.000`.
4. Chọn có/không phân bổ vào phiếu nhập tùy UI hỗ trợ.
5. Xác nhận tạo phiếu.

**Kỳ vọng chuẩn**
- công nợ giảm 100.000
- sinh chứng từ chiết khấu hoặc bản ghi tương đương
- nếu có phân bổ thì phiếu nhập được giảm nợ đúng
- lịch sử công nợ thể hiện rõ đây là chiết khấu, không phải phiếu chi thường

**Pass khi**
- giảm nợ đúng
- có log/chứng từ rõ ràng

---

### Case 06F — Điều chỉnh công nợ thủ công
**Mục tiêu**: kiểm tra có thể chỉnh lại số nợ chính xác và hệ thống tạo phiếu điều chỉnh theo đúng thời gian điều chỉnh.

**Bước test**
1. Tại `NCC001`, mở tab công nợ.
2. Chọn Điều chỉnh.
3. Nhập `Giá trị nợ điều chỉnh = 1.250.000`.
4. Nhập ngày điều chỉnh và mô tả `Điều chỉnh test Flow 06`.
5. Xác nhận.

**Kỳ vọng chuẩn**
- hệ thống không cộng/trừ mù mà đặt lại số nợ theo giá trị điều chỉnh hoặc tạo delta đúng theo thiết kế
- hệ thống sinh phiếu điều chỉnh / transaction điều chỉnh
- tổng nợ hiển thị đúng theo kết quả cuối
- phiếu điều chỉnh có đúng thời gian điều chỉnh

**Pass khi**
- có chứng từ điều chỉnh
- công nợ sau điều chỉnh đúng
- truy vết được vì sao nợ thay đổi

---

### Case 06G — Xem lịch sử nhập / trả hàng của nhà cung cấp
**Mục tiêu**: kiểm tra có xem được đầy đủ lịch sử giao dịch với NCC để đối soát.

**Bước test**
1. Mở chi tiết `NCC001`.
2. Vào tab lịch sử nhập / trả hàng.
3. Kiểm tra có hiển thị các phiếu nhập và nếu có thì cả phiếu trả hàng nhập liên quan.
4. Thử lọc / sắp xếp / xuất file nếu hệ thống hỗ trợ.

**Pass khi**
- nhìn thấy lịch sử giao dịch liên quan đến NCC
- dữ liệu khớp với các phiếu nhập / trả hàng đã tạo
- không lẫn với NCC khác

---

### Case 06H — Quản lý nhà cung cấp theo chi nhánh (nếu hệ thống hỗ trợ)
**Mục tiêu**: kiểm tra logic chi nhánh tương đương KiotViet.

**Tiền đề**
- chỉ chạy nếu source có khái niệm chi nhánh và quản lý NCC theo chi nhánh

**Bước test**
1. Bật cấu hình quản lý NCC theo chi nhánh nếu có.
2. Tạo `NCC_CN1` tại `CN01`.
3. Chuyển sang `CN02`.
4. Thử dùng `NCC_CN1` để nhập hàng hoặc thanh toán công nợ.

**Pass khi**
- `NCC_CN1` không giao dịch được ở chi nhánh khác hoặc bị lọc đúng theo thiết kế
- giao dịch / báo cáo / công nợ hiển thị theo chi nhánh đang xem

**Nếu hệ thống không hỗ trợ**
- ghi `NA` nếu dự án không có scope chi nhánh
- ghi `Fail` nếu dự án có chi nhánh nhưng hành vi sai

---

### Case 06I — Ngừng hoạt động / xóa nhà cung cấp có công nợ và lịch sử giao dịch
**Mục tiêu**: kiểm tra xử lý vòng đời NCC mà không làm mất lịch sử.

**Bước test**
1. Chọn `NCC001`.
2. Ngừng hoạt động.
3. Thử tạo giao dịch mới với NCC này.
4. Cho phép hoạt động lại.
5. Trên môi trường test, thử xóa NCC.
6. Kiểm tra lịch sử phiếu nhập / thanh toán cũ.

**Pass khi**
- NCC ngừng hoạt động không nên phát sinh giao dịch mới
- cho phép hoạt động lại thì dùng lại được
- xóa không làm mất lịch sử cũ
- giao dịch cũ vẫn truy được và có dấu vết NCC đã xóa

---

## Kiểm tra dữ liệu bắt buộc sau mỗi case
Sau mỗi case, agent phải kiểm tra ít nhất các điểm sau ở DB hoặc API:

1. Tổng công nợ hiện tại của NCC.
2. Số còn nợ của từng phiếu nhập.
3. Danh sách phiếu chi đã sinh.
4. Liên kết giữa phiếu chi và phiếu nhập được thanh toán.
5. Lịch sử giao dịch / audit log nếu có.
6. Không có âm nợ hoặc lệch tổng trừ khi business rule cho phép.

Công thức đối soát tối thiểu:

`Nợ NCC hiện tại = Tổng nợ phát sinh từ phiếu nhập + điều chỉnh tăng - thanh toán - chiết khấu - trả hàng nhập - điều chỉnh giảm`

Nếu hệ thống dùng mô hình ledger/sổ cái, agent phải đối soát theo ledger thay vì chỉ nhìn số dư tổng.

---

## Hướng dẫn sửa lỗi nếu fail
Nếu fail, agent phải làm theo thứ tự:

1. Xác định lỗi nằm ở đâu:
   - UI form
   - API validate
   - service nghiệp vụ
   - transaction DB
   - logic phân bổ công nợ
   - logic tính số dư NCC
2. Viết rõ nguyên nhân gốc.
3. Sửa tối thiểu.
4. Không sửa lan sang flow khác nếu chưa có bằng chứng.
5. Re-test lại đúng case vừa fail.
6. Nếu sửa có nguy cơ ảnh hưởng Flow 02 hoặc các flow trước, phải chạy smoke test tối thiểu:
   - tạo phiếu nhập có công nợ
   - xem lại công nợ NCC

---

## Mẫu báo cáo bắt buộc
Agent phải xuất báo cáo theo đúng cấu trúc sau:

### 1. Tóm tắt phạm vi
- Đã test Flow 06 nào
- Đã dùng dữ liệu nào
- Đã chạy trên môi trường nào

### 2. Mapping source
- file / module / service / API liên quan đến NCC payables

### 3. Kết quả từng case
- 06A: Pass / Fail / NA
- 06B: Pass / Fail / NA
- 06C: Pass / Fail / NA
- 06D: Pass / Fail / NA
- 06E: Pass / Fail / NA
- 06F: Pass / Fail / NA
- 06G: Pass / Fail / NA
- 06H: Pass / Fail / NA
- 06I: Pass / Fail / NA

### 4. Danh sách sai lệch so với KiotViet
Mỗi sai lệch phải có:
- mô tả
- bước tái hiện
- expected
- actual
- mức độ ảnh hưởng
- nguyên nhân gốc

### 5. Danh sách file đã sửa
- file nào
- sửa gì
- vì sao sửa

### 6. Kết quả re-test
- case nào pass sau sửa
- case nào còn fail
- case nào cần xử lý sau

### 7. Kết luận cuối
- Flow 06 đã đạt mức production-ready chưa
- còn chặn bởi lỗi nào

---

## Tiêu chí hoàn thành flow
Flow 06 chỉ được coi là hoàn thành khi:

1. Các case lõi 06A, 06B, 06C, 06D phải pass.
2. Ít nhất một trong 06E hoặc 06F phải pass nếu dự án có scope quản lý công nợ nâng cao.
3. Lịch sử giao dịch NCC phải tra cứu được.
4. Không có sai lệch số dư công nợ sau thanh toán.
5. Không tạo phiếu chi sai, trùng hoặc mất liên kết phân bổ.

Nếu chưa đạt, agent phải dừng ở trạng thái **chưa hoàn tất Flow 06** và nêu rõ phần còn thiếu.
