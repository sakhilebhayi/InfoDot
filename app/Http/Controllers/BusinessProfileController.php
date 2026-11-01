<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\BusinessCategory;
use App\Models\BusinessProfileEvent;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Services\Attachments\TeamMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A company's public identity inside InfoDot (R-29 Part A): the business page every signed-in
 * person may read, and the editing surfaces its owner and administrators use to shape it.
 * The verification badge and trust card show only what InfoDot actually verified (Phase 2).
 */
class BusinessProfileController extends Controller
{
    private const MAX_CATEGORIES = 3;

    /** The public business page. Personal workspaces have none. */
    public function show(Request $request, Team $team)
    {
        $user = $request->user();

        // Private and members-only businesses answer 404 to those who may not see them, so a
        // hidden business cannot be discovered by guessing its address (R-33 §11).
        abort_unless($team->isVisibleTo($user), 404);
        $team->loadMissing(['standing', 'categories', 'services', 'projects', 'locations']);

        // "Preview as a visitor" (R-33 §6): the business's managers see the page exactly as
        // someone outside the business would — no member-only activity, no edit controls.
        $canEdit = Gate::allows('updateProfile', $team);
        $preview = $canEdit && $request->query('as') === 'visitor';
        $isMember = ! $preview && $user !== null && $user->belongsToTeam($team);

        // Outsiders see the company's public contributions only; members see the company's own.
        $questions = Questions::query()
            ->where('team_id', $team->id)
            ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
            ->latest()->limit(5)->get();

        $solutions = Solutions::query()
            ->where('team_id', $team->id)
            ->when(! $isMember, fn ($q) => $q->where('visibility', 'public'))
            ->latest()->limit(5)->get();

        return view('business.show', [
            'team' => $team,
            'profile' => $team->profile,
            'logo' => $team->currentMedia('logo'),
            'cover' => $team->currentMedia('cover'),
            'gallery' => $team->gallery(),
            'questions' => $questions,
            'solutions' => $solutions,
            'isMember' => $isMember,
            'canEdit' => $canEdit && ! $preview,
            'preview' => $preview,
            'announcements' => $team->announcements()->showing()->limit(5)->get(),
            'offeringPictures' => $team->mediaByRef('offering'),
            'projectPictures' => $team->mediaByRef('project'),
            'brochures' => $team->media()->where('role', TeamMediaService::BROCHURE)->whereNull('superseded_at')->orderBy('id')->get(),
        ]);
    }

    public function edit(Request $request, Team $team)
    {
        Gate::authorize('updateProfile', $team);

        return view('business.edit', [
            'team' => $team,
            'profile' => $team->profileOrNew(),
            'logo' => $team->currentMedia('logo'),
            'cover' => $team->currentMedia('cover'),
            'gallery' => $team->gallery(),
            'categories' => BusinessCategory::where('active', true)->orderBy('sort')->get(),
            'chosen' => $team->categories()->pluck('business_categories.id')->all(),
            'services' => $team->services()->get(),
            'projects' => $team->projects()->get(),
            'locations' => $team->locations()->get(),
            'announcements' => $team->announcements()->limit(20)->get(),
            'offeringPictures' => $team->mediaByRef('offering'),
            'projectPictures' => $team->mediaByRef('project'),
            'brochures' => $team->media()->where('role', TeamMediaService::BROCHURE)->whereNull('superseded_at')->orderBy('id')->get(),
            'events' => $team->profileEvents()->with('user:id,name')->limit(15)->get(),
        ]);
    }

    /** Who can find the business, what kind of business it is, and where the directory files it (R-33 §2, §11). */
    public function updateDirectory(Request $request, Team $team)
    {
        Gate::authorize('updateProfile', $team);

        $data = $request->validate([
            'visibility' => ['required', Rule::in(array_keys(TeamProfile::VISIBILITY))],
            'business_type' => ['nullable', Rule::in(array_keys(TeamProfile::BUSINESS_TYPES))],
            'categories' => ['nullable', 'array', 'max:'.self::MAX_CATEGORIES],
            'categories.*' => ['integer', Rule::exists('business_categories', 'id')->where('active', true)],
            'accepts_enquiries' => ['nullable', 'boolean'],
        ], ['categories.max' => 'Choose up to '.self::MAX_CATEGORIES.' categories — the ones a customer would look under first.']);

        $profile = $team->profileOrNew();
        $before = $profile->visibility;
        $profile->fill(['visibility' => $data['visibility'], 'business_type' => $data['business_type'] ?? null]);
        if ($request->has('accepts_enquiries')) {
            $profile->accepts_enquiries = (bool) $data['accepts_enquiries'];
        }
        $profile->save();
        $team->categories()->sync($data['categories'] ?? []);
        BusinessProfileEvent::record($team, $request->user(), 'listing_changed', $before !== $data['visibility'] ? $before.' → '.$data['visibility'] : 'type and categories');

        return redirect()->route('business.edit', $team)->withFragment('directory')->with('status', match ($data['visibility']) {
            TeamProfile::PUBLIC => 'Saved. The business is listed for anyone, and its page opens without signing in.',
            TeamProfile::MEMBERS => 'Saved. The business is listed for people signed in to InfoDot.',
            default => 'Saved. The business is not listed; its page is for your own team.',
        });
    }

