<?php

use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Auth\EcosystemAuthController;
use App\Livewire\EcosystemWidget;
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

Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle'])->name('auth.ecosystem');

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
    Route::post('/questions/add', 'App\Http\Controllers\Questions\QuestionsController@add_question')->name('questions.add');
    Route::get('/question/view/{qid}', 'App\Http\Controllers\Questions\QuestionsController@view')->name('questions.view');

    // -------------------------------------------------------
    // Profile Controller
    // -------------------------------------------------------
    Route::get('/user/profile/edit', 'App\Http\Controllers\PagesController@edit')->name('profile.edit');
    Route::get('/user/profile/{id}', 'App\Http\Controllers\PagesController@show')->name('profile.show');

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
Route::get('/terms', 'App\Http\Controllers\PagesController@terms')->name('terms');
Route::get('/solution-results', 'App\Http\Controllers\PagesController@solution_search_results')->name('solution_search_results');

// ===============================================================













