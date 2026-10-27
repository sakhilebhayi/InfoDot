<?php

use App\Http\Controllers\AchievementsController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\ExpertiseSharingController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibraryEntryController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PlatformLaunchController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\ResourceOpenController;
use App\Http\Controllers\TopicAssignmentController;
use App\Http\Controllers\TopicsController;
use App\Http\Controllers\TopicSuggestionsController;
use App\Livewire\EcosystemWidget;
use App\Livewire\Moderation\Library as ModerationLibrary;
use App\Livewire\Moderation\Queue as ModerationQueue;
use App\Livewire\Moderation\Topics as ModerationTopics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::match(['get', 'post'], '/auth/ecosystem', [EcosystemAuthController::class, 'handle'])
    ->withoutMiddleware(['Illuminate\Foundation\Http\Middleware\PreventRequestForgery', 'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken', 'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken'])
    ->name('auth.ecosystem');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // Dashboard Pages

    // -------------------------------------------------------
    // Analytics Controller
    // -------------------------------------------------------
    Route::get('/analytics', [AnalyticsController::class, 'dashboard'])->name('analytics.dashboard');

    // -------------------------------------------------------
    // Ecosystem Platform Switcher
    // -------------------------------------------------------
    Route::get('/platforms', EcosystemWidget::class)->name('ecosystem.widget');
    Route::get('/open/{resource}', ResourceOpenController::class)->name('resources.open');
    // Opens a platform signed in: a page that posts a single-use token to it. The token is never in a URL.
    Route::get('/go/{platform}', PlatformLaunchController::class)->middleware('throttle:30,1')->name('platform.launch');

    // -------------------------------------------------------
    // People: the members of the company the person is acting for
    // -------------------------------------------------------
    Route::get('/people', [PeopleController::class, 'index'])->name('people');

    // // -------------------------------------------------------
    // Solutions Controller
    // -------------------------------------------------------
    Route::get('/solutions', 'App\Http\Controllers\Solutions\SolutionsController@index')->name('solutions');
    Route::get('/solution/create', 'App\Http\Controllers\Solutions\SolutionsController@create')->name('add');
    Route::post('/solution/add', 'App\Http\Controllers\Solutions\SolutionsController@add_solution')->name('solutions.add');
    Route::get('/solution/view/{id}', 'App\Http\Controllers\Solutions\SolutionsController@view_solution')->name('solutions.view');

    // -------------------------------------------------------
    // Question Controller
    // -------------------------------------------------------
    Route::get('/questions', 'App\Http\Controllers\Questions\QuestionsController@index')->name('questions');
    Route::get('/questions/ask', 'App\Http\Controllers\Questions\QuestionsController@seek')->name('seek');
    Route::post('/questions/add', 'App\Http\Controllers\Questions\QuestionsController@add_question')->middleware('throttle:10,1')->name('questions.add');
    Route::get('/question/view/{qid}', 'App\Http\Controllers\Questions\QuestionsController@view')->name('questions.view');
    Route::post('/question/{qid}/topics', [TopicAssignmentController::class, 'question'])->whereNumber('qid')->middleware(['moderator', 'throttle:30,1'])->name('questions.topics');
    Route::post('/solution/{id}/topics', [TopicAssignmentController::class, 'solution'])->whereNumber('id')->middleware(['moderator', 'throttle:30,1'])->name('solutions.topics');

    // -------------------------------------------------------
    // Topics: browse questions and solutions by subject
    // -------------------------------------------------------
    Route::get('/topics', [TopicsController::class, 'index'])->name('topics');
    Route::get('/topics/{topic}', [TopicsController::class, 'show'])->name('topics.show');

    // -------------------------------------------------------
    // Verified library: answers and solutions moderators have adopted. Read live from the source.
    // -------------------------------------------------------
    Route::get('/library', [LibraryController::class, 'index'])->name('library');

    // -------------------------------------------------------
    // Profile Controller
    // -------------------------------------------------------
    Route::get('/user/profile/edit', 'App\Http\Controllers\PagesController@edit')->name('profile.edit');
    Route::get('/user/profile/{id}', 'App\Http\Controllers\PagesController@show')->name('profile.show');
    // Whether other people may see the signed-in person's achievements and public contributions. Always their own row.
    Route::post('/user/profile/achievements-visibility', [AchievementsController::class, 'updateVisibility'])->middleware('throttle:20,1')->name('profile.achievements-visibility');

    // -------------------------------------------------------
    // Dot Points: balance, rewards, redemptions. Server-to-server with the points service; always the signed-in person's own.
    // -------------------------------------------------------
    Route::get('/points', [PointsController::class, 'index'])->middleware('throttle:60,1')->name('points');
    Route::post('/points/redeem', [PointsController::class, 'redeem'])->middleware('throttle:10,1')->name('points.redeem');
    Route::post('/points/expiry-emails', [PointsController::class, 'updateExpiryEmails'])->middleware('throttle:20,1')->name('points.expiry-emails');
    // Opt-in summary emails of what the signed-in person earned (off unless they choose weekly or monthly).
    Route::post('/points/digest-emails', [PointsController::class, 'updateDigestEmails'])->middleware('throttle:20,1')->name('points.digest-emails');
    // Opt in to let Dot.Memory remember the topics the signed-in person helps with (default off; turning it off erases).
    Route::post('/points/share-expertise', [ExpertiseSharingController::class, 'update'])->middleware('throttle:10,1')->name('points.share-expertise');
    Route::post('/points/share-expertise/erase', [ExpertiseSharingController::class, 'erase'])->middleware('throttle:10,1')->name('points.share-expertise.erase');
    // Opt in to a list, for the signed-in person only, of open questions in the topics they have helped with.
    Route::post('/points/topic-suggestions', [TopicSuggestionsController::class, 'update'])->middleware('throttle:20,1')->name('points.topic-suggestions');

    // -------------------------------------------------------
    // Moderation: reports, appeals and the audit trail. Moderators only.
    // -------------------------------------------------------
    Route::get('/moderation', ModerationQueue::class)->middleware('moderator')->name('moderation');
    Route::get('/moderation/topics', ModerationTopics::class)->middleware('moderator')->name('moderation.topics');
    // The Verified library desk: Dot.Brain's recommendations, the entries, and adding one by reference.
    Route::get('/moderation/library', ModerationLibrary::class)->middleware('moderator')->name('moderation.library');
    Route::post('/moderation/library', [LibraryEntryController::class, 'store'])->middleware(['moderator', 'throttle:30,1'])->name('moderation.library.add');

    // -------------------------------------------------------
    // Sub Services
    // -------------------------------------------------------
    Route::view('/sub-services/dot-files', 'sub-services.dot-files')->name('subservices.files');
    Route::view('/sub-services/dot-docs', 'sub-services.dot-docs')->name('subservices.docs');
    Route::view('/sub-services/dot-sheets', 'sub-services.dot-sheets')->name('subservices.sheets');
    Route::view('/sub-services/dot-press', 'sub-services.dot-press')->name('subservices.press');
    Route::view('/sub-services/dot-forms', 'sub-services.dot-forms')->name('subservices.forms');
    Route::view('/sub-services/dot-engage', 'sub-services.dot-engage')->name('subservices.engage');

});

