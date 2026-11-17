import { Form } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { Role } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    editable: boolean;
};

export type TeamMemberUrls = {
    store: string;
    update: (memberId: number) => string;
    destroy: (memberId: number) => string;
};

type Props = {
    title: string;
    members: Member[];
    roles: { value: Role; label: string }[];
    canManage: boolean;
    urls: TeamMemberUrls;
    actions?: ReactNode;
};

/** Member table plus add / edit / delete dialogs, shared by "My Team" and the admin team page. */
export function TeamMembers({
    title,
    members,
    roles,
    canManage,
    urls,
    actions,
}: Props) {
    // `null` = closed, `'new'` = adding, Member = editing
    const [editing, setEditing] = useState<Member | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Member | null>(null);
    // Administrators manage everyone; managers may edit some members.
    const canEdit = members.some((member) => member.editable);

    return (
        <>
            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description={`${members.length} ${members.length === 1 ? 'member' : 'members'}`}
                    />
                    <div className="flex shrink-0 gap-2">
                        {actions}
                        {canManage && (
                            <Button
                                onClick={() => setEditing('new')}
                                data-test="add-member-button"
                            >
                                <Plus /> Add member
                            </Button>
                        )}
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl glass">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Members of {title}
                        </caption>
                        <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Name
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Email
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Role
                                </th>
                                {canEdit && (
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-medium"
                                    >
                                        <span className="sr-only">Actions</span>
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {members.map((member) => (
                                <tr
                                    key={member.id}
                                    className="border-b last:border-0"
                                    data-test="team-member-row"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {member.name}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {member.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {member.role_label}
                                        </Badge>
                                    </td>
                                    {canEdit && (
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                {member.editable && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-11 md:size-9"
                                                        aria-label={`Edit ${member.name}`}
                                                        onClick={() =>
                                                            setEditing(member)
                                                        }
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                )}
                                                {canManage && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-11 text-destructive hover:text-destructive md:size-9"
                                                        aria-label={`Delete ${member.name}`}
                                                        onClick={() =>
                                                            setDeleting(member)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {canEdit && (
                <>
                    <MemberDialog
                        key={
                            editing === null
                                ? 'closed'
                                : editing === 'new'
                                  ? 'new'
                                  : editing.id
                        }
                        urls={urls}
                        roles={roles}
                        canManage={canManage}
                        member={editing === 'new' ? null : editing}
                        open={editing !== null}
                        onClose={() => setEditing(null)}
                    />
                    {canManage && (
                        <DeleteDialog
                            urls={urls}
                            member={deleting}
                            onClose={() => setDeleting(null)}
                        />
                    )}
                </>
            )}
        </>
    );
}

function MemberDialog({
    urls,
    roles,
    canManage,
    member,
    open,
    onClose,
}: {
    urls: TeamMemberUrls;
    roles: Props['roles'];
    canManage: boolean;
    member: Member | null;
    open: boolean;
    onClose: () => void;
}) {
    const form = member
        ? { action: urls.update(member.id), method: 'patch' as const }
        : { action: urls.store, method: 'post' as const };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogTitle>
                    {member ? 'Edit member' : 'Add member'}
                </DialogTitle>
                <DialogDescription>
                    {member
                        ? canManage
                            ? 'Update this member. Leave the password blank to keep it unchanged.'
                            : 'Update this member. You can switch them between Advisor and Advisor Plus.'
                        : 'Create a user on this team.'}
                </DialogDescription>

                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={member?.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={member?.email}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="role">Role</Label>
                                <Select
                                    name="role"
                                    defaultValue={member?.role ?? 'advisor'}
                                >
                                    <SelectTrigger id="role">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.role} />
                            </div>
                            {canManage && (
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required={!member}
                                        autoComplete="new-password"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                            )}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {member ? 'Save changes' : 'Add member'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({
    urls,
    member,
    onClose,
}: {
    urls: TeamMemberUrls;
    member: Member | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={member !== null}
            onOpenChange={(next) => !next && onClose()}
        >
            <DialogContent>
                <DialogTitle>Delete {member?.name}?</DialogTitle>
                <DialogDescription>
                    This permanently deletes the user's account. A team must
                    always keep at least one manager.
                </DialogDescription>
                {member && (
                    <Form
                        action={urls.destroy(member.id)}
                        method="delete"
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                    >
                        {({ processing, errors }) => (
                            <>
                                <InputError message={errors.member} />
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Delete
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
