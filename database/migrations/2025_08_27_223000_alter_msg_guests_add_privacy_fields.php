<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('msg_guests', function (Blueprint $table) {
            if (!Schema::hasColumn('msg_guests', 'do_not_contact')) {
                $table->boolean('do_not_contact')->default(false)->after('is_subscribed');
            }
            if (!Schema::hasColumn('msg_guests', 'anonymized_at')) {
                $table->timestamp('anonymized_at')->nullable()->after('subscription_status_updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('msg_guests', function (Blueprint $table) {
            if (Schema::hasColumn('msg_guests', 'do_not_contact')) {
                $table->dropColumn('do_not_contact');
            }
            if (Schema::hasColumn('msg_guests', 'anonymized_at')) {
                $table->dropColumn('anonymized_at');
            }
        });
    }
};
