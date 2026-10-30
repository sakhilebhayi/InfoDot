<?php

use App\Http\Controllers\AchievementsController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\ExpertiseSharingController;
use App\Http\Controllers\Knowledge\PickerController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibraryEntryController;
use App\Http\Controllers\NoticesController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PlatformLaunchController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\ReputationController;
use App\Http\Controllers\ReputationRingsController;
use App\Http\Controllers\ResourceOpenController;
use App\Http\Controllers\SeminarInvoicesController;
use App\Http\Controllers\SeminarModerationController;
use App\Http\Controllers\SeminarP2pController;
use App\Http\Controllers\SeminarPaymentsModerationController;
use App\Http\Controllers\SeminarPayoutsController;
use App\Http\Controllers\SeminarRoomController;
use App\Http\Controllers\SeminarsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TopicAssignmentController;
use App\Http\Controllers\TopicsController;
use App\Http\Controllers\TopicSuggestionsController;
use App\Http\Controllers\UxFrictionController;
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

Route::post('/csp-report', CspReportController::class)->middleware('throttle:60,1,csp-report')->name('csp.report');
Route::get('/ready', ReadinessController::class)->middleware('throttle:60,1,ready')->name('ready');
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// The public pages a crawler may index (R-17 §13). Everything else is behind sign-in.
Route::get('/sitemap.xml', SitemapController::class)->middleware('throttle:30,1,sitemap')->name('sitemap');

