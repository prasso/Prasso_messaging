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
        // Guests
        Schema::table('msg_guests', function (Blueprint $table) {
            if (Schema::hasColumn('msg_guests', 'team_id')) {
                $table->dropIndex('msg_guests_team_id_index');
                $table->dropColumn('team_id');
            }
        });

        // Messages
        Schema::table('msg_messages', function (Blueprint $table) {
            if (Schema::hasColumn('msg_messages', 'team_id')) {
                $table->dropIndex('msg_messages_team_id_index');
                $table->dropColumn('team_id');
            }
        });

        // Deliveries
        Schema::table('msg_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('msg_deliveries', 'team_id')) {
                $table->dropIndex('msg_deliveries_team_id_index');
                $table->dropColumn('team_id');
            }
        });

        // Consent events (optional table)
        if (Schema::hasTable('msg_consent_events')) {
            Schema::table('msg_consent_events', function (Blueprint $table) {
                if (Schema::hasColumn('msg_consent_events', 'team_id')) {
                    $table->dropIndex('msg_consent_events_team_id_index');
                    $table->dropColumn('team_id');
                }
            });
        }

        // Inbound messages (optional table)
        if (Schema::hasTable('msg_inbound_messages')) {
            Schema::table('msg_inbound_messages', function (Blueprint $table) {
                if (Schema::hasColumn('msg_inbound_messages', 'team_id')) {
                    $table->dropIndex('msg_inbound_messages_team_id_index');
                    $table->dropColumn('team_id');
                }
            });
        }

        // Guest<->Message pivot
        Schema::table('msg_guest_messages', function (Blueprint $table) {
            if (Schema::hasColumn('msg_guest_messages', 'team_id')) {
                $table->dropIndex('msg_guest_messages_team_id_index');
                $table->dropColumn('team_id');
            }
        });
    }
};
