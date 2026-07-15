# CLAUDE.md

## Project Overview

Project Name: Praktika

Praktika adalah aplikasi monitoring Praktik Kerja Lapangan (PKL) untuk Sekolah Menengah Kejuruan (SMK).

Tujuan sistem adalah membantu sekolah memonitor aktivitas siswa selama PKL mulai dari penempatan, absensi, jurnal harian, monitoring guru hingga penilaian.

---

# Tech Stack

Backend:

- Laravel 13 (terpasang: 13.20)
- PHP 8.3 (terpasang: 8.3.31; `composer.json` di-pin `^8.3`)
- PostgreSQL (terpasang: 17.2)

Frontend:

- Inertia.js
- React 19
- TypeScript

UI:

- Tailwind CSS v4
- Shadcn UI
- Lucide React

Authentication:

- Laravel Sanctum

Authorization:

- Spatie Laravel Permission

Storage:

- Local Storage
- S3 Compatible Storage

Queue:

- Database Queue

---

# User Roles

1. Super Admin
2. Admin PKL
3. Guru Pembimbing
4. Pembimbing Industri
5. Siswa

Always use Role-Based Access Control.

---

# Development Principles

## Clean Architecture

Controllers should remain thin.

Business logic belongs in:

- Services
- Actions
- Repositories

Avoid placing business logic inside controllers.

---

## Validation

Always use Form Request validation.

Example:

StoreStudentRequest
UpdateStudentRequest

---

## Database

Use:

- UUID Primary Keys
- Foreign Key Constraints
- Soft Deletes where appropriate

All migrations must be reversible.

---

## Coding Standards

Follow:

- PSR-12
- Laravel Pint

Use strict typing whenever possible.

---

# Folder Structure

app/

- Actions/
- Services/
- Repositories/
- Http/
- Models/
- Policies/

resources/js/

- pages/
- components/
- layouts/
- hooks/
- types/

---

# UI Guidelines

Use:

- Shadcn UI
- Tailwind CSS

Requirements:

- Mobile First
- Responsive
- Accessible

Avoid:

- Custom CSS if Tailwind can solve it
- Inline styles

---

# Main Modules

## Authentication

- Login
- Logout
- Forgot Password

Tidak ada registrasi publik. Akun dibuat oleh Admin (PRD § 3).

---

## Dashboard

- Statistics
- Recent Activities

---

## Students

CRUD siswa.

---

## Teachers

CRUD guru pembimbing.

---

## Companies

CRUD perusahaan.

---

## Placements

Penempatan siswa ke perusahaan.

---

## Attendance

Check In
Check Out

Store:

- timestamp
- latitude
- longitude
- photo

---

## Journals

Daily student activity reports.

Status:

- pending
- approved
- rejected

---

## Monitoring

Teacher visit reports.

---

## Assessments

Industrial score.
Teacher score.

Calculate final score automatically.

---

# Database Naming Convention

Tables:

plural snake_case

Examples:

students
teachers
companies
placements
attendances
journals

Columns:

snake_case

Examples:

student_id
teacher_id
created_at

---

# API Standards

Return consistent JSON responses.

Success:

{
"success": true,
"message": "Success",
"data": {}
}

Error:

{
"success": false,
"message": "Validation Error",
"errors": {}
}

---

# Security

Always:

- Authorize every action
- Validate every request
- Escape user content
- Use Policies

Never trust client-side validation.

---

# Performance

Use eager loading.

Avoid N+1 queries.

Use pagination for all data tables.

Default pagination:

15 items per page.

---

# Testing

Create:

- Feature Tests
- Unit Tests

Coverage priority:

1. Authentication
2. Attendance
3. Journal
4. Assessment
5. Placement

---

# Definition of Done

A feature is complete when:

- Migration exists
- Model exists
- Request validation exists
- Policy exists
- Controller exists
- UI exists
- Tests exist
- Permission exists
- Audit log exists

No feature is considered complete without tests.
