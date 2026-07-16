import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type Props = {
    department: {
        id: string;
        code: string;
        name: string;
        school_classes_count: number;
        school_classes: { id: string; name: string }[];
    };
};

export default function DepartmentShow({ department }: Props) {
    const { can } = usePermission();

    return (
        <AppLayout title={department.name} description="Detail jurusan">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/master/departments">Kembali</Link>
                    </Button>
                    {can('departments.update') ? (
                        <Button asChild>
                            <Link
                                href={`/master/departments/${department.id}/edit`}
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Kode">
                                    <Badge variant="outline">
                                        {department.code}
                                    </Badge>
                                </DetailItem>
                                <DetailItem label="Nama">
                                    {department.name}
                                </DetailItem>
                                <DetailItem label="Jumlah Kelas">
                                    {department.school_classes_count}
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Kelas</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {department.school_classes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada kelas pada jurusan ini.
                                </p>
                            ) : (
                                <ul className="flex flex-wrap gap-2">
                                    {department.school_classes.map(
                                        (schoolClass) => (
                                            <li key={schoolClass.id}>
                                                <Link
                                                    href={`/master/classes/${schoolClass.id}`}
                                                >
                                                    <Badge variant="secondary">
                                                        {schoolClass.name}
                                                    </Badge>
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
