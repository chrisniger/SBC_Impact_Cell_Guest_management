<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\MessagesController;
use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

/**
 * Phase 34 — Admin Messages (in-app announcement board) feature test.
 *
 * Locks the user-visible contract delivered in Phase 34 (the build that
 * replaced the Phase 06d.0 "Coming soon" stub with a real board):
 *
 *   1. Page is Administrator-only (403 for other roles).
 *   2. Admin can post an announcement (title + body required).
 *   3. Admin can delete an announcement.
 *   4. Announcements surface on EVERY role's dashboard payload.
 *   5. The listing route stays behind `gate.stubs` (production → 404).
 *
 * Inheritance: extends Tests\TestCase (RefreshDatabaseWithSeed), which rebinds
 * the connection to the isolated `impact_test` DB. Deliberately does NOT
 * re-`use RefreshDatabase` at the class level — the double-use would shadow the
 * isolation rebind (see RolesPermissionsAdminTest).
 */
class MessagesAdminTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─── Sub-assertion 1 — admin can view the board ──────────────────
    public function test_admin_can_view_messages_board(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        Announcement::create([
            'title'          => 'Welcome to the portal',
            'body'           => 'First announcement body.',
            'author_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.messages.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Messages/Index')
            ->has('announcements', 1)
            ->where('announcements.0.title', 'Welcome to the portal')
            ->where('announcements.0.authorName', $admin->name)
        );
    }

    // ─── Sub-assertion 2 — non-admin gets 403 on every endpoint ───────
    public function test_non_admin_cannot_view_or_mutate(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');

        $this->actingAs($leader)->get(route('admin.messages.index'))->assertForbidden();
        $this->actingAs($leader)->post(route('admin.messages.store'), [
            'title' => 'Hacker post',
            'body'  => 'nope',
        ])->assertForbidden();
        $a = Announcement::create(['title' => 'T', 'body' => 'B', 'author_user_id' => $leader->id]);
        $this->actingAs($leader)->delete(route('admin.messages.destroy', $a))->assertForbidden();
    }

    // ─── Sub-assertion 3 — admin can post an announcement ─────────────
    public function test_admin_can_post_announcement(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->post(route('admin.messages.store'), [
            'title' => 'Cell leaders meeting',
            'body'  => 'Sunday 4pm in the main hall.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('announcements', [
            'title'          => 'Cell leaders meeting',
            'author_user_id' => $admin->id,
        ]);
    }

    // ─── Sub-assertion 4 — validation: title + body required ──────────
    public function test_title_and_body_are_required(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->post(route('admin.messages.store'), []);

        $response->assertSessionHasErrors(['title', 'body']);
        $this->assertSame(0, Announcement::count());
    }

    // ─── Sub-assertion 5 — admin can delete an announcement ───────────
    public function test_admin_can_delete_announcement(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $a = Announcement::create([
            'title'          => 'Obsolete',
            'body'           => 'Old news.',
            'author_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.messages.destroy', $a));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('announcements', ['id' => $a->id]);
    }

    // ─── Sub-assertion 6 — announcements surface on EVERY dashboard ───
    public function test_announcements_surface_on_every_dashboard_variant(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        Announcement::create([
            'title'          => 'Portal-wide notice',
            'body'           => 'Read me.',
            'author_user_id' => $admin->id,
        ]);

        $roles = [
            'Administrator',
            'FollowUpOfficer',
            'Follow_UP',
            'Impact_Leaders',
            'Impact_Cell_Admin',
            'Impact_Zonal_Coordinator',
        ];
        foreach ($roles as $role) {
            $user = $this->makeUserWithRole($role);
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('announcements', 1)
                    ->where('announcements.0.title', 'Portal-wide notice')
                );
        }
    }

    // ─── Sub-assertion 7 — production env hides the listing (gate.stubs) ──
    public function test_listing_is_hidden_in_production(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        // Env override uses detectEnvironment() — NOT config(['app.env' => ...]) —
        // because GateStubPagesByEnvironment reads app()->environment() (see
        // StubGateTest for the same convention).
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs($admin)->get(route('admin.messages.index'))->assertNotFound();
    }

    // ─── Sub-assertion 8 — payload helper shape matches the board ─────
    public function test_announcements_payload_helper_contract(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        Announcement::create([
            'title'          => 'Helper check',
            'body'           => 'Body text.',
            'author_user_id' => $admin->id,
        ]);

        $payload = MessagesController::announcementsPayload();

        $this->assertCount(1, $payload);
        $this->assertSame('Helper check', $payload[0]['title']);
        $this->assertSame($admin->name, $payload[0]['authorName']);
        $this->assertArrayHasKey('createdAt', $payload[0]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name'        => 'Msg ' . $role,
            // uniqid suffix — the role-derived base (e.g. administrator@impact.test)
            // would otherwise collide with a same-role user created by another
            // test in this file within the same run.
            'email'       => 'msg.' . strtolower(str_replace('_', '.', $role)) . '.' . uniqid() . '@impact.test',
            'active_role' => $role,
        ]);
        $user->assignRole($role);
        return $user;
    }
}
