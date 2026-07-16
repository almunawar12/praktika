import { UserForm } from '@/components/system/user-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

export default function UserCreate({ roles }: { roles: SelectOption[] }) {
    return (
        <AppLayout title="Tambah Pengguna" description="Buat akun staf baru">
            <UserForm
                action="/system/users"
                method="post"
                roles={roles}
                showRole
                showPassword
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
