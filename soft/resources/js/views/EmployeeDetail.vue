<template>
    <div class="bg-gray-50 min-h-screen">
        <div class="bg-white border-b">
            <div class="p-6 flex items-center justify-between">
                <div>
                    <a
                        href="/employees"
                        class="text-sm text-gray-500 hover:text-gray-700"
                        >← Quay lại danh sách</a
                    >
                    <h1 class="text-2xl font-semibold text-gray-900 mt-1">
                        {{ employee?.name || "Chi tiết nhân viên" }}
                        <span
                            v-if="employee?.code"
                            class="text-gray-500 font-normal text-base"
                            >({{ employee.code }})</span
                        >
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        class="px-4 py-2 rounded border bg-white hover:bg-gray-50"
                        @click="openEdit"
                        :disabled="!employee"
                    >
                        Cập nhật
                    </button>
                </div>
            </div>

            <div class="px-6">
                <div class="flex items-center gap-6 border-b">
                    <button
                        class="px-1 pb-3 text-sm font-medium"
                        :class="activeTab === 'info' ? tabActive : tabInactive"
                        @click="activeTab = 'info'"
                    >
                        Thông tin
                    </button>
                    <button
                        class="px-1 pb-3 text-sm font-medium"
                        :class="
                            activeTab === 'schedule' ? tabActive : tabInactive
                        "
                        @click="activeTab = 'schedule'"
                    >
                        Lịch làm việc
                    </button>
                    <button
                        class="px-1 pb-3 text-sm font-medium"
                        :class="
                            activeTab === 'salary' ? tabActive : tabInactive
                        "
                        @click="activeTab = 'salary'"
                    >
                        Thiết lập lương
                    </button>
                    <button
                        class="px-1 pb-3 text-sm font-medium"
                        :class="
                            activeTab === 'payroll' ? tabActive : tabInactive
                        "
                        @click="activeTab = 'payroll'"
                    >
                        Phiếu lương
                    </button>
                    <button
                        class="px-1 pb-3 text-sm font-medium"
                        :class="activeTab === 'debt' ? tabActive : tabInactive"
                        @click="activeTab = 'debt'"
                    >
                        Nợ & tạm ứng
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div v-if="loadingEmployee" class="bg-white border rounded-lg p-6">
                Đang tải...
            </div>

            <div v-else>
                <!-- INFO -->
                <div
                    v-if="activeTab === 'info'"
                    class="grid grid-cols-12 gap-4"
                >
                    <div class="col-span-12 lg:col-span-4">
                        <div class="bg-white border rounded-lg p-5">
                            <div class="flex items-center gap-4">
                                <div
                                    class="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden"
                                >
                                    <img
                                        v-if="employee?.avatar_path"
                                        :src="employee.avatar_path"
                                        class="h-16 w-16 object-cover"
                                    />
                                    <span v-else class="text-gray-600 text-sm"
                                        >Ảnh</span
                                    >
                                </div>
                                <div>
                                    <div
                                        class="text-base font-semibold text-gray-900"
                                    >
                                        {{ employee?.name || "-" }}
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        {{ employee?.phone || "-" }}
                                    </div>
                                    <div class="mt-1">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs"
                                            :class="
                                                employee?.status === 'active'
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-700'
                                            "
                                        >
                                            {{
                                                employee?.status === "active"
                                                    ? "Đang làm"
                                                    : "Đã nghỉ"
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 lg:col-span-8">
                        <div class="bg-white border rounded-lg p-5">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Mã nhân viên
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.code || "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Mã chấm công
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.attendance_code || "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Số CMND/CCCD
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.id_number || "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Email
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.email || "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Chi nhánh trả lương
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.warehouse?.name || "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Chi nhánh làm việc
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{
                                            (employee?.work_warehouses || [])
                                                .map((w) => w.name)
                                                .join(", ") || "-"
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Phòng ban
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.department || "-" }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">
                                        Chức danh
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.title || "-" }}
                                    </div>
                                </div>
                                <div class="col-span-2">
                                    <div class="text-xs text-gray-500">
                                        Ghi chú
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ employee?.notes || "-" }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SCHEDULE -->
                <div v-else-if="activeTab === 'schedule'" class="space-y-4">
                    <div class="bg-white border rounded-lg p-5">
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <div class="text-xs text-gray-600 mb-1">
                                    Từ ngày
                                </div>
                                <input
                                    v-model="scheduleFilter.from"
                                    type="date"
                                    class="px-3 py-2 border rounded-md"
                                />
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 mb-1">
                                    Đến ngày
                                </div>
                                <input
                                    v-model="scheduleFilter.to"
                                    type="date"
                                    class="px-3 py-2 border rounded-md"
                                />
                            </div>
                            <button
                                class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                                @click="loadSchedules"
                            >
                                Tải lịch
                            </button>
                        </div>
                    </div>

                    <div class="bg-white border rounded-lg p-5">
                        <div class="text-sm font-semibold text-gray-900 mb-3">
                            Thêm ca làm
                        </div>
                        <div class="grid grid-cols-12 gap-3 items-end">
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">
                                    Ngày
                                </div>
                                <input
                                    v-model="newSchedule.work_date"
                                    type="date"
                                    class="w-full px-3 py-2 border rounded-md"
                                />
                            </div>
                            <div class="col-span-12 md:col-span-2">
                                <div class="text-xs text-gray-600 mb-1">
                                    Slot
                                </div>
                                <input
                                    v-model.number="newSchedule.slot"
                                    type="number"
                                    min="1"
                                    max="20"
                                    class="w-full px-3 py-2 border rounded-md"
                                />
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">
                                    Chi nhánh
                                </div>
                                <select
                                    v-model="newSchedule.warehouse_id"
                                    class="w-full px-3 py-2 border rounded-md"
                                >
                                    <option :value="null">-</option>
                                    <option
                                        v-for="w in warehouses"
                                        :key="w.id"
                                        :value="w.id"
                                    >
                                        {{ w.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">Ca</div>
                                <select
                                    v-model="newSchedule.shift_id"
                                    class="w-full px-3 py-2 border rounded-md"
                                >
                                    <option :value="null">-</option>
                                    <option
                                        v-for="s in shifts"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }} ({{ s.start_time }}-{{
                                            s.end_time
                                        }})
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-1">
                                <button
                                    class="w-full px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700"
                                    @click="addSchedule"
                                    :disabled="savingSchedule"
                                >
                                    Lưu
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Ngày
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Slot
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Chi nhánh
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Ca
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Trạng thái
                                    </th>
                                    <th
                                        class="text-right p-3 text-sm font-medium text-gray-600"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="s in schedules"
                                    :key="s.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-3 text-sm text-gray-900">
                                        {{ s.work_date }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{ s.slot }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{ s.warehouse?.name || "-" }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{
                                            s.shift?.name || s.shift_name || "-"
                                        }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{ s.status || "-" }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <button
                                            class="text-red-600 hover:text-red-800 text-sm"
                                            @click="deleteSchedule(s)"
                                        >
                                            Xóa
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="schedules.length === 0">
                                    <td
                                        colspan="6"
                                        class="p-6 text-center text-gray-500"
                                    >
                                        Chưa có lịch trong khoảng này
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SALARY -->
                <div v-else-if="activeTab === 'salary'" class="space-y-4">
                    <div class="bg-white border rounded-lg p-5">
                        <div class="text-sm font-semibold text-gray-900 mb-3">
                            Cấu hình lương
                        </div>

                        <div
                            v-if="salaryConfig"
                            class="text-sm text-gray-700 mb-3"
                        >
                            Đang áp dụng:
                            <span class="font-medium">{{
                                salaryConfig.template?.name
                            }}</span>
                            <span class="text-gray-500"
                                >(Trả lương:
                                {{
                                    salaryConfig.pay_warehouse?.name ||
                                    employee?.warehouse?.name ||
                                    "-"
                                }})</span
                            >
                        </div>
                        <div v-else class="text-sm text-gray-500 mb-3">
                            Chưa có cấu hình lương cho nhân viên này.
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Mẫu lương</label
                                >
                                <select
                                    v-model="salaryForm.salary_template_id"
                                    class="w-full px-3 py-2 border rounded-md"
                                >
                                    <option :value="null">-</option>
                                    <option
                                        v-for="t in salaryTemplates"
                                        :key="t.id"
                                        :value="t.id"
                                    >
                                        {{ t.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Chi nhánh trả lương</label
                                >
                                <select
                                    v-model="salaryForm.pay_warehouse_id"
                                    class="w-full px-3 py-2 border rounded-md"
                                >
                                    <option :value="null">
                                        (Theo nhân viên)
                                    </option>
                                    <option
                                        v-for="w in warehouses"
                                        :key="w.id"
                                        :value="w.id"
                                    >
                                        {{ w.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="col-span-2">
                                <label
                                    class="inline-flex items-center gap-2 text-sm cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        v-model="salaryForm.enable_commission"
                                    />
                                    <span>Áp dụng hoa hồng</span>
                                </label>
                            </div>

                            <div v-if="salaryForm.enable_commission">
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Tỷ lệ hoa hồng (%)</label
                                >
                                <input
                                    v-model.number="salaryForm.commission_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 border rounded-md"
                                />
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button
                                class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700"
                                @click="saveSalaryConfig"
                                :disabled="savingSalary"
                            >
                                Lưu thiết lập
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PAYROLL -->
                <div v-else-if="activeTab === 'payroll'" class="space-y-4">
                    <div class="bg-white border rounded-lg p-5">
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <div class="text-xs text-gray-600 mb-1">
                                    Từ kỳ
                                </div>
                                <input
                                    v-model="payrollFilter.from"
                                    type="date"
                                    class="px-3 py-2 border rounded-md"
                                />
                            </div>
                            <div>
                                <div class="text-xs text-gray-600 mb-1">
                                    Đến kỳ
                                </div>
                                <input
                                    v-model="payrollFilter.to"
                                    type="date"
                                    class="px-3 py-2 border rounded-md"
                                />
                            </div>
                            <button
                                class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                                @click="loadPayrollItems"
                            >
                                Tải phiếu lương
                            </button>
                        </div>
                    </div>

                    <div class="bg-white border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Kỳ lương
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Trạng thái
                                    </th>
                                    <th
                                        class="text-right p-3 text-sm font-medium text-gray-600"
                                    >
                                        Lương thực nhận
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="it in payrollItems"
                                    :key="it.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-3 text-sm text-gray-900">
                                        {{ it.sheet?.start_date }} →
                                        {{ it.sheet?.end_date }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{ it.sheet?.status || "-" }}
                                    </td>
                                    <td
                                        class="p-3 text-sm text-gray-900 text-right font-medium"
                                    >
                                        {{ formatMoney(it.net_salary) }}
                                    </td>
                                </tr>
                                <tr v-if="payrollItems.length === 0">
                                    <td
                                        colspan="3"
                                        class="p-6 text-center text-gray-500"
                                    >
                                        Chưa có phiếu lương
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DEBT -->
                <div
                    v-else
                    class="bg-white border rounded-lg p-6 text-gray-600"
                >
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <div class="text-sm font-semibold text-gray-900">
                                Nợ & tạm ứng
                            </div>
                            <div class="text-xs text-gray-500">
                                Số dư = tổng các giao dịch (dương: công ty nợ
                                NV, âm: NV nợ công ty)
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Số dư</div>
                            <div
                                class="text-lg font-semibold"
                                :class="
                                    financialSummary.balance >= 0
                                        ? 'text-green-700'
                                        : 'text-red-700'
                                "
                            >
                                {{ formatMoney(financialSummary.balance) }}
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 border rounded-lg p-4 mb-4">
                        <div class="text-sm font-semibold text-gray-900 mb-3">
                            Tạo giao dịch
                        </div>
                        <div class="grid grid-cols-12 gap-3 items-end">
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">
                                    Ngày
                                </div>
                                <input
                                    v-model="txForm.occurred_at"
                                    type="date"
                                    class="w-full px-3 py-2 border rounded-md"
                                />
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">
                                    Loại
                                </div>
                                <select
                                    v-model="txForm.type"
                                    class="w-full px-3 py-2 border rounded-md"
                                >
                                    <option value="advance">
                                        Tạm ứng (NV nợ công ty)
                                    </option>
                                    <option value="repayment">
                                        Hoàn/Thanh toán (NV trả lại)
                                    </option>
                                    <option value="adjustment">
                                        Điều chỉnh
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">
                                    Số tiền
                                </div>
                                <input
                                    v-model.number="txForm.amount_abs"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-full px-3 py-2 border rounded-md"
                                />
                            </div>
                            <div
                                class="col-span-12 md:col-span-3"
                                v-if="txForm.type === 'adjustment'"
                            >
                                <div class="text-xs text-gray-600 mb-1">
                                    Dấu
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        class="px-3 py-2 rounded border text-sm"
                                        :class="
                                            txForm.sign === 1
                                                ? 'bg-green-600 text-white border-green-600'
                                                : 'bg-white text-gray-700'
                                        "
                                        @click="txForm.sign = 1"
                                        type="button"
                                    >
                                        +
                                    </button>
                                    <button
                                        class="px-3 py-2 rounded border text-sm"
                                        :class="
                                            txForm.sign === -1
                                                ? 'bg-red-600 text-white border-red-600'
                                                : 'bg-white text-gray-700'
                                        "
                                        @click="txForm.sign = -1"
                                        type="button"
                                    >
                                        -
                                    </button>
                                    <div class="text-xs text-gray-500">
                                        + công ty nợ NV, - NV nợ công ty
                                    </div>
                                </div>
                            </div>
                            <div class="col-span-12 md:col-span-3">
                                <div class="text-xs text-gray-600 mb-1">
                                    Ghi chú
                                </div>
                                <input
                                    v-model="txForm.notes"
                                    type="text"
                                    class="w-full px-3 py-2 border rounded-md"
                                    placeholder="VD: ứng lương"
                                />
                            </div>
                            <div class="col-span-12 flex justify-end">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="txEditId"
                                        class="px-4 py-2 rounded border"
                                        type="button"
                                        @click="cancelEditTx"
                                    >
                                        Hủy sửa
                                    </button>
                                    <button
                                        class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700"
                                        @click="submitTransaction"
                                        :disabled="savingTx"
                                    >
                                        {{
                                            txEditId
                                                ? "Cập nhật"
                                                : "Lưu giao dịch"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border rounded-lg overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Ngày
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Loại
                                    </th>
                                    <th
                                        class="text-right p-3 text-sm font-medium text-gray-600"
                                    >
                                        Số tiền
                                    </th>
                                    <th
                                        class="text-left p-3 text-sm font-medium text-gray-600"
                                    >
                                        Ghi chú
                                    </th>
                                    <th
                                        class="text-right p-3 text-sm font-medium text-gray-600"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="t in financialTransactions"
                                    :key="t.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="p-3 text-sm text-gray-900">
                                        {{ t.occurred_at }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{ txTypeLabel(t.type) }}
                                    </td>
                                    <td
                                        class="p-3 text-sm text-right font-medium"
                                        :class="
                                            Number(t.amount) >= 0
                                                ? 'text-green-700'
                                                : 'text-red-700'
                                        "
                                    >
                                        {{ formatMoney(t.amount) }}
                                    </td>
                                    <td class="p-3 text-sm text-gray-700">
                                        {{ t.notes || "-" }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <div
                                            class="inline-flex items-center gap-3"
                                        >
                                            <button
                                                class="text-blue-600 hover:text-blue-800 text-sm"
                                                @click="editTransaction(t)"
                                            >
                                                Sửa
                                            </button>
                                            <button
                                                class="text-red-600 hover:text-red-800 text-sm"
                                                @click="deleteTransaction(t)"
                                            >
                                                Xóa
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="financialTransactions.length === 0">
                                    <td
                                        colspan="5"
                                        class="p-6 text-center text-gray-500"
                                    >
                                        Chưa có giao dịch
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal edit employee -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-lg w-full max-w-4xl overflow-hidden">
                <div
                    class="px-6 py-4 border-b flex items-center justify-between"
                >
                    <h2 class="text-lg font-semibold">Cập nhật nhân viên</h2>
                    <button
                        class="text-gray-500 hover:text-gray-700"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <div class="px-6 py-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Mã nhân viên</label
                            >
                            <input
                                v-model="form.code"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Ảnh nhân viên</label
                            >
                            <input
                                type="file"
                                accept="image/*"
                                class="w-full text-sm"
                                @change="onAvatarSelected"
                            />
                            <div class="text-xs text-gray-500 mt-1">
                                Upload sẽ lưu ngay, không cần bấm Lưu.
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Trạng thái</label
                            >
                            <select
                                v-model="form.status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            >
                                <option value="active">Đang làm</option>
                                <option value="inactive">Đã nghỉ</option>
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Tên nhân viên *</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Số điện thoại</label
                            >
                            <input
                                v-model="form.phone"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Email</label
                            >
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Mã chấm công</label
                            >
                            <input
                                v-model="form.attendance_code"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Số CMND/CCCD</label
                            >
                            <input
                                v-model="form.id_number"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Chi nhánh trả lương</label
                            >
                            <select
                                v-model="form.warehouse_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            >
                                <option :value="null">-</option>
                                <option
                                    v-for="w in warehouses"
                                    :key="w.id"
                                    :value="w.id"
                                >
                                    {{ w.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Chi nhánh làm việc</label
                            >
                            <div
                                class="border rounded-md p-2 max-h-32 overflow-auto"
                            >
                                <label
                                    v-for="w in warehouses"
                                    :key="w.id"
                                    class="flex items-center gap-2 text-sm py-1 cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="w.id"
                                        v-model="form.work_warehouse_ids"
                                    />
                                    <span>{{ w.name }}</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Phòng ban</label
                            >
                            <input
                                v-model="form.department"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Chức danh</label
                            >
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            />
                        </div>

                        <div class="col-span-2">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Ghi chú</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <div
                    class="px-6 py-4 border-t flex items-center justify-end gap-3"
                >
                    <button class="px-4 py-2 rounded border" @click="close">
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700"
                        @click="saveEmployee"
                        :disabled="savingEmployee"
                    >
                        Lưu
                    </button>
                </div>
            </div>
        </div>

        <div v-if="toast.show" class="fixed top-4 right-4 z-50">
            <div
                class="p-4 rounded-lg shadow-lg max-w-sm"
                :class="
                    toast.type === 'success'
                        ? 'bg-green-100 border border-green-400 text-green-700'
                        : 'bg-red-100 border border-red-400 text-red-700'
                "
            >
                <div class="flex items-center">
                    <span class="mr-2">{{
                        toast.type === "success" ? "✅" : "❌"
                    }}</span>
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import employeeApi from "@/api/employeeApi";
import warehouseApi from "@/api/warehouseApi";

export default {
    name: "EmployeeDetail",
    props: {
        employeeId: { type: Number, required: true },
    },
    setup(props) {
        const tabActive = "text-blue-600 border-b-2 border-blue-600";
        const tabInactive = "text-gray-600 hover:text-gray-800";

        const activeTab = ref("info");

        const loadingEmployee = ref(false);
        const savingEmployee = ref(false);
        const employee = ref(null);

        const warehouses = ref([]);
        const shifts = ref([]);
        const salaryTemplates = ref([]);

        const schedules = ref([]);
        const savingSchedule = ref(false);

        const salaryConfig = ref(null);
        const savingSalary = ref(false);

        const payrollItems = ref([]);

        const financialTransactions = ref([]);
        const financialSummary = ref({ balance: 0 });
        const savingTx = ref(false);
        const txEditId = ref(null);

        const showModal = ref(false);
        const form = ref({
            warehouse_id: null,
            code: "",
            attendance_code: "",
            name: "",
            phone: "",
            email: "",
            id_number: "",
            department: "",
            title: "",
            avatar_path: "",
            work_warehouse_ids: [],
            status: "active",
            notes: "",
        });

        const salaryForm = ref({
            salary_template_id: null,
            pay_warehouse_id: null,
            enable_commission: false,
            commission_rate: 0,
        });

        const scheduleFilter = ref({ from: "", to: "" });
        const payrollFilter = ref({ from: "", to: "" });

        const txForm = ref({
            occurred_at: "",
            type: "advance",
            sign: 1,
            amount_abs: 0,
            notes: "",
        });

        const newSchedule = ref({
            work_date: "",
            slot: 1,
            warehouse_id: null,
            shift_id: null,
        });

        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 3000);
        };

        const ymd = (d) => {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, "0");
            const dd = String(d.getDate()).padStart(2, "0");
            return `${yyyy}-${mm}-${dd}`;
        };

        const defaultRange = () => {
            const now = new Date();
            const from = new Date(now.getFullYear(), now.getMonth(), 1);
            const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            scheduleFilter.value = { from: ymd(from), to: ymd(to) };
            payrollFilter.value = { from: ymd(from), to: ymd(to) };
            newSchedule.value.work_date = ymd(now);
            txForm.value.occurred_at = ymd(now);
            txForm.value.sign = 1;
        };

        const loadWarehouses = async () => {
            try {
                const res = await warehouseApi.getWarehouses({ per_page: 200 });
                warehouses.value = res?.data || [];
            } catch {
                warehouses.value = [];
            }
        };

        const loadEmployee = async () => {
            loadingEmployee.value = true;
            try {
                const res = await employeeApi.getEmployee(props.employeeId);
                employee.value = res.data?.data;
            } catch {
                showToast("Không tải được thông tin nhân viên", "error");
            } finally {
                loadingEmployee.value = false;
            }
        };

        const loadShifts = async () => {
            try {
                const res = await employeeApi.getShifts({ per_page: 200 });
                shifts.value = res.data?.data || [];
            } catch {
                shifts.value = [];
            }
        };

        const loadSalaryTemplates = async () => {
            try {
                const res = await employeeApi.getSalaryTemplates({});
                salaryTemplates.value = res.data?.data || [];
            } catch {
                salaryTemplates.value = [];
            }
        };

        const loadSchedules = async () => {
            try {
                const res = await employeeApi.getSchedules({
                    employee_id: props.employeeId,
                    from: scheduleFilter.value.from,
                    to: scheduleFilter.value.to,
                    per_page: 200,
                });
                schedules.value = res.data?.data || [];
            } catch {
                schedules.value = [];
                showToast("Không tải được lịch làm việc", "error");
            }
        };

        const addSchedule = async () => {
            if (!newSchedule.value.work_date) {
                showToast("Vui lòng chọn ngày", "error");
                return;
            }
            savingSchedule.value = true;
            try {
                await employeeApi.saveSchedule({
                    employee_id: props.employeeId,
                    work_date: newSchedule.value.work_date,
                    slot: newSchedule.value.slot || 1,
                    warehouse_id: newSchedule.value.warehouse_id,
                    shift_id: newSchedule.value.shift_id,
                });
                showToast("Đã lưu lịch làm việc");
                await loadSchedules();
            } catch {
                showToast("Lỗi khi lưu lịch làm việc", "error");
            } finally {
                savingSchedule.value = false;
            }
        };

        const deleteSchedule = async (s) => {
            if (!confirm("Xóa lịch làm việc này?")) return;
            try {
                await employeeApi.deleteSchedule(s.id);
                showToast("Đã xóa lịch làm việc");
                await loadSchedules();
            } catch {
                showToast("Lỗi khi xóa lịch làm việc", "error");
            }
        };

        const loadSalaryConfig = async () => {
            try {
                const res = await employeeApi.getEmployeeSalaryConfigs({
                    employee_id: props.employeeId,
                });
                const list = res.data?.data || [];
                salaryConfig.value = list.length ? list[0] : null;

                if (salaryConfig.value) {
                    salaryForm.value.salary_template_id =
                        salaryConfig.value.salary_template_id;
                    salaryForm.value.pay_warehouse_id =
                        salaryConfig.value.pay_warehouse_id;
                    salaryForm.value.enable_commission =
                        salaryConfig.value.commission_rate != null;
                    salaryForm.value.commission_rate = salaryConfig.value
                        .commission_rate
                        ? Number(salaryConfig.value.commission_rate)
                        : 0;
                }
            } catch {
                salaryConfig.value = null;
            }
        };

        const saveSalaryConfig = async () => {
            if (!salaryForm.value.salary_template_id) {
                showToast("Vui lòng chọn mẫu lương", "error");
                return;
            }
            savingSalary.value = true;
            try {
                if (salaryConfig.value) {
                    await employeeApi.updateEmployeeSalaryConfig(
                        salaryConfig.value.id,
                        {
                            salary_template_id:
                                salaryForm.value.salary_template_id,
                            pay_warehouse_id: salaryForm.value.pay_warehouse_id,
                            commission_rate: salaryForm.value.enable_commission
                                ? salaryForm.value.commission_rate
                                : null,
                        },
                    );
                } else {
                    await employeeApi.createEmployeeSalaryConfig({
                        employee_id: props.employeeId,
                        salary_template_id: salaryForm.value.salary_template_id,
                        pay_warehouse_id: salaryForm.value.pay_warehouse_id,
                        commission_rate: salaryForm.value.enable_commission
                            ? salaryForm.value.commission_rate
                            : null,
                    });
                }
                showToast("Đã lưu thiết lập lương");
                await loadSalaryConfig();
            } catch {
                showToast("Lỗi khi lưu thiết lập lương", "error");
            } finally {
                savingSalary.value = false;
            }
        };

        const loadPayrollItems = async () => {
            try {
                const res = await employeeApi.getPayrollSheetItems({
                    employee_id: props.employeeId,
                    from: payrollFilter.value.from,
                    to: payrollFilter.value.to,
                    per_page: 200,
                });
                payrollItems.value = res.data?.data || [];
            } catch {
                payrollItems.value = [];
                showToast("Không tải được phiếu lương", "error");
            }
        };

        const txTypeLabel = (type) => {
            if (type === "advance") return "Tạm ứng";
            if (type === "repayment") return "Hoàn/Thanh toán";
            if (type === "adjustment") return "Điều chỉnh";
            return type || "-";
        };

        const loadFinancial = async () => {
            try {
                const res = await employeeApi.getEmployeeFinancialTransactions({
                    employee_id: props.employeeId,
                    per_page: 200,
                });
                financialTransactions.value = res.data?.data || [];
                financialSummary.value = res.data?.summary || { balance: 0 };
            } catch {
                financialTransactions.value = [];
                financialSummary.value = { balance: 0 };
            }
        };

        const signedAmountFromForm = () => {
            const abs = Number(txForm.value.amount_abs || 0);
            if (txForm.value.type === "advance") return -abs;
            if (txForm.value.type === "repayment") return abs;
            // adjustment: explicit sign toggle
            return abs * (txForm.value.sign === -1 ? -1 : 1);
        };

        const cancelEditTx = () => {
            txEditId.value = null;
            txForm.value.type = "advance";
            txForm.value.sign = 1;
            txForm.value.amount_abs = 0;
            txForm.value.notes = "";
        };

        const editTransaction = (t) => {
            txEditId.value = t.id;
            txForm.value.occurred_at = t.occurred_at;
            txForm.value.type = t.type || "adjustment";
            txForm.value.sign = Number(t.amount) >= 0 ? 1 : -1;
            txForm.value.amount_abs = Math.abs(Number(t.amount || 0));
            txForm.value.notes = t.notes || "";
        };

        const submitTransaction = async () => {
            if (!txForm.value.occurred_at) {
                showToast("Vui lòng chọn ngày", "error");
                return;
            }
            if (
                !txForm.value.amount_abs ||
                Number(txForm.value.amount_abs) <= 0
            ) {
                showToast("Vui lòng nhập số tiền > 0", "error");
                return;
            }

            savingTx.value = true;
            try {
                const payload = {
                    employee_id: props.employeeId,
                    occurred_at: txForm.value.occurred_at,
                    type: txForm.value.type,
                    amount: signedAmountFromForm(),
                    notes: txForm.value.notes || null,
                    warehouse_id: employee.value?.warehouse_id ?? null,
                };

                if (txEditId.value) {
                    await employeeApi.updateEmployeeFinancialTransaction(
                        txEditId.value,
                        payload,
                    );
                    showToast("Đã cập nhật giao dịch");
                } else {
                    await employeeApi.createEmployeeFinancialTransaction(
                        payload,
                    );
                    showToast("Đã tạo giao dịch");
                }

                cancelEditTx();
                await loadFinancial();
            } catch {
                showToast(
                    txEditId.value
                        ? "Lỗi khi cập nhật giao dịch"
                        : "Lỗi khi tạo giao dịch",
                    "error",
                );
            } finally {
                savingTx.value = false;
            }
        };

        const deleteTransaction = async (t) => {
            if (!confirm("Xóa giao dịch này?")) return;
            try {
                await employeeApi.deleteEmployeeFinancialTransaction(t.id);
                showToast("Đã xóa giao dịch");
                await loadFinancial();
            } catch {
                showToast("Lỗi khi xóa giao dịch", "error");
            }
        };

        const openEdit = () => {
            if (!employee.value) return;
            form.value = {
                warehouse_id: employee.value.warehouse_id ?? null,
                code: employee.value.code || "",
                attendance_code: employee.value.attendance_code || "",
                name: employee.value.name || "",
                phone: employee.value.phone || "",
                email: employee.value.email || "",
                id_number: employee.value.id_number || "",
                department: employee.value.department || "",
                title: employee.value.title || "",
                avatar_path: employee.value.avatar_path || "",
                work_warehouse_ids: (employee.value.work_warehouses || []).map(
                    (w) => w.id,
                ),
                status: employee.value.status || "active",
                notes: employee.value.notes || "",
            };
            showModal.value = true;
        };

        const onAvatarSelected = async (evt) => {
            const file = evt?.target?.files?.[0];
            if (!file) return;
            try {
                const res = await employeeApi.uploadEmployeeAvatar(
                    props.employeeId,
                    file,
                );
                const updated = res.data?.data;
                if (updated) {
                    employee.value = updated;
                    form.value.avatar_path = updated.avatar_path || "";
                }
                showToast("Đã cập nhật ảnh");
            } catch {
                showToast("Lỗi khi upload ảnh", "error");
            } finally {
                // allow re-select same file
                evt.target.value = "";
            }
        };

        const close = () => {
            showModal.value = false;
        };

        const saveEmployee = async () => {
            savingEmployee.value = true;
            try {
                await employeeApi.updateEmployee(props.employeeId, form.value);
                showToast("Đã cập nhật nhân viên");
                showModal.value = false;
                await loadEmployee();
            } catch {
                showToast("Lỗi khi cập nhật nhân viên", "error");
            } finally {
                savingEmployee.value = false;
            }
        };

        const formatMoney = (v) => {
            const n = Number(v || 0);
            return n.toLocaleString("vi-VN");
        };

        onMounted(async () => {
            defaultRange();
            await loadWarehouses();
            await loadShifts();
            await loadSalaryTemplates();
            await loadEmployee();
            await loadSalaryConfig();
            await loadSchedules();
            await loadPayrollItems();
            await loadFinancial();
        });

        return {
            tabActive,
            tabInactive,
            activeTab,
            loadingEmployee,
            employee,
            warehouses,
            shifts,
            salaryTemplates,
            schedules,
            scheduleFilter,
            newSchedule,
            savingSchedule,
            addSchedule,
            loadSchedules,
            deleteSchedule,
            salaryConfig,
            salaryForm,
            savingSalary,
            saveSalaryConfig,
            payrollItems,
            payrollFilter,
            loadPayrollItems,
            financialTransactions,
            financialSummary,
            txForm,
            savingTx,
            txEditId,
            submitTransaction,
            editTransaction,
            cancelEditTx,
            deleteTransaction,
            txTypeLabel,
            showModal,
            form,
            openEdit,
            close,
            saveEmployee,
            savingEmployee,
            onAvatarSelected,
            toast,
            formatMoney,
        };
    },
};
</script>