// -------------------------------------------------------
// Outside Pages
// -------------------------------------------------------
Route::get('/home', 'App\Http\Controllers\HomeController@index')->name('dashboard');
Route::get('/about', 'App\Http\Controllers\PagesController@about')->name('about');
Route::get('/contact', 'App\Http\Controllers\PagesController@contact')->name('contact');
Route::post('/contact-send', 'App\Http\Controllers\PagesController@contactSend')->name('send-contact');
Route::get('/faqs', 'App\Http\Controllers\PagesController@faqs')->name('faqs');
Route::get('/complains', 'App\Http\Controllers\PagesController@complains')->name('complains');
Route::get('/features', 'App\Http\Controllers\PagesController@features')->name('features');
// Nicer, canonical URLs for the legal pages. Jetstream's termsAndPrivacyPolicy feature also
// auto-registers /terms-of-service and /privacy-policy under route names terms.show/policy.show
// (see vendor/laravel/jetstream/routes/livewire.php) — registering those same names again here
// on a different URI would silently evict one of the two from Laravel's route name lookup table
// (RouteCollection keys allRoutes by method+URI, so same-name-different-URI routes fight over the
// name; see Illuminate\Routing\RouteCollection::refreshNameLookups()). Keeping distinct names
// avoids the collision; both URIs render the same resources/markdown/*.md source either way.
Route::get('/terms', 'App\Http\Controllers\PagesController@terms')->name('terms');
Route::get('/policy', 'App\Http\Controllers\PagesController@policy')->name('policy');
Route::get('/cookies', 'App\Http\Controllers\PagesController@cookies')->name('cookies');
Route::get('/solution-results', 'App\Http\Controllers\PagesController@solution_search_results')->name('solution_search_results');

// ===============================================================

// Liveness probe used by the deploy's smoke check and by monitoring. GET only, so
// a POST answers a small 405 instead of reaching a controller. One `select 1`: with the database down this is a
// 500 (the exception text is not shown with APP_DEBUG=false), not a green "OK".
Route::get('/up', function () {
    DB::select('select 1');

    return response('OK', 200, ['Content-Type' => 'text/plain']);
})->name('up');
