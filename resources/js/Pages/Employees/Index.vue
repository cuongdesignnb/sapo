<script setup>
import { ref, watch } from 'vue';
import { Head, router, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ExcelButtons from '@/Components/ExcelButtons.vue';

const props = defineProps({
    employees: Object,
    branches: Array,
    departments: Array,
    jobTitles: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const expandedRows = ref([]); 

let searchTimeout;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/employees', { search: value }, {
            preserveState: true,
            replace: true
        });
    }, 500);
});

const toggleExpand = (employeeId) => {
    const index = expandedRows.value.indexOf(employeeId);
    if (index > -1) {
        expandedRows.value.splice(index, 1);
    } else {
        expandedRows.value.push(employeeId);
    }
};

const isExpanded = (employeeId) => {
    return expandedRows.value.includes(employeeId);
};

// Modal form state
const showCreateModal = ref(false);
const activeTab = ref('info'); // info | salary

const form = useForm({
    id: null,
    code: '',
    attendance_code: '',
    name: '',
    phone: '',
    email: '',
    cccd: '',
    branch_id: null,
    department_id: null,
    job_title_id: null,
    notes: '',
    is_active: true,
});

const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    form.id = null;
    activeTab.value = 'info';
    showCreateModal.value = true;
};

const openEditModal = (employee) => {
    form.reset();
    form.clearErrors();
    form.id = employee.id;
    form.code = employee.code;
    form.attendance_code = employee.attendance_code || '';
    form.name = employee.name;
    form.phone = employee.phone;
    form.email = employee.email;
    form.cccd = employee.cccd;
    form.branch_id = employee.branch_id;
    form.department_id = employee.department_id;
    form.job_title_id = employee.job_title_id;
    form.notes = employee.notes;
    form.is_active = employee.is_active;
    
    activeTab.value = 'info';
    showCreateModal.value = true;
};

