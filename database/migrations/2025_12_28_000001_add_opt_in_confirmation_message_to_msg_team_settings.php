<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msg_team_settings', function (Blueprint $table) {
            $table->text('opt_in_confirmation_message')->nullable()->after('help_purpose');
        });
    }

    public function down(): void
    {
        Schema::table('msg_team_settings', function (Blueprint $table) {
            $table->dropColumn('opt_in_confirmation_message');
        });
    }
};
