<?php

use App\Services\Platforms\CatalogueSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R-50: the central platform catalogue and the business entitlement core. Until now a platform being registered and
 * switched on was the only thing checked before a person opened it. These tables hold what each platform is
 * (catalogue), how it may be sold (plans and bundles, empty until prices are approved), which company may use which
 * platform (entitlements, the only record that says so), who inside the company (user access), the append-only
 * history of both, and the outbox that tells each platform when an entitlement changes.
 *
 * The catalogue is filled here, from database/data/platform_catalogue.php, so a deploy needs no manual step; the same
 * sync runs later as `platforms:sync` and never overwrites a row that already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_catalogue')) {
            Schema::create('platform_catalogue', function (Blueprint $table) {
                $table->id();
                $table->string('key', 40)->unique();
                $table->string('name', 80);
                $table->string('tagline', 160)->nullable();
                $table->text('description')->nullable();
                $table->text('benefit')->nullable();
                $table->text('audience')->nullable();
                $table->json('capabilities')->nullable();
                $table->json('categories')->nullable();
                $table->json('connects_with')->nullable();
                $table->string('operational_status', 20)->default('operational'); // operational | degraded | maintenance | unavailable | development | not_launched
                $table->boolean('discoverable')->default(true);
                $table->boolean('is_core')->default(false);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_plans')) {
            Schema::create('platform_plans', function (Blueprint $table) {
                $table->id();
                $table->string('platform_key', 40);
                $table->string('slug', 60);
                $table->string('name', 120);
                $table->string('model', 20); // free | included | subscription | per_user | usage | enterprise
                $table->string('interval', 10)->nullable(); // month | year
                $table->unsignedBigInteger('amount_minor')->nullable();
                $table->string('currency', 3)->nullable();
                $table->unsignedInteger('included_users')->nullable();
                $table->unsignedBigInteger('extra_user_amount_minor')->nullable();
                $table->unsignedSmallInteger('trial_days')->nullable();
                $table->json('limits')->nullable();
                $table->json('features')->nullable();
                $table->string('tax_treatment', 40)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->boolean('active')->default(false);
                $table->timestamps();

                $table->unique(['platform_key', 'slug']);
            });
        }

        if (! Schema::hasTable('platform_bundles')) {
            Schema::create('platform_bundles', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('amount_minor')->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('interval', 10)->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->boolean('active')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_bundle_items')) {
            Schema::create('platform_bundle_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_id')->constrained('platform_bundles')->cascadeOnDelete();
                $table->string('platform_key', 40);
                $table->string('plan_slug', 60)->nullable();

                $table->unique(['bundle_id', 'platform_key']);
            });
        }

        if (! Schema::hasTable('platform_entitlements')) {
            Schema::create('platform_entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('platform_key', 40);
                $table->string('status', 20); // pending | active | trial | payment_required | expired | suspended | cancelled
                $table->string('source', 20); // core | free | unpriced_free | included | complimentary | trial | paid | enterprise
                $table->foreignId('plan_id')->nullable()->constrained('platform_plans')->nullOnDelete();
                $table->foreignId('bundle_id')->nullable()->constrained('platform_bundles')->nullOnDelete();
                $table->string('access_mode', 10)->default('everyone'); // everyone | selected
                $table->timestamp('started_at')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('current_period_ends_at')->nullable();
                $table->timestamp('grace_until')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('reference', 120)->nullable(); // the payment service's subscription reference
                // users.id is a legacy unsigned INT (increments), so the referencing columns are unsigned INT too.
                $table->unsignedInteger('granted_by')->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->unique(['team_id', 'platform_key']);
                $table->index(['platform_key', 'status']);
                $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('platform_user_access')) {
            Schema::create('platform_user_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('platform_key', 40);
                $table->unsignedInteger('user_id');
                $table->string('platform_role', 40)->nullable();
                $table->unsignedInteger('granted_by')->nullable();
                $table->timestamps();

                $table->unique(['team_id', 'platform_key', 'user_id'], 'platform_user_access_unique');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('platform_entitlement_events')) {
            Schema::create('platform_entitlement_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('platform_key', 40);
                $table->unsignedInteger('actor_id')->nullable(); // null: the system or the command line
                $table->string('type', 40);
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->json('details')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['team_id', 'platform_key', 'id'], 'platform_entitlement_events_lookup');
                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('platform_catalogue_events')) {
            Schema::create('platform_catalogue_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('actor_id')->nullable(); // null: done from the command line
                $table->string('subject_type', 20); // platform | plan | bundle | administrator
                $table->string('subject_key', 191);
                $table->string('type', 40);
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['subject_type', 'subject_key']);
                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('platform_recommendation_dismissals')) {
            Schema::create('platform_recommendation_dismissals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
                $table->string('platform_key', 40);
                $table->unsignedInteger('user_id')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['team_id', 'platform_key'], 'platform_rec_dismissals_unique');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('entitlement_outbox')) {
            Schema::create('entitlement_outbox', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 64)->unique();
                $table->string('platform_key', 40);
                $table->string('team_uuid', 36);
                $table->json('payload');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->string('last_error', 255)->nullable();
                $table->timestamps();

                $table->index(['sent_at', 'failed_at', 'next_attempt_at'], 'entitlement_outbox_due');
                $table->index(['platform_key', 'team_uuid']);
            });
        }

        // Every platform a person can see gets its catalogue entry now; an entry that already exists is left alone.
        app(CatalogueSync::class)->sync();
    }

    public function down(): void
    {
        foreach ([
            'entitlement_outbox', 'platform_recommendation_dismissals', 'platform_catalogue_events', 'platform_entitlement_events',
            'platform_user_access', 'platform_entitlements', 'platform_bundle_items', 'platform_bundles', 'platform_plans', 'platform_catalogue',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
