import { Head, usePage } from '@inertiajs/react';
import { GraduationCap, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { PropsWithChildren } from 'react';
import { toast } from 'sonner';
import { SidebarNav } from '@/components/layout/sidebar-nav';
import { UserMenu } from '@/components/layout/user-menu';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';

type AppLayoutProps = PropsWithChildren<{
    title: string;
    description?: string;
}>;

function Brand() {
    return (
        <div className="flex items-center gap-2 px-4 py-4 font-semibold">
            <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <GraduationCap className="size-5" aria-hidden="true" />
            </span>
            Praktika
        </div>
    );
}

export default function AppLayout({
    title,
    description,
    children,
}: AppLayoutProps) {
    const [mobileOpen, setMobileOpen] = useState(false);
    const flash = usePage().props.flash;

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }

        if (flash.status) {
            toast.info(flash.status);
        }
    }, [flash]);

    return (
        <div className="min-h-svh bg-background">
            <Head title={title} />

            {/* Mobile-first: the sidebar is a sheet below `lg` and a fixed rail above it. */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r bg-sidebar lg:block">
                <Brand />
                <SidebarNav />
            </aside>

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-14 items-center gap-2 border-b bg-background/95 px-4 backdrop-blur">
                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="lg:hidden"
                            >
                                <Menu className="size-5" aria-hidden="true" />
                                <span className="sr-only">
                                    Buka menu navigasi
                                </span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            className="w-64 bg-sidebar p-0"
                        >
                            <SheetTitle className="sr-only">
                                Navigasi
                            </SheetTitle>
                            <Brand />
                            <SidebarNav
                                onNavigate={() => setMobileOpen(false)}
                            />
                        </SheetContent>
                    </Sheet>

                    <div className="ml-auto">
                        <UserMenu />
                    </div>
                </header>

                <main className="p-4 md:p-6">
                    <div className="mb-6 flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    {children}
                </main>
            </div>

            <Toaster />
        </div>
    );
}
