<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->administrator()->create(['name' => 'Ada Admin']);

        $this->actingAs($this->admin);
    }

    /**
     * @return array{Team, User}
     */
    private function managedTeam(string $name = 'Sales'): array
    {
        $team = Team::factory()->create(['name' => $name]);
        $manager = User::factory()->manager()->forTeam($team)->create();

        return [$team, $manager];
    }

    public function test_users_can_be_searched_and_filtered_by_role(): void
    {
        [, $manager] = $this->managedTeam();
        $advisor = User::factory()->advisor()->forTeam($manager->currentTeam)->create(['email' => 'unique-advisor@example.com']);

        Livewire::test('pages::admin.users.index')
            ->assertSee($manager->name)
            ->assertSee($advisor->name)
            ->set('role', 'manager')
            ->assertSee($manager->email)
            ->assertDontSee($advisor->email)
            ->set('role', '')
            ->set('search', 'unique-advisor')
            ->assertSee($advisor->email)
            ->assertDontSee($manager->email);
    }

    public function test_users_can_be_created(): void
    {
        [$team] = $this->managedTeam();

        Livewire::test('pages::admin.users.create')
            ->set('name', 'New Advisor')
            ->set('email', 'New.Advisor@Example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->set('role', 'advisor_plus')
            ->set('team', $team->slug)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'new.advisor@example.com')->firstOrFail();

        $this->assertTrue($user->isAdvisorPlus());
        $this->assertTrue($user->belongsToTeam($team));
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_managers_can_be_created_with_a_new_team(): void
    {
        Livewire::test('pages::admin.users.create')
            ->set('name', 'New Manager')
            ->set('email', 'manager@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->set('role', 'manager')
            ->set('newTeam', 'North Region')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Team::where('name', 'North Region')->firstOrFail()->hasManager());
    }

    public function test_user_creation_enforces_team_rules(): void
    {
        $unmanaged = Team::factory()->create();

        $component = Livewire::test('pages::admin.users.create')
            ->set('name', 'New Advisor')
            ->set('email', 'advisor@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->set('role', 'advisor');

        // Advisors only see teams with a manager, and must pick one.
        $this->assertFalse($component->instance()->teams->contains($unmanaged));

        $component->call('save')->assertHasErrors(['team']);

        $component->set('team', $unmanaged->slug)->call('save')->assertHasErrors(['team']);

        $component->set('password_confirmation', 'different')->call('save')->assertHasErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'advisor@example.com']);
    }

    public function test_switching_to_an_advisor_role_clears_new_team_and_unmanaged_selection(): void
    {
        $unmanaged = Team::factory()->create();

        Livewire::test('pages::admin.users.create')
            ->set('role', 'manager')
            ->set('team', $unmanaged->slug)
            ->set('newTeam', 'Something')
            ->set('role', 'advisor')
            ->assertSet('team', '')
            ->assertSet('newTeam', '');
    }

    public function test_user_details_and_role_can_be_updated(): void
    {
        [$team] = $this->managedTeam();
        $user = User::factory()->advisor()->forTeam($team)->create();

        Livewire::test('pages::admin.users.edit', ['user' => $user])
            ->set('name', 'Renamed')
            ->set('email', 'renamed@example.com')
            ->set('role', 'manager')
            ->call('updateDetails')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Renamed', $user->name);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertTrue($user->isManager());
    }

    public function test_role_changes_cannot_strand_advisors(): void
    {
        [$team, $manager] = $this->managedTeam();
        User::factory()->advisor()->forTeam($team)->create();

        Livewire::test('pages::admin.users.edit', ['user' => $manager])
            ->set('role', 'advisor_plus')
            ->call('updateDetails')
            ->assertHasErrors(['role'])
            ->assertSee('Manager');

        $this->assertTrue($manager->fresh()->isManager());
    }

    public function test_demoting_to_advisor_requires_a_managed_team(): void
    {
        $admin = User::factory()->administrator()->create();

        Livewire::test('pages::admin.users.edit', ['user' => $admin])
            ->set('role', 'advisor')
            ->call('updateDetails')
            ->assertHasErrors(['role']);

        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    public function test_administrators_cannot_change_their_own_role_or_delete_themselves(): void
    {
        Livewire::test('pages::admin.users.edit', ['user' => $this->admin])
            ->assertDontSeeHtml('data-test="admin-delete-user-button"')
            ->set('role', 'advisor')
            ->call('updateDetails')
            ->assertHasErrors(['role'])
            ->call('deleteUser');

        Livewire::test('pages::admin.users.index')
            ->call('confirmDelete', $this->admin->id)
            ->call('deleteUser');

        $this->assertTrue($this->admin->fresh()->isAdministrator());
    }

    public function test_email_must_be_unique(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->manager()->create();

        Livewire::test('pages::admin.users.edit', ['user' => $user])
            ->set('email', $other->email)
            ->call('updateDetails')
            ->assertHasErrors(['email']);
    }

    public function test_passwords_can_be_set(): void
    {
        $user = User::factory()->manager()->create();

        Livewire::test('pages::admin.users.edit', ['user' => $user])
            ->set('password', 'brand-new-password')
            ->set('password_confirmation', 'mismatch')
            ->call('updatePassword')
            ->assertHasErrors(['password'])
            ->set('password_confirmation', 'brand-new-password')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertSet('password', '');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_team_memberships_can_be_reassigned(): void
    {
        [$sales, $salesManager] = $this->managedTeam('Sales');
        [$support] = $this->managedTeam('Support');
        $advisor = User::factory()->advisor()->forTeam($sales)->create();

        Livewire::test('pages::admin.users.edit', ['user' => $advisor])
            ->set('teams', [(string) $support->id])
            ->call('updateTeams')
            ->assertHasNoErrors();

        $advisor->refresh();

        $this->assertFalse($advisor->belongsToTeam($sales));
        $this->assertTrue($advisor->belongsToTeam($support));
        $this->assertTrue($advisor->isCurrentTeam($support));
    }

    public function test_team_reassignment_cannot_strand_advisors(): void
    {
        [$sales, $manager] = $this->managedTeam('Sales');
        $unmanaged = Team::factory()->create();
        $advisor = User::factory()->advisor()->forTeam($sales)->create();

        // The advisor can't be moved to a team without a manager...
        Livewire::test('pages::admin.users.edit', ['user' => $advisor])
            ->set('teams', [(string) $unmanaged->id])
            ->call('updateTeams')
            ->assertHasErrors(['teams']);

        // ...and their only manager can't leave them behind.
        Livewire::test('pages::admin.users.edit', ['user' => $manager])
            ->set('teams', [])
            ->call('updateTeams')
            ->assertHasErrors(['teams']);

        $this->assertTrue($advisor->fresh()->belongsToTeam($sales));
        $this->assertTrue($manager->fresh()->belongsToTeam($sales));
    }

    public function test_sign_in_methods_can_be_reset(): void
    {
        $user = User::factory()->manager()->withTwoFactor()->create();

        Livewire::test('pages::admin.users.edit', ['user' => $user])
            ->assertSeeHtml('data-test="admin-user-reset-2fa"')
            ->call('resetTwoFactor')
            ->assertDontSeeHtml('data-test="admin-user-reset-2fa"');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_users_can_be_deleted(): void
    {
        $advisorTeam = User::factory()->manager()->create()->currentTeam;
        $advisor = User::factory()->advisor()->forTeam($advisorTeam)->create();
        $other = User::factory()->advisorPlus()->forTeam($advisorTeam)->create();

        Livewire::test('pages::admin.users.edit', ['user' => $advisor])
            ->call('deleteUser')
            ->assertRedirect(route('admin.users.index'));

        Livewire::test('pages::admin.users.index')
            ->call('confirmDelete', $other->id)
            ->call('deleteUser');

        $this->assertModelMissing($advisor);
        $this->assertModelMissing($other);
    }

    public function test_the_last_manager_of_advisors_cannot_be_deleted(): void
    {
        [$team, $manager] = $this->managedTeam();
        User::factory()->advisor()->forTeam($team)->create();

        Livewire::test('pages::admin.users.edit', ['user' => $manager])
            ->call('deleteUser')
            ->assertNoRedirect();

        $this->assertModelExists($manager);
    }
}
