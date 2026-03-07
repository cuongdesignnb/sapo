<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $query = Holiday::query();

        if ($request->filled('from')) {
            $query->whereDate('holiday_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('holiday_date', '<=', $request->date('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $holidays = $query->orderBy('holiday_date')->get();

        return response()->json([
            'success' => true,
            'data' => $holidays,
        ]);
    }

    public function show(Holiday $holiday)
    {
        return response()->json([
            'success' => true,
            'data' => $holiday,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'name' => ['required', 'string', 'max:255'],
            'multiplier' => ['nullable', 'numeric', 'min:0'],
            'paid_leave' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $holiday = Holiday::create([
            'holiday_date' => $data['holiday_date'],
            'name' => $data['name'],
            'multiplier' => $data['multiplier'] ?? 1,
            'paid_leave' => (bool) ($data['paid_leave'] ?? false),
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo ngày lễ thành công',
            'data' => $holiday,
        ]);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date,' . $holiday->id],
            'name' => ['required', 'string', 'max:255'],
            'multiplier' => ['nullable', 'numeric', 'min:0'],
            'paid_leave' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);

        $holiday->update([
            'holiday_date' => $data['holiday_date'],
            'name' => $data['name'],
            'multiplier' => $data['multiplier'] ?? $holiday->multiplier,
            'paid_leave' => (bool) ($data['paid_leave'] ?? $holiday->paid_leave),
            'status' => $data['status'] ?? $holiday->status,
            'notes' => $data['notes'] ?? $holiday->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật ngày lễ thành công',
            'data' => $holiday,
        ]);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xóa ngày lễ thành công',
        ]);
    }

    /**
     * Auto-generate Vietnamese holidays for a specific year
     */
    public function autoGenerate(Request $request)
    {
        $year = $request->integer('year', (int) now()->year);
        
        $holidays = $this->getVietnameseHolidays($year);
        
        $created = 0;
        $skipped = 0;
        
        foreach ($holidays as $holiday) {
            $exists = Holiday::where('holiday_date', $holiday['date'])->exists();
            
            if (!$exists) {
                Holiday::create([
                    'holiday_date' => $holiday['date'],
                    'name' => $holiday['name'],
                    'multiplier' => $holiday['multiplier'] ?? 1,
                    'paid_leave' => $holiday['paid_leave'] ?? true,
                    'status' => 'active',
                    'notes' => $holiday['notes'] ?? null,
                ]);
                $created++;
            } else {
                $skipped++;
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Đã tạo {$created} ngày lễ, bỏ qua {$skipped} ngày đã tồn tại",
            'data' => [
                'year' => $year,
                'created' => $created,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * Get list of Vietnamese holidays for a year
     * Includes both solar and lunar calendar holidays
     */
    private function getVietnameseHolidays(int $year): array
    {
        $holidays = [];
        
        // Fixed solar calendar holidays
        $holidays[] = [
            'date' => "{$year}-01-01",
            'name' => 'Tết Dương lịch',
            'multiplier' => 2,
            'paid_leave' => true,
        ];
        
        $holidays[] = [
            'date' => "{$year}-04-30",
            'name' => 'Ngày Giải phóng miền Nam',
            'multiplier' => 2,
            'paid_leave' => true,
        ];
        
        $holidays[] = [
            'date' => "{$year}-05-01",
            'name' => 'Ngày Quốc tế Lao động',
            'multiplier' => 2,
            'paid_leave' => true,
        ];
        
        $holidays[] = [
            'date' => "{$year}-09-02",
            'name' => 'Ngày Quốc khánh',
            'multiplier' => 2,
            'paid_leave' => true,
        ];
        
        // Lunar calendar holidays (approximate calculation)
        // Tet Nguyen Dan (Lunar New Year) - typically late Jan or Feb
        $tetDates = $this->calculateLunarNewYear($year);
        foreach ($tetDates as $idx => $date) {
            $dayName = match($idx) {
                0 => 'Giao thừa',
                1 => 'Mùng 1 Tết',
                2 => 'Mùng 2 Tết',
                3 => 'Mùng 3 Tết',
                4 => 'Mùng 4 Tết',
                default => 'Tết Nguyên Đán',
            };
            
            $holidays[] = [
                'date' => $date,
                'name' => $dayName,
                'multiplier' => 2,
                'paid_leave' => true,
                'notes' => 'Tết Nguyên Đán ' . $year,
            ];
        }
        
        // Gio To Hung Vuong (10/3 lunar = around April solar)
        $hungVuongDate = $this->lunarToSolar($year, 3, 10);
        if ($hungVuongDate) {
            $holidays[] = [
                'date' => $hungVuongDate,
                'name' => 'Giỗ Tổ Hùng Vương',
                'multiplier' => 2,
                'paid_leave' => true,
            ];
        }
        
        return $holidays;
    }

    /**
     * Calculate Lunar New Year dates (Tet) for a given year
     * Returns array of dates (Eve, Day 1, Day 2, Day 3, Day 4)
     */
    private function calculateLunarNewYear(int $year): array
    {
        // Approximate Lunar New Year dates (1st day of 1st lunar month)
        // This is a simplified calculation - for production use lunar calendar library
        $tetFirstDay = match($year) {
            2024 => '2024-02-10',
            2025 => '2025-01-29',
            2026 => '2026-02-17',
            2027 => '2027-02-06',
            2028 => '2028-01-26',
            2029 => '2029-02-13',
            2030 => '2030-02-03',
            default => null,
        };
        
        if (!$tetFirstDay) {
            return [];
        }
        
        $firstDay = \Carbon\Carbon::parse($tetFirstDay);
        
        return [
            $firstDay->copy()->subDay()->toDateString(), // Giao thừa
            $firstDay->toDateString(),                    // Mùng 1
            $firstDay->copy()->addDay()->toDateString(),  // Mùng 2
            $firstDay->copy()->addDays(2)->toDateString(), // Mùng 3
            $firstDay->copy()->addDays(3)->toDateString(), // Mùng 4
        ];
    }

    /**
     * Convert lunar date to solar date (simplified)
     */
    private function lunarToSolar(int $year, int $lunarMonth, int $lunarDay): ?string
    {
        // Simplified mapping for Hung Vuong (10/3 lunar)
        if ($lunarMonth === 3 && $lunarDay === 10) {
            $hungVuongSolar = match($year) {
                2024 => '2024-04-18',
                2025 => '2025-04-07',
                2026 => '2026-04-27',
                2027 => '2027-04-16',
                2028 => '2028-04-05',
                2029 => '2029-04-23',
                2030 => '2030-04-12',
                default => null,
            };
            
            return $hungVuongSolar;
        }
        
        return null;
    }
}
