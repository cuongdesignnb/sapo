<template>
    <div class="bg-white">
        <div class="p-6 border-b flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-gray-900">Bảng lương</h1>

            <div class="flex items-center gap-3">
                <button
                    class="px-4 py-2 rounded border bg-white hover:bg-gray-50"
                    @click="openGenerate"
                >
                    + Bảng tính lương
                </button>
                <button
                    class="px-4 py-2 rounded border bg-white hover:bg-gray-50"
                    @click="
                        showToast(
                            'Chức năng xuất file sẽ bổ sung tiếp',
                            'error',
                        )
                    "
                >
                    Xuất file
                </button>
            </div>
        </div>

        <div class="p-4 bg-gray-50 border-b flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[280px]">
                <div class="relative">
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Theo mã, tên bảng lương"
                        class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-white"
                    />
                    <div
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                    >
                        🔎
                    </div>
                </div>
            </div>

            <div class="min-w-[180px]">
                <select
                    v-model="filters.status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white"
                >
                    <option :value="''">Tất cả trạng thái</option>
                    <option value="draft">Tạm tính</option>
                    <option value="locked">Đã chốt lương</option>
                    <option value="paid">Đã thanh toán</option>
                </select>
            </div>

            <div class="min-w-[170px]">
                <input
                    v-model="filters.from"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white"
                />
            </div>
            <div class="min-w-[170px]">
                <input
                    v-model="filters.to"
                    type="date"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white"
                />
            </div>

            <button
                class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                @click="load"
            >
                Tải
            </button>
        </div>

        <div v-if="loading" class="flex justify-center items-center py-12">
            <div
                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"
            ></div>
            <span class="ml-2 text-gray-600">Đang tải...</span>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="text-left p-3 text-sm font-medium text-gray-600 w-12"
                        ></th>
                        <th
                            class="text-left p-3 text-sm font-medium text-gray-600"
                        >
                            Mã
                        </th>
                        <th
                            class="text-left p-3 text-sm font-medium text-gray-600"
                        >
                            Tên
                        </th>
                        <th
                            class="text-left p-3 text-sm font-medium text-gray-600"
                        >
                            Kỳ hạn trả
                        </th>
                        <th
                            class="text-left p-3 text-sm font-medium text-gray-600"
                        >
                            Kỳ làm việc
                        </th>
                        <th
                            class="text-right p-3 text-sm font-medium text-gray-600"
                        >
                            Tổng lương
                        </th>
                        <th
                            class="text-right p-3 text-sm font-medium text-gray-600"
                        >
                            Đã trả nhân viên
                        </th>
                        <th
                            class="text-right p-3 text-sm font-medium text-gray-600"
                        >
                            Còn cần trả
                        </th>
                        <th
                            class="text-left p-3 text-sm font-medium text-gray-600"
                        >
                            Trạng thái
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    <template v-for="s in filteredSheets" :key="s.id">
                        <tr
                            class="hover:bg-gray-50 cursor-pointer"
                            @click="toggleExpand(s)"
                        >
                            <td class="p-3">
                                <input
                                    type="checkbox"
                                    @click.stop
                                    v-model="selectedSheetIds"
                                    :value="s.id"
                                />
                            </td>
                            <td class="p-3 font-medium text-blue-700">
                                {{
                                    s.code ||
                                    "BL" + String(s.id).padStart(6, "0")
                                }}
                            </td>
                            <td class="p-3">
                                {{ s.name || defaultSheetName(s) }}
                            </td>
                            <td class="p-3">{{ payCycleText(s.pay_cycle) }}</td>
                            <td class="p-3">
                                {{ formatDateVN(s.period_start) }} -
                                {{ formatDateVN(s.period_end) }}
                            </td>
                            <td class="p-3 text-right">
                                {{ formatMoney(s.total_salary) }}
                            </td>
                            <td class="p-3 text-right">
                                {{ formatMoney(s.total_paid) }}
                            </td>
                            <td class="p-3 text-right">
                                {{ formatMoney(s.total_remaining) }}
                            </td>
                            <td class="p-3">
                                <span
                                    class="px-2 py-1 rounded text-xs"
                                    :class="statusClass(s.status)"
                                    >{{ statusText(s.status) }}</span
                                >
                            </td>
                        </tr>

                        <tr v-if="expandedSheetId === s.id">
                            <td colspan="9" class="bg-white p-0">
                                <div class="border-t border-blue-600">
                                    <div class="px-4 pt-3">
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <button
                                                    class="px-3 py-2 rounded border"
                                                    @click.stop="
                                                        activeTab = 'info'
                                                    "
                                                    :class="
                                                        activeTab === 'info'
                                                            ? 'bg-blue-50 border-blue-300'
                                                            : 'bg-white'
                                                    "
                                                >
                                                    Thông tin
                                                </button>
                                                <button
                                                    class="px-3 py-2 rounded border"
                                                    @click.stop="
                                                        activeTab = 'items'
                                                    "
                                                    :class="
                                                        activeTab === 'items'
                                                            ? 'bg-blue-50 border-blue-300'
                                                            : 'bg-white'
                                                    "
                                                >
                                                    Phiếu lương
                                                </button>
                                                <button
                                                    class="px-3 py-2 rounded border"
                                                    @click.stop="
                                                        activeTab = 'payments'
                                                    "
                                                    :class="
                                                        activeTab === 'payments'
                                                            ? 'bg-blue-50 border-blue-300'
                                                            : 'bg-white'
                                                    "
                                                >
                                                    Lịch sử thanh toán
                                                </button>
                                            </div>

                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <button
                                                    class="px-3 py-2 rounded border bg-white hover:bg-gray-50"
                                                    @click.stop="reloadExpanded"
                                                    :disabled="expandedLoading"
                                                >
                                                    Tải lại dữ liệu
                                                </button>
                                                <button
                                                    v-if="
                                                        expanded?.status ===
                                                        'draft'
                                                    "
                                                    class="px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                                                    @click.stop="lockExpanded"
                                                >
                                                    Chốt bảng lương
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="expandedLoading"
                                        class="p-6 text-sm text-gray-600"
                                    >
                                        Đang tải chi tiết...
                                    </div>

                                    <div v-else class="p-4">
                                        <!-- Tab: Info -->
                                        <div
                                            v-if="activeTab === 'info'"
                                            class="grid grid-cols-12 gap-4"
                                        >
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Mã
                                                </div>
                                                <div class="font-medium">
                                                    {{ expanded?.code }}
                                                </div>
                                            </div>
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Tên
                                                </div>
                                                <div class="font-medium">
                                                    {{ expanded?.name }}
                                                </div>
                                            </div>
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Kỳ hạn trả
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        payCycleText(
                                                            expanded?.pay_cycle,
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Kỳ làm việc
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        formatDateVN(
                                                            expanded?.period_start,
                                                        )
                                                    }}
                                                    -
                                                    {{
                                                        formatDateVN(
                                                            expanded?.period_end,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Trạng thái
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        statusText(
                                                            expanded?.status,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Tổng số nhân viên
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        expanded?.employees_count ||
                                                        expanded?.items
                                                            ?.length ||
                                                        0
                                                    }}
                                                </div>
                                            </div>

                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Tổng lương
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        formatMoney(
                                                            expanded?.total_salary,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Đã trả nhân viên
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        formatMoney(
                                                            expanded?.total_paid,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <div
                                                class="col-span-12 md:col-span-4"
                                            >
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Còn cần trả
                                                </div>
                                                <div class="font-medium">
                                                    {{
                                                        formatMoney(
                                                            expanded?.total_remaining,
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <div class="col-span-12">
                                                <div
                                                    class="text-xs text-gray-500"
                                                >
                                                    Ghi chú
                                                </div>
                                                <div
                                                    class="text-sm text-gray-800"
                                                >
                                                    {{ expanded?.notes || "—" }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tab: Items (Payslips) -->
                                        <div v-else-if="activeTab === 'items'">
                                            <div class="overflow-x-auto">
                                                <table class="w-full">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th
                                                                class="p-3 text-left w-12"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    @change="
                                                                        toggleSelectAllItems
                                                                    "
                                                                    :checked="
                                                                        allItemsSelected
                                                                    "
                                                                />
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Mã phiếu
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Tên nhân viên
                                                            </th>
                                                            <th
                                                                class="p-3 text-right text-sm font-medium text-gray-600"
                                                            >
                                                                Tổng lương
                                                            </th>
                                                            <th
                                                                class="p-3 text-right text-sm font-medium text-gray-600"
                                                            >
                                                                Đã trả NV
                                                            </th>
                                                            <th
                                                                class="p-3 text-right text-sm font-medium text-gray-600"
                                                            >
                                                                Còn cần trả
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="divide-y divide-gray-200"
                                                    >
                                                        <tr
                                                            v-for="it in expandedItems"
                                                            :key="it.id"
                                                            class="hover:bg-gray-50"
                                                        >
                                                            <td class="p-3">
                                                                <input
                                                                    type="checkbox"
                                                                    v-model="
                                                                        selectedItemIds
                                                                    "
                                                                    :value="
                                                                        it.id
                                                                    "
                                                                />
                                                            </td>
                                                            <td
                                                                class="p-3 text-blue-700 font-medium"
                                                            >
                                                                {{
                                                                    it.code ||
                                                                    "PL" +
                                                                        String(
                                                                            it.id,
                                                                        ).padStart(
                                                                            6,
                                                                            "0",
                                                                        )
                                                                }}
                                                            </td>
                                                            <td class="p-3">
                                                                {{
                                                                    it.employee
                                                                        ?.name
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-3 text-right"
                                                            >
                                                                {{
                                                                    formatMoney(
                                                                        it.net_salary,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-3 text-right"
                                                            >
                                                                {{
                                                                    formatMoney(
                                                                        it.paid_amount,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-3 text-right"
                                                            >
                                                                {{
                                                                    formatMoney(
                                                                        itemRemaining(
                                                                            it,
                                                                        ),
                                                                    )
                                                                }}
                                                            </td>
                                                        </tr>
                                                        <tr
                                                            v-if="
                                                                expandedItems.length ===
                                                                0
                                                            "
                                                        >
                                                            <td
                                                                colspan="6"
                                                                class="p-6 text-center text-gray-500"
                                                            >
                                                                Chưa có phiếu
                                                                lương
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div
                                                class="flex items-center justify-end mt-4"
                                            >
                                                <button
                                                    class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                                                    @click="openPayModal"
                                                    :disabled="!canOpenPay"
                                                >
                                                    Thanh toán
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Tab: Payments -->
                                        <div v-else>
                                            <div class="overflow-x-auto">
                                                <table class="w-full">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Mã phiếu
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Tên nhân viên
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Thời gian
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Người tạo
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Phương thức
                                                            </th>
                                                            <th
                                                                class="p-3 text-left text-sm font-medium text-gray-600"
                                                            >
                                                                Trạng thái
                                                            </th>
                                                            <th
                                                                class="p-3 text-right text-sm font-medium text-gray-600"
                                                            >
                                                                Tiền chi
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody
                                                        class="divide-y divide-gray-200"
                                                    >
                                                        <tr
                                                            v-for="p in payments"
                                                            :key="p.id"
                                                            class="hover:bg-gray-50"
                                                        >
                                                            <td
                                                                class="p-3 text-blue-700 font-medium"
                                                            >
                                                                {{
                                                                    p.code ||
                                                                    "PCL" +
                                                                        String(
                                                                            p.id,
                                                                        ).padStart(
                                                                            6,
                                                                            "0",
                                                                        )
                                                                }}
                                                            </td>
                                                            <td class="p-3">
                                                                {{
                                                                    p.employee
                                                                        ?.name
                                                                }}
                                                            </td>
                                                            <td class="p-3">
                                                                {{
                                                                    formatDateTimeVN(
                                                                        p.paid_at,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td class="p-3">
                                                                {{
                                                                    p.creator
                                                                        ?.name ||
                                                                    "—"
                                                                }}
                                                            </td>
                                                            <td class="p-3">
                                                                {{
                                                                    paymentMethodText(
                                                                        p.payment_method,
                                                                    )
                                                                }}
                                                            </td>
                                                            <td class="p-3">
                                                                {{
                                                                    p.status ||
                                                                    "paid"
                                                                }}
                                                            </td>
                                                            <td
                                                                class="p-3 text-right"
                                                            >
                                                                {{
                                                                    formatMoney(
                                                                        p.amount,
                                                                    )
                                                                }}
                                                            </td>
                                                        </tr>
                                                        <tr
                                                            v-if="
                                                                payments.length ===
                                                                0
                                                            "
                                                        >
                                                            <td
                                                                colspan="7"
                                                                class="p-6 text-center text-gray-500"
                                                            >
                                                                Chưa có lịch sử
                                                                thanh toán
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr v-if="filteredSheets.length === 0">
                        <td colspan="9" class="p-8 text-center text-gray-500">
                            Chưa có bảng lương
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Generate modal -->
        <div
            v-if="showGenerate"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl w-full max-w-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">Bảng tính lương</h2>
                    <button
                        class="text-gray-500 hover:text-gray-700"
                        @click="closeGenerate"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-12">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Kỳ hạn trả lương</label
                        >
                        <select
                            v-model="generateForm.pay_cycle"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="monthly">Hàng tháng</option>
                            <option value="weekly">Hàng tuần</option>
                            <option value="biweekly">2 tuần/lần</option>
                        </select>
                    </div>

                    <div class="col-span-6">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Từ ngày *</label
                        >
                        <input
                            v-model="generateForm.period_start"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>
                    <div class="col-span-6">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Đến ngày *</label
                        >
                        <input
                            v-model="generateForm.period_end"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>

                    <div class="col-span-12">
                        <label
                            class="flex items-center gap-2 text-sm text-gray-700"
                        >
                            <input
                                type="checkbox"
                                v-model="generateForm.recalculate_timekeeping"
                            />
                            <span
                                >Tự tính lại chấm công trước khi lập bảng</span
                            >
                        </label>
                    </div>

                    <div class="col-span-12">
                        <div class="text-sm font-medium text-gray-700 mb-2">
                            Phạm vi áp dụng
                        </div>
                        <div
                            class="flex items-center gap-6 text-sm text-gray-700"
                        >
                            <label class="flex items-center gap-2">
                                <input
                                    type="radio"
                                    value="all"
                                    v-model="generateForm.scope"
                                />
                                <span>Tất cả nhân viên</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    type="radio"
                                    value="custom"
                                    v-model="generateForm.scope"
                                />
                                <span>Tùy chọn</span>
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="generateForm.scope === 'custom'"
                        class="col-span-12"
                    >
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Chọn nhân viên</label
                        >
                        <select
                            v-model="generateForm.employee_ids"
                            multiple
                            class="w-full px-3 py-2 border border-gray-300 rounded-md h-40"
                        >
                            <option v-if="employeesLoading" disabled>
                                Đang tải danh sách nhân viên...
                            </option>
                            <option
                                v-for="e in employees"
                                :key="e.id"
                                :value="e.id"
                            >
                                {{ e.code }} - {{ e.name }}
                            </option>
                        </select>
                        <div class="text-xs text-gray-500 mt-1">
                            Giữ Ctrl để chọn nhiều nhân viên
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button
                        class="px-4 py-2 rounded border"
                        @click="closeGenerate"
                    >
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                        @click="generate"
                        :disabled="generating"
                    >
                        Tạo
                    </button>
                </div>
            </div>
        </div>

        <!-- Payment modal -->
        <div
            v-if="payModal"
            class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl w-full max-w-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Thanh toán</h3>
                    <button
                        class="text-gray-500 hover:text-gray-700"
                        @click="closePayModal"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-12 gap-4">
                    <div class="col-span-6">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Phương thức</label
                        >
                        <select
                            v-model="payForm.payment_method"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        >
                            <option value="cash">Tiền mặt</option>
                            <option value="bank">Chuyển khoản</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="col-span-6">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Ngày chi</label
                        >
                        <input
                            v-model="payForm.paid_at"
                            type="datetime-local"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        />
                    </div>
                    <div class="col-span-12">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Ghi chú</label
                        >
                        <textarea
                            v-model="payForm.notes"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                        ></textarea>
                    </div>
                    <div class="col-span-12 text-sm text-gray-700">
                        Sẽ chi trả <b>{{ selectedItemIds.length }}</b> phiếu
                        lương (mặc định: chi hết số còn cần trả).
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6">
                    <button
                        class="px-4 py-2 rounded border"
                        @click="closePayModal"
                    >
                        Bỏ qua
                    </button>
                    <button
                        class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                        @click="submitPay"
                        :disabled="paying"
                    >
                        Xác nhận
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
import { ref, onMounted, computed } from "vue";
import employeeApi from "@/api/employeeApi";

export default {
    name: "PayrollList",
    setup() {
        const loading = ref(false);
        const sheets = ref([]);
        const selectedSheetIds = ref([]);
        const expandedSheetId = ref(null);
        const expanded = ref(null);
        const expandedLoading = ref(false);
        const activeTab = ref("info");
        const payments = ref([]);

        const selectedItemIds = ref([]);

        const filters = ref({ search: "", status: "", from: null, to: null });

        const showGenerate = ref(false);
        const generating = ref(false);
        const payrollSettings = ref(null);
        const generateForm = ref({
            pay_cycle: "monthly",
            period_start: null,
            period_end: null,
            recalculate_timekeeping: true,
            scope: "all",
            employee_ids: [],
        });

        const employees = ref([]);
        const employeesLoading = ref(false);

        const payModal = ref(false);
        const paying = ref(false);
        const payForm = ref({
            payment_method: "cash",
            paid_at: null,
            notes: "",
        });

        const toast = ref({ show: false, type: "success", message: "" });
        const showToast = (message, type = "success") => {
            toast.value = { show: true, type, message };
            setTimeout(() => (toast.value.show = false), 3000);
        };

        const formatMoney = (v) => {
            const n = Number(v || 0);
            return n.toLocaleString("vi-VN");
        };

        const daysInMonth = (year, monthIndex0) => {
            return new Date(year, monthIndex0 + 1, 0).getDate();
        };

        const clampDayInMonth = (year, monthIndex0, day) => {
            const max = daysInMonth(year, monthIndex0);
            return Math.min(Math.max(1, Number(day || 1)), max);
        };

        const toYmdString = (dt) => {
            const y = dt.getFullYear();
            const m = String(dt.getMonth() + 1).padStart(2, "0");
            const d = String(dt.getDate()).padStart(2, "0");
            return `${y}-${m}-${d}`;
        };

        const computeSuggestedPeriod = (settings, now = new Date()) => {
            const cycle = settings?.pay_cycle || "monthly";

            // Default to a completed period ending yesterday for non-monthly cycles
            if (cycle === "weekly" || cycle === "biweekly") {
                const span = cycle === "biweekly" ? 14 : 7;
                const end = new Date(now);
                end.setDate(end.getDate() - 1);
                const start = new Date(end);
                start.setDate(start.getDate() - (span - 1));
                return {
                    period_start: toYmdString(start),
                    period_end: toYmdString(end),
                };
            }

            const startDay = Number(settings?.start_day ?? 26);
            const endDay = Number(settings?.end_day ?? 25);
            const startInPrevMonth = !!(settings?.start_in_prev_month ?? true);

            // Choose the most recent period end that is not in the future
            const currentY = now.getFullYear();
            const currentM = now.getMonth(); // 0-based
            const currentD = now.getDate();
            const endMonth = currentD >= endDay ? currentM : currentM - 1;
            const endDate = new Date(
                currentY,
                endMonth,
                clampDayInMonth(currentY, endMonth, endDay),
            );

            const startMonth = startInPrevMonth ? endMonth - 1 : endMonth;
            const startDate = new Date(
                currentY,
                startMonth,
                clampDayInMonth(currentY, startMonth, startDay),
            );

            return {
                period_start: toYmdString(startDate),
                period_end: toYmdString(endDate),
            };
        };

        const toYmd = (d) => {
            if (!d) return "";
            if (typeof d === "string") return d.slice(0, 10);
            const dt = new Date(d);
            const y = dt.getFullYear();
            const m = String(dt.getMonth() + 1).padStart(2, "0");
            const day = String(dt.getDate()).padStart(2, "0");
            return `${y}-${m}-${day}`;
        };

        const formatDateVN = (ymd) => {
            if (!ymd) return "—";
            const s = String(ymd).slice(0, 10);
            const [y, m, d] = s.split("-");
            return `${d}/${m}/${y}`;
        };

        const formatDateTimeVN = (dt) => {
            if (!dt) return "—";
            const s = String(dt);
            const date = s.slice(0, 10);
            const time = s.includes("T") ? s.split("T")[1].slice(0, 8) : "";
            return `${formatDateVN(date)} ${time}`.trim();
        };

        const payCycleText = (cycle) => {
            if (cycle === "weekly") return "Hàng tuần";
            if (cycle === "biweekly") return "2 tuần/lần";
            return "Hàng tháng";
        };

        const paymentMethodText = (m) => {
            if (m === "bank") return "Chuyển khoản";
            if (m === "other") return "Khác";
            return "Tiền mặt";
        };

        const statusText = (st) => {
            if (st === "locked") return "Đã chốt lương";
            if (st === "paid") return "Đã thanh toán";
            return "Tạm tính";
        };

        const statusClass = (st) => {
            if (st === "locked") return "bg-blue-100 text-blue-700";
            if (st === "paid") return "bg-green-100 text-green-700";
            return "bg-orange-100 text-orange-700";
        };

        const defaultSheetName = (s) => {
            const end = toYmd(s?.period_end);
            if (!end) return "Bảng lương";
            const [y, m] = end.split("-");
            return `Bảng lương tháng ${m}/${y}`;
        };

        const load = async () => {
            loading.value = true;
            try {
                const res = await employeeApi.getPayrollSheets({
                    status: filters.value.status || undefined,
                    from: filters.value.from || undefined,
                    to: filters.value.to || undefined,
                    per_page: 100,
                });
                sheets.value = res.data?.data || [];
            } catch {
                showToast("Lỗi khi tải bảng lương", "error");
            } finally {
                loading.value = false;
            }
        };

        const filteredSheets = computed(() => {
            const q = (filters.value.search || "").trim().toLowerCase();
            if (!q) return sheets.value;
            return sheets.value.filter((s) => {
                const code = String(s.code || "").toLowerCase();
                const name = String(
                    s.name || defaultSheetName(s) || "",
                ).toLowerCase();
                return `${code} ${name}`.includes(q);
            });
        });

        const toggleExpand = async (s) => {
            if (expandedSheetId.value === s.id) {
                expandedSheetId.value = null;
                expanded.value = null;
                payments.value = [];
                selectedItemIds.value = [];
                return;
            }
            expandedSheetId.value = s.id;
            activeTab.value = "info";
            selectedItemIds.value = [];
            await reloadExpanded();
        };

        const reloadExpanded = async () => {
            if (!expandedSheetId.value) return;
            expandedLoading.value = true;
            try {
                const res = await employeeApi.getPayrollSheet(
                    expandedSheetId.value,
                );
                expanded.value = res.data?.data;
                const payRes = await employeeApi.getPayrollSheetPayments({
                    payroll_sheet_id: expandedSheetId.value,
                    per_page: 200,
                });
                payments.value = payRes.data?.data || [];
            } catch {
                showToast("Không tải được chi tiết bảng lương", "error");
            } finally {
                expandedLoading.value = false;
            }
        };

        const expandedItems = computed(() => {
            return expanded.value?.items || [];
        });

        const itemRemaining = (it) => {
            const net = Number(it?.net_salary || 0);
            const paid = Number(it?.paid_amount || 0);
            return Math.max(0, net - paid);
        };

        const allItemsSelected = computed(() => {
            if (!expandedItems.value.length) return false;
            return expandedItems.value.every((it) =>
                selectedItemIds.value.includes(it.id),
            );
        });

        const toggleSelectAllItems = (e) => {
            const checked = !!e.target.checked;
            if (!checked) {
                selectedItemIds.value = [];
                return;
            }
            selectedItemIds.value = expandedItems.value.map((it) => it.id);
        };

        const canOpenPay = computed(() => {
            if (!expanded.value) return false;
            if (!inArray(expanded.value.status, ["locked", "paid"]))
                return false;
            if (!selectedItemIds.value.length) return false;
            const anyRemaining = expandedItems.value
                .filter((it) => selectedItemIds.value.includes(it.id))
                .some((it) => itemRemaining(it) > 0);
            return anyRemaining;
        });

        const openPayModal = () => {
            if (!canOpenPay.value) return;
            payForm.value = {
                payment_method: "cash",
                paid_at: null,
                notes: "",
            };
            payModal.value = true;
        };

        const closePayModal = () => (payModal.value = false);

        const submitPay = async () => {
            if (!expanded.value) return;
            paying.value = true;
            try {
                const items = expandedItems.value
                    .filter((it) => selectedItemIds.value.includes(it.id))
                    .map((it) => ({
                        payroll_sheet_item_id: it.id,
                        amount: itemRemaining(it),
                    }))
                    .filter((x) => x.amount > 0);

                if (!items.length) {
                    showToast("Không có phiếu nào còn cần trả", "error");
                    return;
                }

                await employeeApi.createPayrollSheetPayment({
                    payroll_sheet_id: expanded.value.id,
                    payment_method: payForm.value.payment_method,
                    paid_at: payForm.value.paid_at
                        ? new Date(payForm.value.paid_at).toISOString()
                        : null,
                    notes: payForm.value.notes || null,
                    items,
                });

                showToast("Đã ghi nhận thanh toán");
                payModal.value = false;
                await reloadExpanded();
                await load();
            } catch {
                showToast("Lỗi khi thanh toán", "error");
            } finally {
                paying.value = false;
            }
        };

        const lockExpanded = async () => {
            if (!expanded.value) return;
            try {
                await employeeApi.lockPayrollSheet(expanded.value.id);
                showToast("Đã chốt bảng lương");
                await reloadExpanded();
                await load();
            } catch {
                showToast("Không chốt được bảng lương", "error");
            }
        };

        const openGenerate = () => {
            showGenerate.value = true;

            const settings = payrollSettings.value;
            const suggested = computeSuggestedPeriod(settings, new Date());

            generateForm.value = {
                pay_cycle: settings?.pay_cycle || "monthly",
                period_start: suggested?.period_start || null,
                period_end: suggested?.period_end || null,
                recalculate_timekeeping:
                    settings?.default_recalculate_timekeeping ?? true,
                scope: "all",
                employee_ids: [],
            };
        };

        const closeGenerate = () => (showGenerate.value = false);

        const generate = async () => {
            if (
                !generateForm.value.period_start ||
                !generateForm.value.period_end
            ) {
                showToast("Vui lòng chọn từ ngày/đến ngày", "error");
                return;
            }
            if (
                generateForm.value.scope === "custom" &&
                (!generateForm.value.employee_ids ||
                    generateForm.value.employee_ids.length === 0)
            ) {
                showToast("Vui lòng chọn ít nhất 1 nhân viên", "error");
                return;
            }
            generating.value = true;
            try {
                const payload = {
                    pay_cycle: generateForm.value.pay_cycle || "monthly",
                    period_start: toYmd(generateForm.value.period_start),
                    period_end: toYmd(generateForm.value.period_end),
                    recalculate_timekeeping:
                        !!generateForm.value.recalculate_timekeeping,
                    scope: generateForm.value.scope || "all",
                    employee_ids:
                        generateForm.value.scope === "custom"
                            ? generateForm.value.employee_ids
                            : undefined,
                };

                const res = await employeeApi.generatePayrollSheet(payload);
                const created = res.data?.data;
                showToast("Đã tạo bảng lương");
                showGenerate.value = false;
                await load();
                if (created?.id) {
                    const s =
                        sheets.value.find((x) => x.id === created.id) ||
                        created;
                    await toggleExpand(s);
                }
            } catch {
                showToast("Lỗi khi tạo bảng lương", "error");
            } finally {
                generating.value = false;
            }
        };

        onMounted(async () => {
            await load();

            // Load payroll settings for suggested defaults
            try {
                const sRes = await employeeApi.getPayrollSettings();
                payrollSettings.value = sRes?.data?.data || null;
            } catch {
                payrollSettings.value = null;
            }

            // For generate modal (custom employee scope)
            employeesLoading.value = true;
            try {
                const res = await employeeApi.getEmployees({ per_page: 2000 });
                employees.value = res.data?.data || [];
            } catch {
                employees.value = [];
            } finally {
                employeesLoading.value = false;
            }
        });

        return {
            loading,
            sheets,
            filteredSheets,
            selectedSheetIds,
            expandedSheetId,
            expanded,
            expandedLoading,
            activeTab,
            payments,
            selectedItemIds,
            allItemsSelected,
            filters,
            load,
            toggleExpand,
            reloadExpanded,
            expandedItems,
            itemRemaining,
            toggleSelectAllItems,
            canOpenPay,
            openPayModal,
            closePayModal,
            submitPay,
            lockExpanded,
            showGenerate,
            generating,
            payrollSettings,
            generateForm,
            employees,
            employeesLoading,
            openGenerate,
            closeGenerate,
            generate,
            payModal,
            paying,
            payForm,
            formatMoney,
            formatDateVN,
            formatDateTimeVN,
            statusText,
            statusClass,
            payCycleText,
            paymentMethodText,
            defaultSheetName,
            toast,
            showToast,
        };
    },
};

function inArray(v, arr) {
    return arr.includes(v);
}
</script>
