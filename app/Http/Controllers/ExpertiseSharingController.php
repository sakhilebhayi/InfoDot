<?php

namespace App\Http\Controllers;

use App\Services\Participation\ExpertiseSharing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in person's own switch for "let Dot.Memory remember the topics I help with". It takes no user id: it can only
 * ever change, and ask Dot.Memory about, whoever is signed in. Off is the default and the safe state.
 */
class ExpertiseSharingController extends Controller
{
    public function update(Request $request, ExpertiseSharing $sharing): RedirectResponse
    {
        $data = $request->validate(['share_expertise' => ['required', 'in:0,1']]);
        $user = $request->user();

        $result = $data['share_expertise'] === '1' ? $sharing->enable($user) : $sharing->disable($user);

        return $this->back($result);
    }

    /** Turn sharing off and erase everything Dot.Memory holds about the person. The same operation as switching it off. */
    public function erase(Request $request, ExpertiseSharing $sharing): RedirectResponse
    {
        return $this->back($sharing->disable($request->user()));
    }

    /** @param array{level: string, message: string} $result */
    private function back(array $result): RedirectResponse
    {
        return redirect()->to(route('points').'#memory-sharing')->with('memory_notice', $result);
    }
}
