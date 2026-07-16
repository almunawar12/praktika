import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type Props = {
    schoolClass: {
        id: string;
        name: string;
        students_count: number;
        department: { id: string; code: string; name: string };
    };
};

export default function ClassShow({ schoolClass }: Props) {
    const { can } = usePermission();

    return (
        <AppLayout title={schoolClass.name} description="Detail kelas">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/master/classes">Kembali</Link>
                    </Button>
                    {can('school-classes.update') ? (
                        <Button asChild>
                            <Link
                                href={`/master/classes/${schoolClass.id}/edit`}
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Informasi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <DetailItem label="Nama Kelas">
                                {schoolClass.name}
                            </DetailItem>
                            <DetailItem label="Jurusan">
                                <Link
                                    href={`/master/departments/${schoolClass.department.id}`}
                                >
                                    <Badge variant="outline">
                                        {schoolClass.department.code} —{' '}
                                        {schoolClass.department.name}
                                    </Badge>
                                </Link>
                            </DetailItem>
                            <DetailItem label="Jumlah Siswa">
                                <Link
                                    href={`/master/students?class_id=${schoolClass.id}`}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {schoolClass.students_count} siswa
                                </Link>
                            </DetailItem>
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
