<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\BusinessAnnouncement;
use App\Models\BusinessLocation;
use App\Models\BusinessProfileEvent;
use App\Models\BusinessProject;
use App\Models\BusinessService;
use App\Models\Team;
use App\Services\Attachments\TeamMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What a business shows on its page (R-33 §4, §6): offerings with pictures and the prices it
 * chooses to publish, a portfolio, its locations, announcements and brochures. Only the business's
 * owner and administrators manage these, every change is written to the profile's audit trail,
 * and removed pictures and brochures stay on the record rather than being deleted.
 */
class BusinessShowcaseController extends Controller
{
    private const MAX_SERVICES = 30;

    private const MAX_PROJECTS = 12;

    private const MAX_PROJECT_PICTURES = 6;

    private const MAX_LOCATIONS = 10;

    private const MAX_SHOWING_ANNOUNCEMENTS = 5;

    private const MAX_BROCHURES = 5;

    public function __construct(private TeamMediaService $media) {}

    // ------------------------------------------------------------------ offerings

    public function storeService(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);
        $data = $this->validateService($request);
        $this->limit($team->services()->count() < self::MAX_SERVICES, 'A business can list up to '.self::MAX_SERVICES.' services and products.');

        $service = DB::transaction(function () use ($request, $team, $data) {
            $service = new BusinessService($data);
            $service->sort = (int) $team->services()->max('sort') + 1;
            $team->services()->save($service);
            if ($request->hasFile('image')) {
                $this->media->put($request->user(), $team, 'offering', $request->file('image'), null, null, $service->id);
            }

            return $service;
        });

        BusinessProfileEvent::record($team, $request->user(), 'service_added', $service->name);

