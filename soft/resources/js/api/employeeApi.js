import axios from "axios";

const API_BASE = "/api";

const getHeaders = () => {
    const headers = {
        "Content-Type": "application/json",
        Accept: "application/json",
    };

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
    if (csrfToken) headers["X-CSRF-TOKEN"] = csrfToken;

    let accessToken = sessionStorage.getItem("api_token");
    if (!accessToken) {
        accessToken = document
            .querySelector('meta[name="api-token"]')
            ?.getAttribute("content");
    }
    if (accessToken) headers["Authorization"] = `Bearer ${accessToken}`;

    return headers;
};

export const employeeApi = {
    // Employee setup overview
    getEmployeeSetupOverview: (params = {}) =>
        axios.get(`${API_BASE}/employee-setup/overview`, {
            params,
            headers: getHeaders(),
        }),

    getEmployees: (params = {}) =>
        axios.get(`${API_BASE}/employees`, { params, headers: getHeaders() }),
    getEmployee: (id) =>
        axios.get(`${API_BASE}/employees/${id}`, { headers: getHeaders() }),
    createEmployee: (data) =>
        axios.post(`${API_BASE}/employees`, data, { headers: getHeaders() }),
    updateEmployee: (id, data) =>
        axios.put(`${API_BASE}/employees/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteEmployee: (id) =>
        axios.delete(`${API_BASE}/employees/${id}`, { headers: getHeaders() }),

    // Shifts
    getShifts: (params = {}) =>
        axios.get(`${API_BASE}/shifts`, { params, headers: getHeaders() }),
    createShift: (data) =>
        axios.post(`${API_BASE}/shifts`, data, { headers: getHeaders() }),
    updateShift: (id, data) =>
        axios.put(`${API_BASE}/shifts/${id}`, data, { headers: getHeaders() }),
    toggleShift: (id) =>
        axios.patch(
            `${API_BASE}/shifts/${id}/toggle`,
            {},
            { headers: getHeaders() },
        ),
    deleteShift: (id) =>
        axios.delete(`${API_BASE}/shifts/${id}`, { headers: getHeaders() }),

    getSchedules: (params = {}) =>
        axios.get(`${API_BASE}/employee-schedules`, {
            params,
            headers: getHeaders(),
        }),
    saveSchedule: (data) =>
        axios.post(`${API_BASE}/employee-schedules`, data, {
            headers: getHeaders(),
        }),
    updateSchedule: (id, data) =>
        axios.put(`${API_BASE}/employee-schedules/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteSchedule: (id) =>
        axios.delete(`${API_BASE}/employee-schedules/${id}`, {
            headers: getHeaders(),
        }),

    getDevices: (params = {}) =>
        axios.get(`${API_BASE}/attendance-devices`, {
            params,
            headers: getHeaders(),
        }),
    createDevice: (data) =>
        axios.post(`${API_BASE}/attendance-devices`, data, {
            headers: getHeaders(),
        }),
    updateDevice: (id, data) =>
        axios.put(`${API_BASE}/attendance-devices/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteDevice: (id) =>
        axios.delete(`${API_BASE}/attendance-devices/${id}`, {
            headers: getHeaders(),
        }),
    testDeviceConnection: (id) =>
        axios.post(
            `${API_BASE}/attendance-devices/${id}/test-connection`,
            {},
            { headers: getHeaders() },
        ),
    syncDevice: (id) =>
        axios.post(
            `${API_BASE}/attendance-devices/${id}/sync`,
            {},
            { headers: getHeaders() },
        ),

    getAttendanceLogs: (params = {}) =>
        axios.get(`${API_BASE}/attendance-logs`, {
            params,
            headers: getHeaders(),
        }),

    // Timekeeping (derived from schedules + logs)
    getTimekeepingRecords: (params = {}) =>
        axios.get(`${API_BASE}/timekeeping-records`, {
            params,
            headers: getHeaders(),
        }),
    getTimekeepingRecord: (id) =>
        axios.get(`${API_BASE}/timekeeping-records/${id}`, {
            headers: getHeaders(),
        }),
    upsertTimekeepingRecord: (data) =>
        axios.post(`${API_BASE}/timekeeping-records`, data, {
            headers: getHeaders(),
        }),
    updateTimekeepingRecord: (id, data) =>
        axios.put(`${API_BASE}/timekeeping-records/${id}`, data, {
            headers: getHeaders(),
        }),
    recalculateTimekeeping: (data) =>
        axios.post(`${API_BASE}/timekeeping-records/recalculate`, data, {
            headers: getHeaders(),
        }),

    getPayrolls: (params = {}) =>
        axios.get(`${API_BASE}/payrolls`, { params, headers: getHeaders() }),
    createPayroll: (data) =>
        axios.post(`${API_BASE}/payrolls`, data, { headers: getHeaders() }),
    updatePayroll: (id, data) =>
        axios.put(`${API_BASE}/payrolls/${id}`, data, {
            headers: getHeaders(),
        }),
    deletePayroll: (id) =>
        axios.delete(`${API_BASE}/payrolls/${id}`, { headers: getHeaders() }),

    // Payroll sheets (auto)
    getPayrollSheets: (params = {}) =>
        axios.get(`${API_BASE}/payroll-sheets`, {
            params,
            headers: getHeaders(),
        }),
    getPayrollSheet: (id) =>
        axios.get(`${API_BASE}/payroll-sheets/${id}`, {
            headers: getHeaders(),
        }),
    generatePayrollSheet: (data) =>
        axios.post(`${API_BASE}/payroll-sheets/generate`, data, {
            headers: getHeaders(),
        }),
    lockPayrollSheet: (id) =>
        axios.post(
            `${API_BASE}/payroll-sheets/${id}/lock`,
            {},
            { headers: getHeaders() },
        ),
    markPayrollSheetPaid: (id) =>
        axios.post(
            `${API_BASE}/payroll-sheets/${id}/mark-paid`,
            {},
            { headers: getHeaders() },
        ),

    // Payroll sheet items (per employee)
    getPayrollSheetItems: (params = {}) =>
        axios.get(`${API_BASE}/payroll-sheet-items`, {
            params,
            headers: getHeaders(),
        }),

    // Payroll sheet payments (history)
    getPayrollSheetPayments: (params = {}) =>
        axios.get(`${API_BASE}/payroll-sheet-payments`, {
            params,
            headers: getHeaders(),
        }),
    createPayrollSheetPayment: (data) =>
        axios.post(`${API_BASE}/payroll-sheet-payments`, data, {
            headers: getHeaders(),
        }),

    // Employee financial transactions (debt & advance)
    getEmployeeFinancialTransactions: (params = {}) =>
        axios.get(`${API_BASE}/employee-financial-transactions`, {
            params,
            headers: getHeaders(),
        }),
    createEmployeeFinancialTransaction: (data) =>
        axios.post(`${API_BASE}/employee-financial-transactions`, data, {
            headers: getHeaders(),
        }),
    updateEmployeeFinancialTransaction: (id, data) =>
        axios.put(`${API_BASE}/employee-financial-transactions/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteEmployeeFinancialTransaction: (id) =>
        axios.delete(`${API_BASE}/employee-financial-transactions/${id}`, {
            headers: getHeaders(),
        }),

    uploadEmployeeAvatar: (id, file) => {
        const fd = new FormData();
        fd.append("avatar", file);
        const headers = { ...getHeaders() };
        // Let browser set boundary
        delete headers["Content-Type"];
        return axios.post(`${API_BASE}/employees/${id}/avatar`, fd, {
            headers,
        });
    },

    // Salary templates & employee salary configs
    getSalaryTemplates: (params = {}) =>
        axios.get(`${API_BASE}/salary-templates`, {
            params,
            headers: getHeaders(),
        }),
    createSalaryTemplate: (data) =>
        axios.post(`${API_BASE}/salary-templates`, data, {
            headers: getHeaders(),
        }),
    updateSalaryTemplate: (id, data) =>
        axios.put(`${API_BASE}/salary-templates/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteSalaryTemplate: (id) =>
        axios.delete(`${API_BASE}/salary-templates/${id}`, {
            headers: getHeaders(),
        }),

    getEmployeeSalaryConfigs: (params = {}) =>
        axios.get(`${API_BASE}/employee-salary-configs`, {
            params,
            headers: getHeaders(),
        }),
    createEmployeeSalaryConfig: (data) =>
        axios.post(`${API_BASE}/employee-salary-configs`, data, {
            headers: getHeaders(),
        }),
    updateEmployeeSalaryConfig: (id, data) =>
        axios.put(`${API_BASE}/employee-salary-configs/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteEmployeeSalaryConfig: (id) =>
        axios.delete(`${API_BASE}/employee-salary-configs/${id}`, {
            headers: getHeaders(),
        }),

    // Holidays
    getHolidays: (params = {}) =>
        axios.get(`${API_BASE}/holidays`, { params, headers: getHeaders() }),
    createHoliday: (data) =>
        axios.post(`${API_BASE}/holidays`, data, { headers: getHeaders() }),
    updateHoliday: (id, data) =>
        axios.put(`${API_BASE}/holidays/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteHoliday: (id) =>
        axios.delete(`${API_BASE}/holidays/${id}`, { headers: getHeaders() }),

    // Workday settings
    getWorkdaySettings: (params = {}) =>
        axios.get(`${API_BASE}/workday-settings`, {
            params,
            headers: getHeaders(),
        }),
    saveWorkdaySettings: (data) =>
        axios.post(`${API_BASE}/workday-settings`, data, {
            headers: getHeaders(),
        }),

    getTimekeepingSettings: (params = {}) =>
        axios.get(`${API_BASE}/timekeeping-settings`, {
            params,
            headers: getHeaders(),
        }),
    saveTimekeepingSettings: (data) =>
        axios.post(`${API_BASE}/timekeeping-settings`, data, {
            headers: getHeaders(),
        }),

    getPayrollSettings: (params = {}) =>
        axios.get(`${API_BASE}/payroll-settings`, {
            params,
            headers: getHeaders(),
        }),
    savePayrollSettings: (data) =>
        axios.post(`${API_BASE}/payroll-settings`, data, {
            headers: getHeaders(),
        }),

    getCommissions: (params = {}) =>
        axios.get(`${API_BASE}/commissions`, { params, headers: getHeaders() }),
    createCommission: (data) =>
        axios.post(`${API_BASE}/commissions`, data, { headers: getHeaders() }),
    updateCommission: (id, data) =>
        axios.put(`${API_BASE}/commissions/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteCommission: (id) =>
        axios.delete(`${API_BASE}/commissions/${id}`, {
            headers: getHeaders(),
        }),

    // Holidays API
    getHolidays: (params = {}) =>
        axios.get(`${API_BASE}/holidays`, { params, headers: getHeaders() }),
    createHoliday: (data) =>
        axios.post(`${API_BASE}/holidays`, data, { headers: getHeaders() }),
    updateHoliday: (id, data) =>
        axios.put(`${API_BASE}/holidays/${id}`, data, {
            headers: getHeaders(),
        }),
    deleteHoliday: (id) =>
        axios.delete(`${API_BASE}/holidays/${id}`, {
            headers: getHeaders(),
        }),
    autoGenerateHolidays: (year) =>
        axios.post(
            `${API_BASE}/holidays/auto-generate`,
            { year },
            { headers: getHeaders() },
        ),
};

export default employeeApi;
