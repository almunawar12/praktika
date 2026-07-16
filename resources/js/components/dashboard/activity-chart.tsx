import { useMemo, useState } from 'react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ActivityChartPoint } from '@/types/dashboard';

const chartConfig = {
    absensi: { label: 'Absensi', color: 'var(--chart-1)' },
    jurnal: { label: 'Jurnal', color: 'var(--chart-2)' },
    monitoring: { label: 'Monitoring', color: 'var(--chart-3)' },
    penilaian: { label: 'Penilaian', color: 'var(--chart-4)' },
} satisfies ChartConfig;

const series = Object.keys(chartConfig) as (keyof typeof chartConfig)[];

const ranges = [
    { value: '7', label: '7 Hari Terakhir' },
    { value: '14', label: '14 Hari Terakhir' },
    { value: '30', label: '30 Hari Terakhir' },
];

function formatDay(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(iso));
}

export function ActivityChart({ data }: { data: ActivityChartPoint[] }) {
    const [range, setRange] = useState('7');

    const visible = useMemo(() => data.slice(-Number(range)), [data, range]);

    const isEmpty = visible.every((point) =>
        series.every((key) => point[key] === 0),
    );

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle>Grafik Aktivitas PKL</CardTitle>
                <Select value={range} onValueChange={setRange}>
                    <SelectTrigger className="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {ranges.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </CardHeader>
            <CardContent>
                {isEmpty ? (
                    <p className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                        Belum ada aktivitas pada rentang ini.
                    </p>
                ) : (
                    <ChartContainer config={chartConfig} className="h-72 w-full">
                        <LineChart data={visible} accessibilityLayer>
                            <CartesianGrid vertical={false} />
                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                tickFormatter={formatDay}
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                width={32}
                                allowDecimals={false}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            formatDay(String(value))
                                        }
                                    />
                                }
                            />
                            <ChartLegend content={<ChartLegendContent />} />
                            {series.map((key) => (
                                <Line
                                    key={key}
                                    dataKey={key}
                                    type="monotone"
                                    stroke={`var(--color-${key})`}
                                    strokeWidth={2}
                                    dot={false}
                                />
                            ))}
                        </LineChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
