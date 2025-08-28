<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('msg_team_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('msg_team_settings', 'verification_status')) {
                $table->string('verification_status')->nullable()->after('rate_batch_interval_seconds'); // values: verified|pending|rejected|suspended
            }
            if (!Schema::hasColumn('msg_team_settings', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_status');
            }
            if (!Schema::hasColumn('msg_team_settings', 'verification_notes')) {
                $table->text('verification_notes')->nullable()->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('msg_team_settings', function (Blueprint $table) {
            if (Schema::hasColumn('msg_team_settings', 'verification_status')) {
                $table->dropColumn('verification_status');
            }
            if (Schema::hasColumn('msg_team_settings', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
            if (Schema::hasColumn('msg_team_settings', 'verification_notes')) {
                $table->dropColumn('verification_notes');
            }
        });
    }
};
