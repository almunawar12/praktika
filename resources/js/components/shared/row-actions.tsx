import { Link } from '@inertiajs/react';
import { Eye, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type RowActionsProps = {
    showHref: string;
    editHref: string;
    deleteHref: string;
    deleteDescription: string;
    canUpdate: boolean;
    canDelete: boolean;
};

export function RowActions({
    showHref,
    editHref,
    deleteHref,
    deleteDescription,
    canUpdate,
    canDelete,
}: RowActionsProps) {
    const [confirming, setConfirming] = useState(false);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="size-8">
                        <MoreHorizontal className="size-4" aria-hidden="true" />
                        <span className="sr-only">Aksi</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link href={showHref}>
                            <Eye className="size-4" aria-hidden="true" />
                            Detail
                        </Link>
                    </DropdownMenuItem>

                    {canUpdate ? (
                        <DropdownMenuItem asChild>
                            <Link href={editHref}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </DropdownMenuItem>
                    ) : null}

                    {canDelete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={(event) => {
                                    // Let the menu close first; opening the dialog from
                                    // inside the closing menu traps focus.
                                    event.preventDefault();
                                    setConfirming(true);
                                }}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Hapus
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>

            <DeleteDialog
                open={confirming}
                onOpenChange={setConfirming}
                action={deleteHref}
                description={deleteDescription}
            />
        </>
    );
}
