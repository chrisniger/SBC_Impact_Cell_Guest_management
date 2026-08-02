<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 34 — Admin Messages (in-app announcement board).
 *
 * Replaces the Phase 06d.0 "Coming soon" stub with a real board:
 *
 *   GET    /admin/messages              → index   (announcement list)
 *   POST   /admin/messages              → store   (post a new announcement)
 *   DELETE /admin/messages/{announcement} → destroy (remove an announcement)
 *
 * Announcements are in-app only (no email — the user chose the simple,
 * instant feed over an SMTP broadcast). Every authenticated user sees them
 * on their dashboard (DashboardController adds `announcements` to every
 * variant's payload); only Administrator can post/delete from this page.
 *
 * The listing route stays behind `gate.stubs` (production-hidden per the
 * design decision); the write endpoints stay available for provisioning,
 * matching the Users / Roles-Permissions admin pattern.
 */
class MessagesController extends Controller
{
    /**
     * GET /admin/messages — announcement board (Admin only).
     */
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        return Inertia::render('Admin/Messages/Index', [
            'announcements' => $this->announcementsPayload(),
        ]);
    }

    /**
     * POST /admin/messages — post a new announcement.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body'  => ['required', 'string', 'max:10000'],
        ]);

        Announcement::create([
            'title'          => $data['title'],
            'body'           => $data['body'],
            'author_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    /**
     * DELETE /admin/messages/{announcement} — remove an announcement.
     */
    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }

    /**
     * Shared announcement payload shape (also used by DashboardController).
     *
     * Public static so the dashboard (and any future consumer) renders the
     * same wire format without duplicating the mapping.
     */
    public static function announcementsPayload(): array
    {
        return Announcement::query()
            ->with('author:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Announcement $a) => [
                'id'         => (int) $a->id,
                'title'      => $a->title,
                'body'       => $a->body,
                'authorName' => $a->author?->name ?? 'Administrator',
                'createdAt'  => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
