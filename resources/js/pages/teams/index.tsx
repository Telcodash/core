import { Head, Link, router } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import { Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminTeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/teams';

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    matched: boolean;
};

type TeamResult = {
    id: number;
    name: string;
    slug: string;
    members: Member[];
};

type Props = {
    teams: {
        data: TeamResult[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    search: string;
};

export default function Teams({ teams, search }: Props) {
    const [query, setQuery] = useState(search);
    const [creating, setCreating] = useState(false);

    const submit = (value: string) =>
        router.get(index.url(), value ? { q: value } : {}, {
            preserveState: true,
            replace: true,
        });

    return (
        <>
            <Head title="Manage Teams" />

            <div className="space-y-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Manage teams"
                        description="Search by team name, or by a member's name or email"
                    />
                    <Button
                        onClick={() => setCreating(true)}
                        data-test="new-team-button"
                    >
                        <Plus /> New team
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
                        <Label htmlFor="team-search" className="sr-only">
                            Search teams
                        </Label>
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden
                        />
                        <Input
                            id="team-search"
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Team name, member name or email"
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
                    {teams.total} {teams.total === 1 ? 'team' : 'teams'}
                    {search && ` matching “${search}”`}
                </p>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <caption className="sr-only">Teams</caption>
                        <thead className="border-b bg-muted/40 text-left text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Team
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Members
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    Managers
                                </th>
                                {search && (
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Matching members
                                    </th>
                                )}
                                <th
                                    scope="col"
                                    className="px-4 py-3 text-right font-medium"
                                >
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {teams.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={search ? 5 : 4}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No teams match your search. Try a
                                        different name or email.
                                    </td>
                                </tr>
                            )}
                            {teams.data.map((team) => (
                                <tr
                                    key={team.id}
                                    className="border-b last:border-0"
                                    data-test="team-result"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={show(team.slug)}
                                            className="hover:underline"
                                        >
                                            {team.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {team.members.length}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {team.members
                                            .filter((member) =>
                                                [
                                                    'manager',
                                                    'administrator',
                                                ].includes(member.role),
                                            )
                                            .map((member) => member.name)
                                            .join(', ')}
                                    </td>
                                    {search && (
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {team.members
                                                    .filter(
                                                        (member) =>
                                                            member.matched,
                                                    )
                                                    .map((member) => (
                                                        <Badge
                                                            key={member.id}
                                                            variant="secondary"
                                                            title={member.name}
                                                        >
                                                            {member.email}
                                                        </Badge>
                                                    ))}
                                            </div>
                                        </td>
                                    )}
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-11 md:h-8"
                                                asChild
                                            >
                                                <Link
                                                    href={show(team.slug)}
                                                    aria-label={`Manage ${team.name}`}
                                                >
                                                    Manage
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {teams.links.length > 3 && (
                    <nav
                        className="flex flex-wrap gap-1"
                        aria-label="Pagination"
                    >
                        {teams.links.map((link, i) =>
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

            <NewTeamDialog open={creating} onClose={() => setCreating(false)} />
        </>
    );
}

function NewTeamDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogTitle>New team</DialogTitle>
                <DialogDescription>
                    Every team needs a manager, so create the team's first
                    manager now. You can add more members afterwards.
                </DialogDescription>
                <Form
                    {...AdminTeamController.store.form()}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="new-team-name">Team name</Label>
                                <Input
                                    id="new-team-name"
                                    name="name"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="manager-name">
                                    Manager name
                                </Label>
                                <Input
                                    id="manager-name"
                                    name="manager_name"
                                    required
                                />
                                <InputError message={errors.manager_name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="manager-email">
                                    Manager email
                                </Label>
                                <Input
                                    id="manager-email"
                                    name="manager_email"
                                    type="email"
                                    required
                                />
                                <InputError message={errors.manager_email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="manager-role">
                                    Manager role
                                </Label>
                                <Select
                                    name="manager_role"
                                    defaultValue="manager"
                                >
                                    <SelectTrigger id="manager-role">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="manager">
                                            Manager
                                        </SelectItem>
                                        <SelectItem value="administrator">
                                            Administrator
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.manager_role} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="manager-password">
                                    Manager password
                                </Label>
                                <PasswordInput
                                    id="manager-password"
                                    name="manager_password"
                                    required
                                    autoComplete="new-password"
                                />
                                <InputError message={errors.manager_password} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create team
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

Teams.layout = {
    breadcrumbs: [
        { title: 'Administration', href: index.url() },
        { title: 'Teams', href: index.url() },
    ],
};
