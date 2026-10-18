<?php

namespace App\Support;

use App\Models\Questions;
use App\Models\Resource;
use App\Models\Solutions;
use Illuminate\Database\Eloquent\Model;

/**
 * The human-readable reference of a thing a company owns: OWNER CODE, kind, serial,
 * for example NWFN-Q-000014. It is the real record id, so it is stable, and it names
 * the owner. Resolving a reference goes through the same visibility rules as any
 * other lookup: it can never open something the person may not see.
 */
final class Reference
{
    public const KINDS = ['Q' => Questions::class, 'S' => Solutions::class, 'F' => Resource::class, 'D' => Resource::class];

    /** What a registered object is called in a reference: F for a stored file, D for a document. */
    private const RESOURCE_LETTER = ['file' => 'F', 'document' => 'D'];

    public static function for(Model $item): string
    {
        $kind = $item instanceof Resource
            ? (self::RESOURCE_LETTER[$item->type] ?? 'X')
            : (array_search($item::class, self::KINDS, true) ?: 'X');
        $code = $item->team?->code ?? 'INFO';

        return sprintf('%s-%s-%06d', $code, $kind, $item->getKey());
    }

    public static function resolve(string $text): ?Model
    {
        if (! preg_match('/^\s*([A-Z0-9]{4})-([QSFD])-0*(\d{1,9})\s*$/i', $text, $m)) {
            return null;
        }

        $class = self::KINDS[strtoupper($m[2])];
        $item = $class::query()->with('team')->find((int) $m[3]);

        // The code must be the item's real owner: a forged prefix opens nothing.
        // A stored file or a document is only openable by someone in its company, and its letter
        // must be the right one for what it is (NOFI-F-... never opens a document).
        if ($item instanceof Resource && (! self::viewerBelongs($item) || (self::RESOURCE_LETTER[$item->type] ?? null) !== strtoupper($m[2]))) {
            return null;
        }

        return $item && strcasecmp(self::for($item), strtoupper($m[1]).'-'.strtoupper($m[2]).'-'.sprintf('%06d', $m[3])) === 0 ? $item : null;
    }

    private static function viewerBelongs(Resource $resource): bool
    {
        $user = auth()->user();

        return $user !== null && $resource->team !== null && $user->belongsToTeam($resource->team);
    }

    public static function route(Model $item): string
    {
        if ($item instanceof Resource) {
            return route('resources.open', ['resource' => $item->uuid]);
        }

        return $item instanceof Questions
            ? route('questions.view', ['qid' => $item->getKey()])
            : route('solutions.view', ['id' => $item->getKey()]);
    }
}
