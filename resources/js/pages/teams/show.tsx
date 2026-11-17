import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AdminTeamController from '@/actions/App/Http/Controllers/Admin/TeamController';
import AdminTeamMemberController from '@/actions/App/Http/Controllers/Admin/TeamMemberController';
import InputError from '@/components/input-error';
import { TeamMembers } from '@/components/team-members';
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
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/teams';
import type { Role } from '@/types';

type Props = {
    team: { id: number; name: string; slug: string };
    members: {
        id: number;
        name: string;
        email: string;
        role: Role;
        role_label: string;
        editable: boolean;
    }[];
    roles: { value: Role; label: string }[];
    canManage: boolean;
};

export default function TeamShow({ team, members, roles, canManage }: Props) {
    const [renaming, setRenaming] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const slug = team.slug;

    return (
        <>
            <Head title={team.name} />

            <div className="px-4 pt-4">
                <Link
                    href={index()}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Manage teams
                </Link>
            </div>

            <TeamMembers
                title={team.name}
                members={members}
                roles={roles}
                canManage={canManage}
                actions={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setRenaming(true)}
                            data-test="rename-team-button"
                        >
                            <Pencil /> Rename
                        </Button>
                        <Button
                            variant="outline"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeleting(true)}
                            data-test="delete-team-button"
                        >
                            <Trash2 /> Delete team
                        </Button>
                    </>
                }
                urls={{
                    store: AdminTeamMemberController.store.url(slug),
                    update: (id) =>
                        AdminTeamMemberController.update.url({
                            team: slug,
                            member: id,
                        }),
                    destroy: (id) =>
                        AdminTeamMemberController.destroy.url({
                            team: slug,
                            member: id,
                        }),
                }}
            />

            <Dialog open={renaming} onOpenChange={setRenaming}>
                <DialogContent>
                    <DialogTitle>Rename team</DialogTitle>
                    <DialogDescription>
                        The team's URL changes with its name.
                    </DialogDescription>
                    <Form
                        action={AdminTeamController.update.url(slug)}
                        method="patch"
                        onSuccess={() => setRenaming(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="team-name">Team name</Label>
                                    <Input
                                        id="team-name"
                                        name="name"
                                        defaultValue={team.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Save
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent>
                    <DialogTitle>Delete {team.name}?</DialogTitle>
                    <DialogDescription>
                        This permanently deletes the team and all{' '}
                        {members.length}{' '}
                        {members.length === 1
                            ? 'user account'
                            : 'user accounts'}{' '}
                        on it. This cannot be undone.
                    </DialogDescription>
                    <Form
                        action={AdminTeamController.destroy.url(slug)}
                        method="delete"
                    >
                        {({ processing }) => (
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Delete team
                                </Button>
                            </DialogFooter>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}

TeamShow.layout = (props: { team?: { name: string } }) => ({
    breadcrumbs: [
        { title: 'Administration', href: index.url() },
        { title: 'Teams', href: index.url() },
        { title: props.team?.name ?? 'Team', href: '#' },
    ],
});
