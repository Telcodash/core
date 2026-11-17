import { Form, Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
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
import { show } from '@/routes/teams';
import { index } from '@/routes/users';
import type { Role } from '@/types';

type UserRow = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    team: { id: number; name: string; slug: string } | null;
    is_self: boolean;
};

type Props = {
    users: {
        data: UserRow[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    search: string;
    roles: { value: Role; label: string }[];
    teams: { id: number; name: string }[];
};

const NEW_TEAM = '__new__';

export default function Users({ users, search, roles, teams }: Props) {
    const [query, setQuery] = useState(search);
    // `null` = closed, `'new'` = creating, UserRow = editing
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);
    const [deleting, setDeleting] = useState<UserRow | null>(null);

    const submit = (value: string) =>
        router.get(index.url(), value ? { q: value } : {}, {
            preserveState: true,
            replace: true,
        });

    return (
        <>
            <Head title="Manage Users" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Manage users"
                        description="Search by name, email or team name"
                    />
                    <Button
                        onClick={() => setEditing('new')}
                        data-test="new-user-button"
                    >
                        <Plus /> New user
                    </Button>
                </div>

                <form
                    role="search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit(query.trim());
                    }}
                    className="flex max-w-xl gap-2"
                >
                    <div className="relative flex-1">
                        <Label htmlFor="user-search" className="sr-only">
                            Search users
                        </Label>
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            id="user-search"
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Name, email or team"
                            className="pl-9"
                            maxLength={100}
                            autoComplete="off"
                        />
                    </div>
                    <Button type="submit">Search</Button>
                    {search && (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => {
                                setQuery('');
                                submit('');
                            }}
                        >
                            <X /> Clear
                        </Button>
                    )}
                </form>

                <p className="text-sm text-muted-foreground" aria-live="polite">
                    {users.total} {users.total === 1 ? 'user' : 'users'}
                    {search && ` matching “${search}”`}
                </p>

                <div className="overflow-x-auto rounded-xl glass">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Users</caption>
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
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Team
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right font-medium"
                                >
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No users match your search.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b last:border-0"
                                    data-test="user-row"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {user.name}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {user.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {user.role_label}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {user.team && (
                                            <Link
                                                href={show(user.team.slug)}
                                                className="hover:underline"
                                            >
                                                {user.team.name}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-11 md:size-9"
                                                aria-label={`Edit ${user.name}`}
                                                onClick={() => setEditing(user)}
                                            >
                                                <Pencil />
                                            </Button>
                                            {!user.is_self && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-11 text-destructive hover:text-destructive md:size-9"
                                                    aria-label={`Delete ${user.name}`}
                                                    onClick={() =>
                                                        setDeleting(user)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.links.length > 3 && (
                    <nav
                        className="flex flex-wrap gap-1"
                        aria-label="Pagination"
                    >
                        {users.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    preserveState
                                    className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="rounded-md border px-3 py-1.5 text-sm text-muted-foreground opacity-50"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </div>

            <UserDialog
                key={
                    editing === null
                        ? 'closed'
                        : editing === 'new'
                          ? 'new'
                          : editing.id
                }
                user={editing === 'new' ? null : editing}
                open={editing !== null}
                roles={roles}
                teams={teams}
                onClose={() => setEditing(null)}
            />
            <DeleteDialog user={deleting} onClose={() => setDeleting(null)} />
        </>
    );
}

function UserDialog({
    user,
    open,
    roles,
    teams,
    onClose,
}: {
    user: UserRow | null;
    open: boolean;
    roles: Props['roles'];
    teams: Props['teams'];
    onClose: () => void;
}) {
    const form = user
        ? UserController.update.form(user.id)
        : UserController.store.form();
    const [teamChoice, setTeamChoice] = useState(
        user?.team ? String(user.team.id) : '',
    );
    const [role, setRole] = useState<string>(user?.role ?? 'advisor');
    const creatingTeam = !user && teamChoice === NEW_TEAM;
    const isSelf = user?.is_self ?? false;

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogTitle>{user ? 'Edit user' : 'New user'}</DialogTitle>
                <DialogDescription>
                    {user
                        ? 'Update this user. Leave the password blank to keep it unchanged.'
                        : 'Create a user and place them on a team.'}
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
                                <Label htmlFor="user-name">Name</Label>
                                <Input
                                    id="user-name"
                                    name="name"
                                    defaultValue={user?.name}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-email">Email</Label>
                                <Input
                                    id="user-email"
                                    name="email"
                                    type="email"
                                    defaultValue={user?.email}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-role">Role</Label>
                                <Select
                                    name="role"
                                    value={role}
                                    onValueChange={setRole}
                                    disabled={isSelf}
                                >
                                    <SelectTrigger id="user-role">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {isSelf && (
                                    <input
                                        type="hidden"
                                        name="role"
                                        value={role}
                                    />
                                )}
                                <InputError message={errors.role} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-team">Team</Label>
                                <Select
                                    value={teamChoice}
                                    onValueChange={setTeamChoice}
                                >
                                    <SelectTrigger id="user-team">
                                        <SelectValue placeholder="Select a team" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {!user && (
                                            <SelectItem value={NEW_TEAM}>
                                                + New team…
                                            </SelectItem>
                                        )}
                                        {teams.map((team) => (
                                            <SelectItem
                                                key={team.id}
                                                value={String(team.id)}
                                            >
                                                {team.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {!creatingTeam && teamChoice && (
                                    <input
                                        type="hidden"
                                        name="team_id"
                                        value={teamChoice}
                                    />
                                )}
                                <InputError message={errors.team_id} />
                            </div>
                            {creatingTeam && (
                                <div className="grid gap-2">
                                    <Label htmlFor="new-team-name">
                                        New team name
                                    </Label>
                                    <Input
                                        id="new-team-name"
                                        name="new_team_name"
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Only a manager or administrator can
                                        start a new team.
                                    </p>
                                    <InputError
                                        message={errors.new_team_name}
                                    />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="user-password">Password</Label>
                                <PasswordInput
                                    id="user-password"
                                    name="password"
                                    required={!user}
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {user ? 'Save changes' : 'Create user'}
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
    user,
    onClose,
}: {
    user: UserRow | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={user !== null}
            onOpenChange={(next) => !next && onClose()}
        >
            <DialogContent>
                <DialogTitle>Delete {user?.name}?</DialogTitle>
                <DialogDescription>
                    This permanently deletes the user's account. A team must
                    always keep at least one manager.
                </DialogDescription>
                {user && (
                    <Form
                        {...UserController.destroy.form(user.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                    >
                        {({ processing, errors }) => (
                            <>
                                <InputError message={errors.user} />
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

Users.layout = {
    breadcrumbs: [
        { title: 'Administration', href: index.url() },
        { title: 'Users', href: index.url() },
    ],
};