const submit = () => {
    if (form.id) {
        form.put(`/employees/${form.id}`, {
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post('/employees', {
            onSuccess: () => {
                showCreateModal.value = false;
                form.reset();
            }
        });
    }
};
</script>

<template>
    <Head title="Nhân viên - KiotViet Clone" />
    <AppLayout>
        <!-- Sidebar slot -->
        <template #sidebar>
            <!-- Lọc TRẠNG THÁI NHÂN VIÊN -->
            <div class="px-3 py-4 border-b border-gray-200">
                <label class="block text-sm font-bold text-gray-800 mb-2">Trạng thái nhân viên</label>
                <div class="space-y-2 text-sm text-gray-700">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="is_active" checked class="text-blue-600 focus:ring-blue-500 w-4 h-4"> Đang làm việc
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-gray-500">
                        <input type="radio" name="is_active" class="text-blue-600 focus:ring-blue-500 w-4 h-4"> Đã nghỉ
                    </label>
                </div>
            </div>

            <!-- Lọc CHI NHÁNH LÀM VIỆC -->
            <div class="px-3 py-4 border-b border-gray-200">
                <label class="block text-sm font-bold text-gray-800 mb-2">Chi nhánh làm việc</label>
                <select class="w-full border border-gray-300 rounded p-1.5 text-sm outline-none text-gray-700">
                    <option value="">Chọn chi nhánh</option>
                    <option v-for="br in branches" :key="br.id" :value="br.id">{{ br.name }}</option>
                </select>
            </div>

            <!-- Lọc PHÒNG BAN -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-bold text-gray-800">Phòng ban</label>
                    <button class="text-gray-400 hover:text-blue-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                </div>
                <select class="w-full border border-gray-300 rounded p-1.5 text-sm outline-none text-gray-500">
                    <option value="">Chọn phòng ban</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                </select>
            </div>

            <!-- Lọc CHỨC DANH -->
            <div class="px-3 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-bold text-gray-800">Chức danh</label>
                    <button class="text-gray-400 hover:text-blue-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg></button>
                </div>
                <select class="w-full border border-gray-300 rounded p-1.5 text-sm outline-none text-gray-500">
                    <option value="">Chọn chức danh</option>
                    <option v-for="jt in jobTitles" :key="jt.id" :value="jt.id">{{ jt.name }}</option>
                </select>
            </div>
        </template>

        <!-- Main content -->
        <div class="bg-white h-full flex flex-col pt-3">
            <!-- Header Toolbar -->
            <div class="flex items-center justify-between px-4 pb-3 border-b border-gray-200">
                <div class="flex items-center gap-4 flex-1 max-w-2xl text-2xl font-bold text-gray-800">
                    Danh sách nhân viên
                </div>
                
                <div class="relative w-80 ml-auto mr-4 border-b border-gray-300">
                    <svg class="w-4 h-4 absolute left-1 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" v-model="search" placeholder="Theo mã, tên nhân viên" class="w-full pl-7 pr-8 py-1.5 focus:outline-none text-sm placeholder-gray-400 bg-transparent">
                    <svg class="w-4 h-4 absolute right-1 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                </div>

                <div class="flex gap-2 ml-2">
                    <button @click="openCreateModal" class="bg-white text-blue-600 border border-blue-600 px-3 py-1.5 text-sm font-medium rounded flex items-center gap-1 hover:bg-blue-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>Nhân viên
                    </button>
                    <ExcelButtons export-url="/employees/export" import-url="/employees/import" />
                    <button class="bg-white text-gray-600 border border-gray-300 px-2.5 py-1.5 rounded hover:bg-gray-50">
                        <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/></svg>
                    </button>
                    
                </div>
            </div>

            <!-- Table -->
            <div class="flex-1 overflow-auto bg-[#f8fbff]">
                <table class="w-full text-sm text-left whitespace-nowrap">
                    <thead class="text-[13px] font-bold text-gray-700 bg-[#eef1f8] border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-4 py-3 w-10 text-center"><input type="checkbox" class="rounded border-gray-300"></th>
                            <th class="px-4 py-3 w-12">Ảnh</th>
                            <th class="px-4 py-3">Mã nhân viên</th>
                            <th class="px-4 py-3">Mã chấm công</th>
                            <th class="px-4 py-3">Tên nhân viên</th>
                            <th class="px-4 py-3">Số điện thoại</th>
                            <th class="px-4 py-3">Số CMND/CCCD</th>
                            <th class="px-4 py-3 text-right">Nợ và tạm ứng</th>
                            <th class="px-4 py-3">Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-gray-800">
                        <tr v-if="employees.data.length === 0">
                             <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                 Không tìm thấy nhân viên nào.
                             </td>
                        </tr>
                        <template v-for="employee in employees.data" :key="employee.id">
                            <!-- Main Row -->
                            <tr @click="openEditModal(employee)" class="hover:bg-blue-50/50 transition-colors cursor-pointer bg-white" >
                                <td class="px-4 py-3 text-center" @click.stop><input type="checkbox" class="rounded border-gray-300 text-blue-500 focus:ring-blue-500"></td>
                                <td class="px-4 py-3">
                                   <!-- Avatar placeholder -->
                                   <div class="w-8 h-8 bg-gray-200 rounded text-gray-400 flex items-center justify-center">
                                       <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                                   </div>
                                </td>
                                <td class="px-4 py-3">{{ employee.code }}</td>
                                <td class="px-4 py-3">{{ employee.attendance_code || '' }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-800">{{ employee.name }}</td>
                                <td class="px-4 py-3">{{ employee.phone }}</td>
                                <td class="px-4 py-3">{{ employee.cccd }}</td>
                                <td class="px-4 py-3 text-right">{{ Number(employee.balance).toLocaleString() }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ employee.notes || '' }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Footer Pagination -->
            <div class="flex items-center justify-between px-4 py-2 border-t border-gray-200 bg-white text-sm">
                <div class="text-gray-600">
                    Hiển thị từ <span class="font-bold">{{ employees.from || 0 }}</span> đến <span class="font-bold">{{ employees.to || 0 }}</span> trong tổng số <span class="font-bold">{{ employees.total || 0 }}</span> bản ghi
                </div>
                <!-- Pagination -->
                <div class="flex gap-1" v-if="employees.links && employees.links.length > 3">
                    <template v-for="(link, index) in employees.links" :key="index">
                        <Link 
                            v-if="link.url"
                            :href="link.url" 
                            class="px-2.5 py-1 text-sm border rounded"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'"
                            v-html="link.label"
                        ></Link>
                        <span v-else class="px-2.5 py-1 text-sm border rounded bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- CREATE/EDIT EMPLOYEE MODAL -->
        <div v-if="showCreateModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 pt-10 pb-10">
             <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-full overflow-hidden flex flex-col relative text-[13px] text-gray-800">
                  <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-white shadow-sm z-10 relative">
                       <h2 class="text-xl font-bold text-gray-800">{{ form.id ? 'Cập nhật nhân viên' : 'Thêm mới nhân viên' }}</h2>
                       <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                           <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                       </button>
                  </div>
                  
                  <!-- Tabs Control -->
                  <div class="flex px-6 border-b border-gray-200 pt-3 relative bg-white z-10">
                      <button @click="activeTab = 'info'" 
                          class="px-4 py-2 font-bold text-[14px]"
                          :class="activeTab === 'info' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                          Thông tin
                      </button>
                      <button @click="activeTab = 'salary'" 
                          class="px-4 py-2 font-bold text-[14px]"
                          :class="activeTab === 'salary' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                          Thiết lập lương
                      </button>
                  </div>

                  <div class="flex-1 overflow-y-auto px-6 py-6 custom-scrollbar text-[13.5px] bg-[#f8fbff]">
                       <form @submit.prevent="submit" class="space-y-6">
                           <!-- TAB THÔNG TIN -->
                           <div v-show="activeTab === 'info'" class="bg-white border border-gray-200 shadow-sm rounded-lg p-5">
                                <div class="font-bold text-[15px] mb-4 text-gray-800">Thông tin khởi tạo</div>
                                <div class="flex gap-8 items-start">
                                   <!-- Avatar Circle Upload -->
                                   <div class="w-32 flex flex-col items-center mt-2">
                                        <div class="w-28 h-28 rounded border border-dashed border-gray-400 bg-gray-50 flex items-center justify-center flex-col text-gray-500 cursor-pointer hover:bg-gray-100 transition">
                                            <svg class="w-6 h-6 mb-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        </div>
                                        <div class="font-bold mb-2 mt-2">Chọn ảnh</div>
                                   </div>

                                   <!-- Form Fields -->
                                   <div class="flex-1 grid grid-cols-2 gap-x-6 gap-y-4">
                                       <!-- Row 1 -->
                                       <div>
                                           <label class="block font-semibold mb-1">Mã nhân viên</label>
                                           <input v-model="form.code" type="text" class="w-full border border-gray-300 rounded-md px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-400" placeholder="Mã nhân viên tự động">
                                       </div>
                                       <div>
                                           <label class="block font-semibold mb-1">Tên nhân viên</label>
                                            <input v-model="form.name" type="text" class="w-full border border-gray-300 rounded-md px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none" required>
                                       </div>
                                       
                                       <div>
                                           <label class="block font-semibold mb-1">Mã chấm công</label>
                                            <input v-model="form.attendance_code" type="text" class="w-full border border-gray-300 rounded-md px-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none" placeholder="Từ máy chấm">
                                       </div>
                                       
                                       <!-- Row 2 -->
                                       <div>
                                            <label class="block font-semibold mb-1">Số điện thoại</label>
                                            <input v-model="form.phone" type="text" class="w-full border border-gray-300 rounded-md px-3 py-1.5 focus:border-blue-500 outline-none">
                                       </div>
                                       <div>
                                           <label class="block font-semibold mb-1">Số CMND/CCCD</label>
                                           <input v-model="form.cccd" type="text" class="w-full border border-gray-300 rounded-md px-3 py-1.5 focus:border-blue-500 outline-none">
                                       </div>

                                       <!-- Row 3 -->
                                       <div class="col-span-2">
                                            <label class="block font-semibold mb-1">Chi nhánh làm việc</label>
                                            <select v-model="form.branch_id" class="w-full border border-gray-300 rounded-md px-3 py-2 bg-blue-600 text-white focus:outline-none">
                                                <option v-for="br in branches" :key="br.id" :value="br.id">{{ br.name }} <span v-if="br.id">x</span></option>
                                            </select>
                                       </div>
                                       
                                       <div class="col-span-2 pt-2 border-t border-gray-100 mt-2 text-center">
                                            <button type="button" class="text-blue-600 font-bold hover:underline flex items-center justify-center gap-1 mx-auto">
                                                Thêm thông tin
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                            </button>
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <!-- TAB THIẾT LẬP LƯƠNG -->
                           <div v-show="activeTab === 'salary'" class="space-y-4">
                               <div class="bg-white border border-gray-200 shadow-sm rounded-lg p-5">
                                    <div class="font-bold text-[15px] mb-4 text-gray-800">Lương chính</div>
                                    <div class="w-1/2">
                                        <label class="block font-semibold mb-1 text-gray-500">Loại lương</label>
                                        <select class="w-full border border-blue-400 text-blue-600 font-medium rounded-md px-3 py-1.5 focus:outline-none">
                                            <option>Chọn Loại lương</option>
                                            <option>Cố định</option>
                                            <option>Theo giờ</option>
                                        </select>
                                    </div>
                                    <div class="w-1/2 mt-4">
                                        <label class="block font-semibold mb-1 text-gray-500 flex items-center gap-1">Mẫu lương <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></label>
                                        <select class="w-full border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none text-gray-700">
                                            <option>Chọn mẫu lương có sẵn</option>
                                        </select>
                                    </div>
                               </div>
                               
                               <div class="bg-white border border-gray-200 shadow-sm rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-[14px] text-gray-800">Thưởng</div>
                                        <div class="text-[12px] text-gray-500 mt-0.5">Thiết lập thưởng theo doanh thu cho nhân viên</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                               </div>

                               <div class="bg-white border border-gray-200 shadow-sm rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-[14px] text-gray-800">Hoa hồng</div>
                                        <div class="text-[12px] text-gray-500 mt-0.5">Thiết lập mức hoa hồng theo sản phẩm hoặc dịch vụ</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                               </div>

                               <div class="bg-white border border-gray-200 shadow-sm rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-[14px] text-gray-800">Phụ cấp</div>
                                        <div class="text-[12px] text-gray-500 mt-0.5">Thiết lập khoản hỗ trợ làm việc như ăn trưa, đi lại, điện thoại, ...</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                               </div>

                               <div class="bg-white border border-gray-200 shadow-sm rounded-lg p-4 flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-[14px] text-gray-800">Giảm trừ</div>
                                        <div class="text-[12px] text-gray-500 mt-0.5">Thiết lập khoản giảm trừ như đi muộn, về sớm, vi phạm nội quy, ...</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                               </div>
                           </div>

                       </form>
                  </div>
                  
                  <!-- Modal Footer Actions -->
                  <div class="px-6 py-4 border-t border-gray-200 bg-white flex justify-end gap-3 rounded-b shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] z-10">
                       <button @click="showCreateModal = false" class="px-6 py-2 border border-gray-300 rounded text-gray-700 bg-white font-bold hover:bg-gray-50 transition shadow-sm">Bỏ qua</button>
                       <button v-show="activeTab === 'salary'" @click="submit" class="px-6 py-2 border border-gray-300 rounded text-gray-700 bg-white font-bold hover:bg-gray-50 transition shadow-sm">Lưu và tạo mẫu lương mới</button>
                       <button @click="submit" class="px-8 py-2 border border-transparent rounded text-white bg-blue-600 font-bold hover:bg-blue-700 transition shadow-sm" :class="{ 'opacity-50 cursor-not-allowed': form.processing }">Lưu</button>
                  </div>
             </div>
        </div>

    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #d1d5db;
  border-radius: 10px;
}
</style>
