<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            // 2026
            [
                'holiday_date' => '2026-01-01',
                'name' => 'Tết Dương lịch',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
            ],
            [
                'holiday_date' => '2026-02-16',
                'name' => 'Giao thừa Tết Nguyên Đán',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
                'notes' => 'Tết Nguyên Đán 2026',
            ],
            [
                'holiday_date' => '2026-02-17',
                'name' => 'Mùng 1 Tết',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
                'notes' => 'Tết Nguyên Đán 2026',
            ],
            [
                'holiday_date' => '2026-02-18',
                'name' => 'Mùng 2 Tết',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
                'notes' => 'Tết Nguyên Đán 2026',
            ],
            [
                'holiday_date' => '2026-02-19',
                'name' => 'Mùng 3 Tết',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
                'notes' => 'Tết Nguyên Đán 2026',
            ],
            [
                'holiday_date' => '2026-04-27',
                'name' => 'Giỗ Tổ Hùng Vương',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
            ],
            [
                'holiday_date' => '2026-04-30',
                'name' => 'Ngày Giải phóng miền Nam',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
            ],
            [
                'holiday_date' => '2026-05-01',
                'name' => 'Ngày Quốc tế Lao động',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
            ],
            [
                'holiday_date' => '2026-09-02',
                'name' => 'Ngày Quốc khánh',
                'multiplier' => 2,
                'paid_leave' => true,
                'status' => 'active',
            ],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                ['holiday_date' => $holiday['holiday_date']],
                $holiday
            );
        }

        $this->command->info('Đã tạo ' . count($holidays) . ' ngày lễ năm 2026');
    }
}
