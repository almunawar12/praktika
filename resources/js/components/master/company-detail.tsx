import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type CompanyRecord = {
    id: string;
    name: string;
    business_field: string;
    address: string;
    pic_name: string;
    pic_phone: string;
    quota: number;
    is_active: boolean;
};

export function CompanyDetail({ record }: { record: CompanyRecord }) {
    return (
        <div className="grid gap-6">
            <section>
                <p className="mb-2 text-sm font-medium">Informasi Perusahaan</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Nama">{record.name}</DetailItem>
                    <DetailItem label="Bidang Usaha">
                        <Badge variant="outline">{record.business_field}</Badge>
                    </DetailItem>
                    <DetailItem label="Status">
                        {record.is_active ? (
                            <Badge variant="secondary">Aktif</Badge>
                        ) : (
                            <Badge variant="outline">Nonaktif</Badge>
                        )}
                    </DetailItem>
                    <DetailItem label="Kuota">{record.quota} siswa</DetailItem>
                    <div className="sm:col-span-2">
                        <DetailItem label="Alamat">
                            <span className="whitespace-pre-line">
                                {record.address}
                            </span>
                        </DetailItem>
                    </div>
                </dl>
            </section>

            <section>
                <p className="mb-2 text-sm font-medium">Kontak PIC</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Nama PIC">{record.pic_name}</DetailItem>
                    <DetailItem label="No HP PIC">
                        <a
                            href={`tel:${record.pic_phone}`}
                            className="underline-offset-4 hover:underline"
                        >
                            {record.pic_phone}
                        </a>
                    </DetailItem>
                </dl>
            </section>
        </div>
    );
}
