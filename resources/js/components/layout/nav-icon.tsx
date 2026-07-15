import {
    Award,
    Building2,
    CalendarCheck,
    CalendarRange,
    Circle,
    ClipboardCheck,
    FileBadge,
    FileSearch,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    LayoutDashboard,
    MapPin,
    Network,
    NotebookPen,
    School,
    ScrollText,
    Settings,
    ShieldCheck,
    Star,
    UserCheck,
    UserCog,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Explicit registry of every icon the server may name.
 *
 * A `import * as icons from 'lucide-react'` lookup would pull the entire icon set
 * into the bundle, so icons are listed here and tree-shaken normally.
 */
const icons: Record<string, LucideIcon> = {
    Award,
    Building2,
    CalendarCheck,
    CalendarRange,
    ClipboardCheck,
    FileBadge,
    FileSearch,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    LayoutDashboard,
    MapPin,
    Network,
    NotebookPen,
    School,
    ScrollText,
    Settings,
    ShieldCheck,
    Star,
    UserCheck,
    UserCog,
    UserRound,
    Users,
};

export function NavIcon({
    name,
    className,
}: {
    name: string;
    className?: string;
}) {
    // Falls back rather than crashing the page if the server names an icon that
    // has not been registered here yet.
    const Icon = icons[name] ?? Circle;

    return <Icon className={className} aria-hidden="true" />;
}