// RFC 9116 disclosure file (R-19). The web server serves the physical copy first; this route is the
// safety net on hosts whose docroot copy lags a deploy.
Route::get('/.well-known/security.txt', function () {
    abort_unless(is_file(public_path('.well-known/security.txt')), 404);

    return response(file_get_contents(public_path('.well-known/security.txt')), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->middleware('throttle:30,1,security-txt')->name('security.txt');

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
    Route::get('/go/{platform}', PlatformLaunchController::class)->middleware('throttle:30,1,platform.launch')->name('platform.launch');

    // -------------------------------------------------------
    // People: the members of the company the person is acting for
    // -------------------------------------------------------
    Route::get('/people', [PeopleController::class, 'index'])->name('people');

    // -------------------------------------------------------
    // Composer lookups (R-16): @mention autocomplete and the recommend-a-solution picker
    // -------------------------------------------------------
    Route::get('/mentions/search', [PickerController::class, 'mentions'])->middleware('throttle:60,1,mentions-search')->name('mentions.search');
    Route::get('/solutions/picker', [PickerController::class, 'solutions'])->middleware('throttle:60,1,solutions-picker')->name('solutions.picker');
    Route::post('/knowledge/similar', [PickerController::class, 'similar'])->middleware('throttle:30,1,knowledge-similar')->name('knowledge.similar');

    // -------------------------------------------------------
    // Attachments (R-15): uploads for questions, solutions, answers and comments, and the only door to the files
    // -------------------------------------------------------
    Route::post('/attachments', [AttachmentController::class, 'store'])->middleware('throttle:30,1,attachments-upload')->name('attachments.store');
    Route::delete('/attachments/{attachment:uuid}', [AttachmentController::class, 'destroy'])->middleware('throttle:60,1,attachments-destroy')->name('attachments.destroy');
    Route::get('/attachments/{attachment:uuid}/thumb', [AttachmentController::class, 'thumb'])->name('attachments.thumb');
    Route::get('/attachments/{attachment:uuid}', [AttachmentController::class, 'show'])->name('attachments.show');

    // // -------------------------------------------------------
    // Solutions Controller
    // -------------------------------------------------------
    Route::get('/solutions', 'App\Http\Controllers\Solutions\SolutionsController@index')->name('solutions');
    Route::get('/solution/create', 'App\Http\Controllers\Solutions\SolutionsController@create')->name('add');
    Route::post('/solution/add', 'App\Http\Controllers\Solutions\SolutionsController@add_solution')->middleware('throttle:10,1,solutions-add')->name('solutions.add');
    Route::get('/solution/view/{id}', 'App\Http\Controllers\Solutions\SolutionsController@view_solution')->name('solutions.view');

    // -------------------------------------------------------
    // Question Controller
    // -------------------------------------------------------
    Route::get('/questions', 'App\Http\Controllers\Questions\QuestionsController@index')->name('questions');
    Route::get('/questions/ask', 'App\Http\Controllers\Questions\QuestionsController@seek')->name('seek');
    Route::post('/questions/add', 'App\Http\Controllers\Questions\QuestionsController@add_question')->middleware('throttle:10,1,questions.add')->name('questions.add');
    Route::get('/question/view/{qid}', 'App\Http\Controllers\Questions\QuestionsController@view')->name('questions.view');
    Route::post('/question/{qid}/topics', [TopicAssignmentController::class, 'question'])->whereNumber('qid')->middleware(['moderator', 'throttle:30,1,questions.topics'])->name('questions.topics');
    Route::post('/solution/{id}/topics', [TopicAssignmentController::class, 'solution'])->whereNumber('id')->middleware(['moderator', 'throttle:30,1,solutions.topics'])->name('solutions.topics');

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
    Route::post('/user/profile/achievements-visibility', [AchievementsController::class, 'updateVisibility'])->middleware('throttle:20,1,profile.achievements-visibility')->name('profile.achievements-visibility');
    // Whether the person's specialist badges are shown (public unless they hide them). Always their own row.
    Route::post('/user/profile/badges-visibility', [AchievementsController::class, 'updateBadges'])->middleware('throttle:20,1,profile.badges-visibility')->name('profile.badges-visibility');

    // Knowledge reputation: private progress (404 unless REPUTATION_ENABLED). Appeals are answered by moderators.
    Route::get('/progress', [ReputationController::class, 'show'])->middleware('throttle:60,1,reputation')->name('reputation');
    Route::post('/progress/preference', [ReputationController::class, 'preference'])->middleware('throttle:10,1,reputation.preference')->name('reputation.preference');
    Route::get('/progress/export', [ReputationController::class, 'export'])->middleware('throttle:10,1,reputation.export')->name('reputation.export');
    Route::post('/progress/appeal', [ReputationController::class, 'appeal'])->middleware('throttle:10,1,reputation.appeal')->name('reputation.appeal');
    Route::get('/moderation/reputation', [ReputationController::class, 'appeals'])->middleware(['moderator', 'throttle:30,1,moderation.reputation'])->name('moderation.reputation');
    Route::post('/moderation/reputation/{appeal}', [ReputationController::class, 'resolve'])->whereNumber('appeal')->middleware(['moderator', 'throttle:30,1,moderation.reputation.resolve'])->name('moderation.reputation.resolve');
    // Advisory ring flags (accounts accepting each other's answers). Moderators only; the people in a flag are not told.
    Route::get('/moderation/rings', [ReputationRingsController::class, 'index'])->middleware(['moderator', 'throttle:30,1,moderation.rings'])->name('moderation.rings');
    Route::post('/moderation/rings/{flag}', [ReputationRingsController::class, 'review'])->whereNumber('flag')->middleware(['moderator', 'throttle:30,1,moderation.rings.review'])->name('moderation.rings.review');
    // Free seminars: any verified person may host one; the host's own join link is shown only to registered people in the window.
    Route::get('/seminars', [SeminarsController::class, 'index'])->name('seminars');
    Route::get('/seminars/payouts', [SeminarPayoutsController::class, 'index'])->middleware('throttle:30,1,seminars.payouts')->name('seminars.payouts');
    Route::post('/seminars/payouts', [SeminarPayoutsController::class, 'store'])->middleware('throttle:5,1,seminars.payouts.store')->name('seminars.payouts.store');
    Route::post('/seminars/payouts/agreement', [SeminarPayoutsController::class, 'agreement'])->middleware('throttle:10,1,seminar-agreement')->name('seminars.payouts.agreement');
    Route::post('/seminars/payouts/seller', [SeminarPayoutsController::class, 'seller'])->middleware('throttle:10,1,seminars.payouts.seller')->name('seminars.payouts.seller');
    Route::get('/seminars/invoices', [SeminarInvoicesController::class, 'index'])->middleware('throttle:30,1,seminars.invoices')->name('seminars.invoices');
    Route::get('/seminars/invoices/{invoice}', [SeminarInvoicesController::class, 'show'])->whereNumber('invoice')->middleware('throttle:30,1,seminars.invoice')->name('seminars.invoice');
    Route::get('/moderation/seminar-payments', [SeminarPaymentsModerationController::class, 'index'])->middleware(['moderator', 'throttle:30,1,moderation.seminar-payments'])->name('moderation.seminar-payments');
    Route::get('/seminars/link-check', [SeminarsController::class, 'linkCheck'])->middleware('throttle:20,1,seminar-link-check')->name('seminars.link-check');
    Route::get('/seminars/new', [SeminarsController::class, 'create'])->name('seminars.create');
    Route::post('/seminars', [SeminarsController::class, 'store'])->middleware('throttle:10,1,seminars.store')->name('seminars.store');
    Route::get('/seminars/{seminar}', [SeminarsController::class, 'show'])->whereNumber('seminar')->name('seminars.show');
    Route::get('/seminars/{seminar}/edit', [SeminarsController::class, 'edit'])->whereNumber('seminar')->name('seminars.edit');
    Route::put('/seminars/{seminar}', [SeminarsController::class, 'update'])->whereNumber('seminar')->middleware('throttle:20,1,seminars.update')->name('seminars.update');
    Route::post('/seminars/{seminar}/cancel', [SeminarsController::class, 'cancel'])->whereNumber('seminar')->middleware('throttle:10,1,seminars.cancel')->name('seminars.cancel');
    Route::post('/seminars/{seminar}/register', [SeminarsController::class, 'register'])->whereNumber('seminar')->middleware('throttle:30,1,seminars.register')->name('seminars.register');
    Route::post('/seminars/{seminar}/unregister', [SeminarsController::class, 'unregister'])->whereNumber('seminar')->middleware('throttle:30,1,seminars.unregister')->name('seminars.unregister');
    Route::post('/seminars/{seminar}/join', [SeminarsController::class, 'join'])->whereNumber('seminar')->middleware('throttle:30,1,seminars.join')->name('seminars.join');
    Route::post('/seminars/{seminar}/attendance/{person}', [SeminarsController::class, 'attendance'])->whereNumber(['seminar', 'person'])->middleware('throttle:60,1,seminars.attendance')->name('seminars.attendance');
    Route::post('/seminars/{seminar}/refund/{person}', [SeminarsController::class, 'refund'])->whereNumber(['seminar', 'person'])->middleware('throttle:20,1,seminars.refund')->name('seminars.refund');
    Route::get('/seminars/{seminar}/receipt', [SeminarsController::class, 'receipt'])->whereNumber('seminar')->middleware('throttle:30,1,seminars.receipt')->name('seminars.receipt');
    Route::get('/seminars/{seminar}/room', [SeminarRoomController::class, 'show'])->whereNumber('seminar')->middleware('throttle:30,1,seminars.room')->name('seminars.room');
    Route::post('/seminars/{seminar}/room/token', [SeminarRoomController::class, 'token'])->whereNumber('seminar')->middleware('throttle:30,1,seminars.room.token')->name('seminars.room.token');
    Route::post('/seminars/{seminar}/room/remove/{person}', [SeminarRoomController::class, 'remove'])->whereNumber(['seminar', 'person'])->middleware('throttle:30,1,seminars.room.remove')->name('seminars.room.remove');
    Route::post('/seminars/p2p/speedtest', [SeminarP2pController::class, 'speedtest'])->middleware('throttle:12,1,p2p-speedtest')->name('seminars.p2p.speedtest');
    Route::get('/seminars/p2p/plan', [SeminarP2pController::class, 'plan'])->middleware('throttle:30,1,p2p-plan')->name('seminars.p2p.plan');
    Route::get('/seminars/{seminar}/small-room', [SeminarP2pController::class, 'show'])->whereNumber('seminar')->middleware('throttle:30,1,p2p-show')->name('seminars.p2p');
    Route::post('/seminars/{seminar}/p2p/join', [SeminarP2pController::class, 'join'])->whereNumber('seminar')->middleware('throttle:30,1,p2p-join')->name('seminars.p2p.join');
    Route::post('/seminars/{seminar}/p2p/poll', [SeminarP2pController::class, 'poll'])->whereNumber('seminar')->middleware('throttle:150,1,p2p-poll')->name('seminars.p2p.poll');
    Route::post('/seminars/{seminar}/p2p/signal', [SeminarP2pController::class, 'signal'])->whereNumber('seminar')->middleware('throttle:240,1,p2p-signal')->name('seminars.p2p.signal');
    Route::post('/seminars/{seminar}/p2p/ready', [SeminarP2pController::class, 'ready'])->whereNumber('seminar')->middleware('throttle:30,1,p2p-ready')->name('seminars.p2p.ready');
    Route::post('/seminars/{seminar}/p2p/report', [SeminarP2pController::class, 'report'])->whereNumber('seminar')->middleware('throttle:30,1,p2p-report')->name('seminars.p2p.report');
    Route::post('/seminars/{seminar}/p2p/leave', [SeminarP2pController::class, 'leave'])->whereNumber('seminar')->middleware('throttle:30,1,p2p-leave')->name('seminars.p2p.leave');
    Route::post('/seminars/{seminar}/p2p/chat', [SeminarP2pController::class, 'chat'])->whereNumber('seminar')->middleware('throttle:30,1,p2p-chat')->name('seminars.p2p.chat');
    Route::post('/seminars/{seminar}/p2p/remove/{person}', [SeminarP2pController::class, 'remove'])->whereNumber(['seminar', 'person'])->middleware('throttle:30,1,p2p-remove')->name('seminars.p2p.remove');
    Route::post('/seminars/{seminar}/report-link', [SeminarsController::class, 'reportLink'])->whereNumber('seminar')->middleware('throttle:10,1,seminar-report-link')->name('seminars.report-link');
    Route::post('/seminars/{seminar}/report', [SeminarsController::class, 'report'])->whereNumber('seminar')->middleware('throttle:10,1,seminar-report')->name('seminars.report');
    Route::get('/moderation/seminars', [SeminarModerationController::class, 'index'])->middleware(['moderator', 'throttle:30,1,mod-seminars'])->name('moderation.seminars');
    Route::post('/moderation/seminars/{seminar}/remove', [SeminarModerationController::class, 'remove'])->whereNumber('seminar')->middleware(['moderator', 'throttle:20,1,mod-seminar-remove'])->name('moderation.seminars.remove');
    Route::post('/moderation/seminar-reports/{report}/dismiss', [SeminarModerationController::class, 'dismiss'])->whereNumber('report')->middleware(['moderator', 'throttle:30,1,mod-seminar-dismiss'])->name('moderation.seminars.dismiss');
    Route::get('/account/seminar-data', [SeminarsController::class, 'exportMyData'])->middleware('throttle:10,1,seminar-export')->name('seminars.export');
    Route::post('/seminars/{seminar}/removal-appeal', [SeminarsController::class, 'appealRemoval'])->whereNumber('seminar')->middleware('throttle:5,1,seminar-appeal')->name('seminars.appeal');
    Route::post('/moderation/seminar-appeals/{appeal}', [SeminarModerationController::class, 'decideAppeal'])->whereNumber('appeal')->middleware(['moderator', 'throttle:30,1,mod-seminar-appeal'])->name('moderation.seminars.appeal');
    Route::post('/moderation/seminars/{seminar}/bar/{person}', [SeminarModerationController::class, 'bar'])->whereNumber(['seminar', 'person'])->middleware(['moderator', 'throttle:30,1,mod-seminar-bar'])->name('moderation.seminars.bar');
    Route::post('/seminars/{seminar}/feedback', [SeminarsController::class, 'feedback'])->whereNumber('seminar')->middleware('throttle:20,1,seminars.feedback')->name('seminars.feedback');

    // In-app notifications about the signed-in person's own work (an answer to their question, an accepted answer, a verified solution, work added to the library).
    Route::get('/notifications', [NoticesController::class, 'index'])->middleware('throttle:60,1,notices')->name('notices');
    Route::get('/notifications/{id}/open', [NoticesController::class, 'open'])->whereNumber('id')->middleware('throttle:60,1,notices.open')->name('notices.open');
    Route::post('/notifications/read-all', [NoticesController::class, 'readAll'])->middleware('throttle:20,1,notices.read-all')->name('notices.read-all');
    Route::post('/notifications/preference', [NoticesController::class, 'preference'])->middleware('throttle:20,1,notices.preference')->name('notices.preference');

    // -------------------------------------------------------
    // Dot Points: balance, rewards, redemptions. Server-to-server with the points service; always the signed-in person's own.
    // -------------------------------------------------------
    Route::get('/points', [PointsController::class, 'index'])->middleware('throttle:60,1,points')->name('points');
    Route::post('/points/redeem', [PointsController::class, 'redeem'])->middleware('throttle:10,1,points.redeem')->name('points.redeem');
    Route::post('/points/expiry-emails', [PointsController::class, 'updateExpiryEmails'])->middleware('throttle:20,1,points.expiry-emails')->name('points.expiry-emails');
    // Opt-in summary emails of what the signed-in person earned (off unless they choose weekly or monthly).
    Route::post('/points/digest-emails', [PointsController::class, 'updateDigestEmails'])->middleware('throttle:20,1,points.digest-emails')->name('points.digest-emails');
    // Opt in to let Dot.Memory remember the topics the signed-in person helps with (default off; turning it off erases).
    Route::post('/points/share-expertise', [ExpertiseSharingController::class, 'update'])->middleware('throttle:10,1,points.share-expertise')->name('points.share-expertise');
    Route::post('/points/share-expertise/erase', [ExpertiseSharingController::class, 'erase'])->middleware('throttle:10,1,points.share-expertise.erase')->name('points.share-expertise.erase');
    // Opt in to a list, for the signed-in person only, of open questions in the topics they have helped with.
    Route::post('/points/topic-suggestions', [TopicSuggestionsController::class, 'update'])->middleware('throttle:20,1,points.topic-suggestions')->name('points.topic-suggestions');

    // -------------------------------------------------------
    // Moderation: reports, appeals and the audit trail. Moderators only.
    // -------------------------------------------------------
    Route::get('/moderation', ModerationQueue::class)->middleware('moderator')->name('moderation');
    Route::get('/moderation/friction', [UxFrictionController::class, 'index'])->middleware(['moderator', 'throttle:30,1,moderation.friction'])->name('moderation.friction');
    Route::get('/moderation/topics', ModerationTopics::class)->middleware('moderator')->name('moderation.topics');
    // The Verified library desk: Dot.Brain's recommendations, the entries, and adding one by reference.
    Route::get('/moderation/library', ModerationLibrary::class)->middleware('moderator')->name('moderation.library');
    Route::post('/moderation/library', [LibraryEntryController::class, 'store'])->middleware(['moderator', 'throttle:30,1,moderation.library.add'])->name('moderation.library.add');

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
Route::post('/contact-send', 'App\Http\Controllers\PagesController@contactSend')->middleware('throttle:5,10,send-contact')->name('send-contact');
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
Route::get('/security', 'App\Http\Controllers\PagesController@security')->name('security');
Route::get('/solution-results', 'App\Http\Controllers\PagesController@solution_search_results')->middleware('throttle:30,1,solution-results')->name('solution_search_results');

// ===============================================================

// Liveness probe used by the deploy's smoke check and by monitoring. GET only, so
// a POST answers a small 405 instead of reaching a controller. One `select 1`: with the database down this is a
// 500 (the exception text is not shown with APP_DEBUG=false), not a green "OK".
Route::get('/up', function () {
    DB::select('select 1');

    return response('OK', 200, ['Content-Type' => 'text/plain']);
})->name('up');

// A short link to a seminar that always opens (it goes to the seminar page, signing in first when needed).
Route::get('/j/{code}', [SeminarsController::class, 'short'])->where('code', '[A-Za-z0-9]{6,12}')->middleware('throttle:60,1,seminar-short')->name('seminars.short');
