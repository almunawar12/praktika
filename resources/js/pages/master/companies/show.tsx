import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type Props = {
    company: {
        id: string;
        name: string;
        business_field: string;
        address: string;
        pic_name: string;
        pic_phone: string;
        quota: number;
        is_active: boolean;
    };
};

export default function CompanyShow({ company }: Props) {
    const { can } = usePermission();

    return (
        <AppLayout title={company.name} description="Detail perusahaan">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/master/companies">Kembali</Link>
                    </Button>
                    {can('companies.update') ? (
                        <Button asChild>
                            <Link href={`/master/companies/${company.id}/edit`}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi Perusahaan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Nama">
                                    {company.name}
                                </DetailItem>
                                <DetailItem label="Bidang Usaha">
                                    <Badge variant="outline">
                                        {company.business_field}
                                    </Badge>
                                </DetailItem>
                                <DetailItem label="Status">
                                    {company.is_active ? (
                                        <Badge variant="secondary">Aktif</Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Nonaktif
                                        </Badge>
                                    )}
                                </DetailItem>
                                <DetailItem label="Kuota">
                                    {company.quota} siswa
                                </DetailItem>
                                <div className="sm:col-span-2">
                                    <DetailItem label="Alamat">
                                        <span className="whitespace-pre-line">
                                            {company.address}
                                        </span>
                                    </DetailItem>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Kontak PIC</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Nama PIC">
                                    {company.pic_name}
                                </DetailItem>
                                <DetailItem label="No HP PIC">
                                    <a
                                        href={`tel:${company.pic_phone}`}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {company.pic_phone}
                                    </a>
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
