import { CheckCircle2 } from 'lucide-react';
import { Label, Pie, PieChart } from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import type { AttendanceToday } from '@/types/dashboard';

const chartConfig = {
    hadir: { label: 'Hadir', color: 'var(--status-hadir)' },
    izin: { label: 'Izin', color: 'var(--status-izin)' },
    sakit: { label: 'Sakit', color: 'var(--status-sakit)' },
    alpha: { label: 'Alpha', color: 'var(--status-alpha)' },
} satisfies ChartConfig;

export function AttendanceDonut({ data }: { data: AttendanceToday }) {
    const slices = (
        [
            { key: 'hadir', value: data.hadir },
            { key: 'izin', value: data.izin },
            { key: 'sakit', value: data.sakit },
            { key: 'alpha', value: data.alpha },
        ] as const
    ).map((slice) => ({
        ...slice,
        label: chartConfig[slice.key].label,
        fill: `var(--color-${slice.key})`,
        percentage:
            data.total > 0
                ? Math.round((slice.value / data.total) * 1000) / 10
                : 0,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>Kehadiran Hari Ini</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {data.total === 0 ? (
                    <p className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                        Tidak ada siswa PKL aktif hari ini.
                    </p>
                ) : (
                    <>
                        <ChartContainer
                            config={chartConfig}
                            className="mx-auto aspect-square max-h-52 w-full"
                        >
                            <PieChart>
                                <ChartTooltip
                                    content={<ChartTooltipContent hideLabel />}
                                />
                                <Pie
                                    data={slices.filter(
                                        (slice) => slice.value > 0,
                                    )}
                                    dataKey="value"
                                    nameKey="key"
                                    innerRadius={55}
                                    paddingAngle={2}
                                >
                                    <Label
                                        content={({ viewBox }) => {
                                            if (
                                                !viewBox ||
                                                !('cx' in viewBox) ||
                                                viewBox.cx === undefined ||
                                                viewBox.cy === undefined
                                            ) {
                                                return null;
                                            }

                                            return (
                                                <text
                                                    x={viewBox.cx}
                                                    y={viewBox.cy}
                                                    textAnchor="middle"
                                                    dominantBaseline="middle"
                                                >
                                                    <tspan
                                                        x={viewBox.cx}
                                                        y={viewBox.cy - 6}
                                                        className="fill-foreground text-2xl font-semibold"
                                                    >
                                                        {data.total}
                                                    </tspan>
                                                    <tspan
                                                        x={viewBox.cx}
                                                        y={viewBox.cy + 14}
                                                        className="fill-muted-foreground text-xs"
                                                    >
                                                        Total Siswa
                                                    </tspan>
                                                </text>
                                            );
                                        }}
                                    />
                                </Pie>
                            </PieChart>
                        </ChartContainer>

                        {/*
                         * The legend carries the count and share as text: two of the
                         * status hues sit below 3:1 on the light surface, so identity
                         * here can never rest on colour alone.
                         */}
                        <ul className="flex flex-col gap-2">
                            {slices.map((slice) => (
                                <li
                                    key={slice.key}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <span
                                        aria-hidden="true"
                                        className="size-2.5 rounded-full"
                                        style={{
                                            backgroundColor: `var(--status-${slice.key})`,
                                        }}
                                    />
                                    <span>{slice.label}</span>
                                    <span className="ml-auto tabular-nums">
                                        {slice.value}{' '}
                                        <span className="text-muted-foreground">
                                            ({slice.percentage}%)
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <div className="flex items-center gap-2 rounded-lg bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                            <CheckCircle2
                                className="size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>Tingkat kehadiran hari ini</span>
                            <span className="ml-auto font-semibold tabular-nums">
                                {data.rate}%
                            </span>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
