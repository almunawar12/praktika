import { Link } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/hooks/use-auth';

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
}

export function UserMenu() {
    const { user, roleLabel } = useAuth();

    if (user === null) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-auto gap-2 px-2 py-1.5">
                    <Avatar className="size-7">
                        <AvatarFallback className="text-xs">
                            {initials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <span className="hidden text-left sm:flex sm:flex-col">
                        <span className="text-sm leading-tight font-medium">
                            {user.name}
                        </span>
                        <span className="text-xs leading-tight text-muted-foreground">
                            {roleLabel}
                        </span>
                    </span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuLabel className="flex flex-col gap-0.5">
                    <span className="text-sm font-medium">{user.name}</span>
                    <span className="text-xs font-normal text-muted-foreground">
                        {user.email}
                    </span>
                </DropdownMenuLabel>

                <DropdownMenuSeparator />

                <DropdownMenuItem asChild variant="destructive">
                    {/* POST, never GET: a GET logout can be triggered by any image tag
                        or prefetcher on the page. */}
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="w-full"
                    >
                        <LogOut className="size-4" aria-hidden="true" />
                        Keluar
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