    public function update(Request $request, Team $team)
    {
        Gate::authorize('updateProfile', $team);

        $data = $request->validate([
            'trading_name' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'industry' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'products_services' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:160'],
            'regions' => ['nullable', 'string', 'max:160'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'registration_country' => ['nullable', 'string', 'max:60'],
            'established_on' => ['nullable', 'date', 'before_or_equal:today'],
            'size' => ['nullable', 'string', 'max:40'],
            'links' => ['nullable', 'array'],
            'links.*' => ['nullable', 'url:http,https', 'max:255'],
            'industries_served' => ['nullable', 'string', 'max:300'],
            'public_contact' => ['nullable', 'array'],
            'public_contact.*' => ['boolean'],
        ]);

        // Only the link slots the form offers are stored, and empty ones fall away.
        $data['links'] = array_filter(array_intersect_key($data['links'] ?? [], array_flip(TeamProfile::LINK_KEYS)));

        // Which contact details show is the business's choice (R-33 §5B); the form sends every field.
        $public = array_map(fn ($key) => (bool) ($data['public_contact'][$key] ?? false), array_combine(array_keys(TeamProfile::CONTACT_FIELDS), array_keys(TeamProfile::CONTACT_FIELDS)));
        unset($data['public_contact']);

        $profile = $team->profileOrNew();
        $contactChanged = $request->has('public_contact') && $public !== array_map(fn ($k) => $profile->shows($k), array_combine(array_keys($public), array_keys($public)));
        $profile->fill($data);
        if ($request->has('public_contact')) {
            $profile->public_contact = $public;
        }
        $profile->save();

        BusinessProfileEvent::record($team, $request->user(), 'profile_saved');
        if ($contactChanged) {
            BusinessProfileEvent::record($team, $request->user(), 'contact_changed', collect($public)->filter()->keys()->implode(', ') ?: 'none public');
        }

        return redirect()->route('business.edit', $team)
            ->with('status', 'The business profile was saved.');
    }

    public function storeMedia(Request $request, Team $team, TeamMediaService $media)
    {
        Gate::authorize('updateProfile', $team);

        $data = $request->validate([
            'role' => ['required', 'in:logo,cover,gallery'],
            'file' => ['required', 'file'],
            'caption' => ['nullable', 'string', 'max:500'],
            'crop' => ['nullable', 'array'],
            'crop.x' => ['required_with:crop', 'numeric', 'min:0', 'max:1'],
            'crop.y' => ['required_with:crop', 'numeric', 'min:0', 'max:1'],
            'crop.w' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
            'crop.h' => ['required_with:crop', 'numeric', 'gt:0', 'max:1'],
        ]);

        $media->put(
            $request->user(),
            $team,
            $data['role'],
            $request->file('file'),
            $data['crop'] ?? null,
            $data['caption'] ?? null,
        );
        BusinessProfileEvent::record($team, $request->user(), 'media_added', $data['role']);

        return redirect()->route('business.edit', $team)->with('status', match ($data['role']) {
            'logo' => 'The logo is on the profile. The one it replaced stays on the record.',
            'cover' => 'The cover photograph is on the profile. The one it replaced stays on the record.',
            default => 'Added to the gallery.',
        });
    }

    public function removeMedia(Request $request, Team $team, Attachment $attachment, TeamMediaService $media)
    {
        Gate::authorize('updateProfile', $team);

        $media->remove($team, $attachment);
        BusinessProfileEvent::record($team, $request->user(), 'media_removed', $attachment->role);

        return redirect()->route('business.edit', $team)
            ->with('status', 'Taken off the profile. The image stays on the record.');
    }
}
