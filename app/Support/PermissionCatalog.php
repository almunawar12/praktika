<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleName;

/**
 * Single source of truth for the permission catalogue and the role → permission map.
 *
 * Mirrors the Architecture Design Document § 4.2. Modules that are not built yet
 * (attendance, journal, ...) already have their permissions declared here so that
 * seeding stays a one-time concern — the modules only have to consume them.
 */
final class PermissionCatalog
{
    /**
     * resource => list of actions.
     *
     * @return array<string, array<int, string>>
     */
    public static function resources(): array
    {
        return [
            'dashboard' => ['view'],

            // Master data.
            'academic-years' => ['viewAny', 'view', 'create', 'update', 'delete', 'activate'],
            'departments' => ['viewAny', 'view', 'create', 'update', 'delete'],
            'school-classes' => ['viewAny', 'view', 'create', 'update', 'delete'],
            'students' => ['viewAny', 'view', 'create', 'update', 'delete', 'import', 'export'],
            'teachers' => ['viewAny', 'view', 'create', 'update', 'delete', 'import'],
            'companies' => ['viewAny', 'view', 'create', 'update', 'delete'],
            'company-supervisors' => ['viewAny', 'view', 'create', 'update', 'delete'],

            // PKL transactions.
            'placements' => ['viewAny', 'view', 'create', 'update', 'delete', 'activate', 'complete', 'cancel'],
            'attendances' => ['viewAny', 'view', 'checkIn', 'checkOut', 'verify', 'update', 'delete'],
            'journals' => ['viewAny', 'view', 'create', 'update', 'delete', 'reviewAsIndustry', 'reviewAsTeacher'],
            'monitorings' => ['viewAny', 'view', 'create', 'update', 'delete'],
            'assessments' => ['viewAny', 'view', 'submitAsIndustry', 'submitAsTeacher'],
            'final-scores' => ['view', 'calculate', 'publish', 'unpublish'],
            'attachments' => ['create', 'delete'],

            // Reports.
            'reports' => ['attendance', 'journal', 'assessment', 'monitoring'],

            // System.
            'users' => ['viewAny', 'view', 'create', 'update', 'delete', 'resetPassword', 'impersonate', 'toggleStatus'],
            'roles' => ['viewAny', 'view', 'create', 'update', 'delete', 'assign'],
            'settings' => ['view', 'update'],
            'activity-log' => ['viewAny', 'view'],
        ];
    }

    /**
     * Every permission name in `{resource}.{action}` form.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::resources() as $resource => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return $permissions;
    }

    /**
     * Permissions granted to each role.
     *
     * Super Admin receives the whole catalogue explicitly — Praktika has no
     * `Gate::before()` bypass (see AppServiceProvider for why).
     *
     * @return array<int, string>
     */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => self::all(),
            RoleName::AdminPkl => self::adminPkl(),
            RoleName::GuruPembimbing => self::guruPembimbing(),
            RoleName::PembimbingIndustri => self::pembimbingIndustri(),
            RoleName::Siswa => self::siswa(),
        };
    }

    /**
     * @return array<int, string>
     */
    private static function adminPkl(): array
    {
        return [
            'dashboard.view',

            'academic-years.viewAny', 'academic-years.view',

            ...self::crud('departments'),
            ...self::crud('school-classes'),
            ...self::crud('students'), 'students.import', 'students.export',
            ...self::crud('teachers'), 'teachers.import',
            ...self::crud('companies'),
            ...self::crud('company-supervisors'),

            ...self::crud('placements'),
            'placements.activate', 'placements.complete', 'placements.cancel',

            'attendances.viewAny', 'attendances.view', 'attendances.verify',
            'attendances.update', 'attendances.delete',

            'journals.viewAny', 'journals.view',

            'monitorings.viewAny', 'monitorings.view',

            'assessments.viewAny', 'assessments.view',

            'final-scores.view', 'final-scores.calculate',
            'final-scores.publish', 'final-scores.unpublish',

            'attachments.create', 'attachments.delete',

            'reports.attendance', 'reports.journal', 'reports.assessment', 'reports.monitoring',

            'users.resetPassword',
            'settings.view',
            'activity-log.viewAny', 'activity-log.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function guruPembimbing(): array
    {
        return [
            'dashboard.view',

            'departments.viewAny', 'departments.view',
            'school-classes.viewAny', 'school-classes.view',
            'students.viewAny', 'students.view',
            'teachers.viewAny', 'teachers.view',
            'companies.viewAny', 'companies.view',

            'placements.viewAny', 'placements.view',

            'attendances.viewAny', 'attendances.view', 'attendances.verify',

            'journals.viewAny', 'journals.view', 'journals.reviewAsTeacher',

            ...self::crud('monitorings'),

            'assessments.viewAny', 'assessments.view', 'assessments.submitAsTeacher',

            'final-scores.view',

            'attachments.create', 'attachments.delete',

            'reports.attendance', 'reports.journal', 'reports.assessment', 'reports.monitoring',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function pembimbingIndustri(): array
    {
        return [
            'dashboard.view',

            'students.viewAny', 'students.view',
            'companies.viewAny', 'companies.view',

            'placements.viewAny', 'placements.view',

            'attendances.viewAny', 'attendances.view', 'attendances.verify',

            'journals.viewAny', 'journals.view', 'journals.reviewAsIndustry',

            'assessments.viewAny', 'assessments.view', 'assessments.submitAsIndustry',

            'final-scores.view',

            'reports.attendance', 'reports.journal',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function siswa(): array
    {
        return [
            'dashboard.view',

            'companies.viewAny', 'companies.view',

            'placements.viewAny', 'placements.view',

            'attendances.viewAny', 'attendances.view',
            'attendances.checkIn', 'attendances.checkOut',

            ...self::crud('journals'),

            'monitorings.viewAny', 'monitorings.view',

            'final-scores.view',

            'attachments.create', 'attachments.delete',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function crud(string $resource): array
    {
        return [
            "{$resource}.viewAny",
            "{$resource}.view",
            "{$resource}.create",
            "{$resource}.update",
            "{$resource}.delete",
        ];
    }
}
