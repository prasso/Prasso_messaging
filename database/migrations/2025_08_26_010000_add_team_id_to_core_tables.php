<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guests
        Schema::table('msg_guests', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('id')->index();
        });

        // Messages
        Schema::table('msg_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('id')->index();
        });

        // Deliveries
        Schema::table('msg_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('id')->index();
        });

        // Consent events
        if (Schema::hasTable('msg_consent_events')) {
            Schema::table('msg_consent_events', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id')->index();
            });
        }

        // Inbound messages
        if (Schema::hasTable('msg_inbound_messages')) {
            Schema::table('msg_inbound_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id')->index();
            });
        }

        // Guest<->Message pivot
        Schema::table('msg_guest_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('msg_guests', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
        Schema::table('msg_messages', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
        Schema::table('msg_deliveries', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
        if (Schema::hasTable('msg_consent_events')) {
            Schema::table('msg_consent_events', function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }
        if (Schema::hasTable('msg_inbound_messages')) {
            Schema::table('msg_inbound_messages', function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }
        Schema::table('msg_guest_messages', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};
