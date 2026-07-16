<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

/**
 * Demo master data for local development.
 *
 * Guarded against production by DatabaseSeeder: this invents people and companies,
 * which must never appear in a real school's database.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $departments = collect([
            ['code' => 'RPL', 'name' => 'Rekayasa Perangkat Lunak'],
            ['code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan'],
            ['code' => 'MM', 'name' => 'Multimedia'],
            ['code' => 'AKL', 'name' => 'Akuntansi dan Keuangan Lembaga'],
        ])->map(fn (array $attributes): Department => Department::firstOrCreate(
            ['code' => $attributes['code']],
            ['name' => $attributes['name']],
        ));

        $classes = $departments->flatMap(
            fn (Department $department) => collect(['XI A', 'XI B', 'XII A', 'XII B'])
                ->map(fn (string $name): SchoolClass => SchoolClass::firstOrCreate(
                    ['department_id' => $department->id, 'name' => $name],
                ))
        );

        Teacher::factory()->count(8)->create();

        Company::factory()->count(12)->create();
        Company::factory()->count(2)->inactive()->create();

        // Spread students across every class so the class/department filters have
        // something to actually filter.
        $classes->each(fn (SchoolClass $class) => Student::factory()
            ->count(3)
            ->create(['class_id' => $class->id]));

        $this->command->info(sprintf(
            'Master data: %d jurusan, %d kelas, %d guru, %d perusahaan, %d siswa.',
            Department::count(),
            SchoolClass::count(),
            Teacher::count(),
            Company::count(),
            Student::count(),
        ));
    }
}
