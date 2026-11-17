import { Head } from '@inertiajs/react';
import TeamMemberController from '@/actions/App/Http/Controllers/Team/TeamMemberController';
import { TeamMembers } from '@/components/team-members';
import { index as teamRoute } from '@/routes/team';
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

export default function TeamIndex({ team, members, roles, canManage }: Props) {
    const slug = team.slug;

    return (
        <>
            <Head title="My Team" />
            <TeamMembers
                title={team.name}
                members={members}
                roles={roles}
                canManage={canManage}
                urls={{
                    store: TeamMemberController.store.url(slug),
                    update: (id) =>
                        TeamMemberController.update.url({
                            current_team: slug,
                            member: id,
                        }),
                    destroy: (id) =>
                        TeamMemberController.destroy.url({
                            current_team: slug,
                            member: id,
                        }),
                }}
            />
        </>
    );
}

TeamIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'My Team',
            href: props.currentTeam ? teamRoute(props.currentTeam.slug) : '/',
        },
    ],
});
