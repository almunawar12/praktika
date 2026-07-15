/**
 * Mirrors App\Support\SidebarBuilder.
 *
 * `href` is null and `enabled` false for modules that are not built yet — the item
 * is rendered as a disabled placeholder rather than a link that would 404.
 */
export type NavItem = {
    label: string;
    /** Lucide icon name, resolved at render time. */
    icon: string;
    permission: string;
    href: string | null;
    enabled: boolean;
};

export type NavGroup = {
    title: string | null;
    items: NavItem[];
};