        return $this->back($team, 'services', '“'.$service->name.'” is on the profile.');
    }

    public function updateService(Request $request, Team $team, BusinessService $service): RedirectResponse
    {
        $this->owned($request, $team, $service->team_id);
        $data = $this->validateService($request);

        DB::transaction(function () use ($request, $team, $service, $data) {
            $service->fill($data)->save();
            if ($request->boolean('remove_image')) {
                $this->media->retire($team, 'offering', $service->id);
            } elseif ($request->hasFile('image')) {
                $this->media->put($request->user(), $team, 'offering', $request->file('image'), null, null, $service->id);
            }
        });

        BusinessProfileEvent::record($team, $request->user(), 'service_changed', $service->name);

        return $this->back($team, 'services', '“'.$service->name.'” was saved.');
    }

    public function destroyService(Request $request, Team $team, BusinessService $service): RedirectResponse
    {
        $this->owned($request, $team, $service->team_id);

        $this->media->retire($team, 'offering', $service->id);
        $service->delete();
        BusinessProfileEvent::record($team, $request->user(), 'service_removed', $service->name);

        return $this->back($team, 'services', '“'.$service->name.'” was taken off the profile.');
    }

    /** @return array<string, mixed> */
    private function validateService(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'kind' => ['nullable', Rule::in(array_keys(BusinessService::KINDS))],
            'description' => ['nullable', 'string', 'max:600'],
            'price_note' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'file'],
        ], [], ['price_note' => 'price']);
        $data['kind'] ??= 'service';

        return $data;
    }

    // ------------------------------------------------------------------ portfolio

    public function storeProject(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);
        $data = $this->validateProject($request);
        $this->limit($team->projects()->count() < self::MAX_PROJECTS, 'A portfolio holds up to '.self::MAX_PROJECTS.' projects. Take an older one down first.');

        $project = DB::transaction(function () use ($request, $team, $data) {
            $project = new BusinessProject($data);
            $project->sort = (int) $team->projects()->max('sort') + 1;
            $team->projects()->save($project);
            if ($request->hasFile('image')) {
                $this->media->put($request->user(), $team, 'project', $request->file('image'), null, $request->input('caption'), $project->id);
            }

            return $project;
        });

        BusinessProfileEvent::record($team, $request->user(), 'project_added', $project->title);

        return $this->back($team, 'portfolio', '“'.$project->title.'” is in the portfolio.');
    }

    public function updateProject(Request $request, Team $team, BusinessProject $project): RedirectResponse
    {
        $this->owned($request, $team, $project->team_id);
        $data = $this->validateProject($request);

        if ($request->hasFile('image')) {
            $pictures = $team->mediaByRef('project')->get($project->id)?->count() ?? 0;
            $this->limit($pictures < self::MAX_PROJECT_PICTURES, 'A project shows up to '.self::MAX_PROJECT_PICTURES.' pictures. Take one down first.');
        }

        DB::transaction(function () use ($request, $team, $project, $data) {
            $project->fill($data)->save();
            if ($request->hasFile('image')) {
                $this->media->put($request->user(), $team, 'project', $request->file('image'), null, $request->input('caption'), $project->id);
            }
        });

        BusinessProfileEvent::record($team, $request->user(), 'project_changed', $project->title);

        return $this->back($team, 'portfolio', '“'.$project->title.'” was saved.');
    }

    public function destroyProject(Request $request, Team $team, BusinessProject $project): RedirectResponse
    {
        $this->owned($request, $team, $project->team_id);

        $this->media->retire($team, 'project', $project->id);
        $project->delete();
        BusinessProfileEvent::record($team, $request->user(), 'project_removed', $project->title);

        return $this->back($team, 'portfolio', '“'.$project->title.'” was taken out of the portfolio.');
    }

    /** @return array<string, mixed> */
    private function validateProject(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:140'],
            'summary' => ['nullable', 'string', 'max:1200'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'location' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'file'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        return collect($data)->only(['title', 'summary', 'year', 'location'])->all();
    }

    // ------------------------------------------------------------------ locations

    public function storeLocation(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);
        $data = $this->validateLocation($request);
        $this->limit($team->locations()->count() < self::MAX_LOCATIONS, 'A business can list up to '.self::MAX_LOCATIONS.' locations.');

        $location = new BusinessLocation($data);
        $location->sort = (int) $team->locations()->max('sort') + 1;
        $team->locations()->save($location);
        BusinessProfileEvent::record($team, $request->user(), 'location_added', $location->label.', '.$location->city);

        return $this->back($team, 'locations', $location->label.' is on the profile.');
    }

    public function updateLocation(Request $request, Team $team, BusinessLocation $location): RedirectResponse
    {
        $this->owned($request, $team, $location->team_id);
        $location->fill($this->validateLocation($request))->save();
        BusinessProfileEvent::record($team, $request->user(), 'location_changed', $location->label.', '.$location->city);

        return $this->back($team, 'locations', $location->label.' was saved.');
    }

    public function destroyLocation(Request $request, Team $team, BusinessLocation $location): RedirectResponse
    {
        $this->owned($request, $team, $location->team_id);
        $location->delete();
        BusinessProfileEvent::record($team, $request->user(), 'location_removed', $location->label.', '.$location->city);

        return $this->back($team, 'locations', $location->label.' was taken off the profile.');
    }

    /** @return array<string, mixed> */
    private function validateLocation(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'min:2', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'max:60'],
            'show_address' => ['nullable', 'boolean'],
        ]) + ['show_address' => false];
    }

    // ------------------------------------------------------------------ announcements

    public function storeAnnouncement(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:140'],
            'body' => ['required', 'string', 'min:10', 'max:1500'],
            'ends_at' => ['nullable', 'date', 'after:today'],
        ]);
        $this->limit($team->announcements()->showing()->count() < self::MAX_SHOWING_ANNOUNCEMENTS, 'Up to '.self::MAX_SHOWING_ANNOUNCEMENTS.' announcements show at once. Withdraw an older one first.');

        $announcement = new BusinessAnnouncement($data + ['published_at' => now()]);
        $announcement->user_id = $request->user()->id;
        $team->announcements()->save($announcement);
        BusinessProfileEvent::record($team, $request->user(), 'announcement_published', $announcement->title);

        return $this->back($team, 'announcements', 'Published on the business page.');
    }

    /** Withdrawing ends it now; the row stays, so the business keeps its own history. */
    public function withdrawAnnouncement(Request $request, Team $team, BusinessAnnouncement $announcement): RedirectResponse
    {
        $this->owned($request, $team, $announcement->team_id);

        if ($announcement->isShowing()) {
            $announcement->forceFill(['ends_at' => now()])->save();
            BusinessProfileEvent::record($team, $request->user(), 'announcement_withdrawn', $announcement->title);
        }

        return $this->back($team, 'announcements', 'The announcement is off the page.');
    }

    // ------------------------------------------------------------------ brochures

    public function storeBrochure(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:140'],
            'file' => ['required', 'file'],
        ]);
        $this->limit($team->media()->where('role', TeamMediaService::BROCHURE)->whereNull('superseded_at')->count() < self::MAX_BROCHURES, 'Up to '.self::MAX_BROCHURES.' brochures show at once. Take one down first.');

        $this->media->putBrochure($request->user(), $team, $request->file('file'), $data['title']);
        BusinessProfileEvent::record($team, $request->user(), 'brochure_added', $data['title']);

        return $this->back($team, 'brochures', '“'.$data['title'].'” is on the business page.');
    }

    // ------------------------------------------------------------------ pictures of an item

    /** Take one picture of a project or offering down; it stays on the record. */
    public function removePicture(Request $request, Team $team, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);
        abort_unless(in_array($attachment->role, ['offering', 'project', TeamMediaService::BROCHURE], true), 404);

        $this->media->remove($team, $attachment);
        $brochure = $attachment->role === TeamMediaService::BROCHURE;
        BusinessProfileEvent::record($team, $request->user(), $brochure ? 'brochure_removed' : 'media_removed', $attachment->caption);

        return $this->back($team, $brochure ? 'brochures' : ($attachment->role === 'project' ? 'portfolio' : 'services'), 'Taken off the page. It stays on the record.');
    }

    // ------------------------------------------------------------------ helpers

    /** Only this business's own people may change its items, and only its own items. */
    private function owned(Request $request, Team $team, int $itemTeamId): void
    {
        Gate::authorize('updateProfile', $team);
        abort_unless($itemTeamId === $team->id, 404);
    }

    private function limit(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['limit' => $message]);
        }
    }

    private function back(Team $team, string $section, string $status): RedirectResponse
    {
        return redirect()->route('business.edit', $team)->withFragment($section)->with('status', $status);
    }
}
