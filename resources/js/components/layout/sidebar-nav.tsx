import { Link, usePage } from '@inertiajs/react';
import { NavIcon } from '@/components/layout/nav-icon';
import { cn } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

function isActive(href: string | null, currentUrl: string): boolean {
    if (href === null) {
        return false;
    }

    const path = new URL(href, 'http://localhost').pathname;

    return currentUrl === path || currentUrl.startsWith(`${path}/`);
}

function NavLink({ item, currentUrl }: { item: NavItem; currentUrl: string }) {
    const baseClasses =
        'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors';

    // Modules that are not built yet render as inert rows with a "Segera" badge, so
    // each role can see the shape of the app without hitting a dead link.
    if (!item.enabled || item.href === null) {
        return (
            <span
                className={cn(
                    baseClasses,
                    'cursor-not-allowed text-muted-foreground/60',
                )}
                aria-disabled="true"
            >
                <NavIcon name={item.icon} className="size-4 shrink-0" />
                <span className="truncate">{item.label}</span>
                <span className="ml-auto rounded-full border px-1.5 py-0.5 text-[10px] font-normal">
                    Segera
                </span>
            </span>
        );
    }

    const active = isActive(item.href, currentUrl);

    return (
        <Link
            href={item.href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                baseClasses,
                active
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground'
                    : 'text-sidebar-foreground hover:bg-sidebar-accent/60 hover:text-sidebar-accent-foreground',
            )}
        >
            <NavIcon name={item.icon} className="size-4 shrink-0" />
            <span className="truncate">{item.label}</span>
        </Link>
    );
}

export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
    const page = usePage();
    const groups: NavGroup[] = page.props.sidebar;

    return (
        // `flex-1 overflow-y-auto` is what makes the nav reachable: both callers pin
        // the panel to the viewport height, and the menu is taller than a short
        // laptop screen — without an internal scroller the bottom groups can't be
        // reached at all, since page scroll doesn't move a fixed panel.
        <nav
            className="flex flex-1 flex-col gap-4 overflow-y-auto px-3 py-4"
            onClick={onNavigate}
        >
            {groups.map((group, index) => (
                <div
                    key={group.title ?? `group-${index}`}
                    className="flex flex-col gap-1"
                >
                    {group.title ? (
                        <p className="px-3 py-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {group.title}
                        </p>
                    ) : null}

                    {group.items.map((item) => (
                        <NavLink
                            key={item.label}
                            item={item}
                            currentUrl={page.url}
                        />
                    ))}
                </div>
            ))}
        </nav>
    );
}
