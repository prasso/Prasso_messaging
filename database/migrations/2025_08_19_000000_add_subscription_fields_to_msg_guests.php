<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('msg_guests', function (Blueprint $table) {
            $table->boolean('is_subscribed')->default(true)->after('phone');
            $table->timestamp('last_message_at')->nullable()->after('is_subscribed');
            $table->timestamp('subscription_status_updated_at')->nullable()->after('last_message_at');
            
            // Add index for faster lookups
            $table->index(['phone', 'is_subscribed']);
        });
    }

    public function down()
    {
        // Drop index before dropping columns and use explicit index name
        Schema::table('msg_guests', function (Blueprint $table) {
            if (Schema::hasColumn('msg_guests', 'is_subscribed')) {
                // Laravel default index name for composite index: {table}_{columns}_index
                $table->dropIndex('msg_guests_phone_is_subscribed_index');
            }
        });
        Schema::table('msg_guests', function (Blueprint $table) {
            if (Schema::hasColumn('msg_guests', 'subscription_status_updated_at')) {
                $table->dropColumn('subscription_status_updated_at');
            }
            if (Schema::hasColumn('msg_guests', 'last_message_at')) {
                $table->dropColumn('last_message_at');
            }
            if (Schema::hasColumn('msg_guests', 'is_subscribed')) {
                $table->dropColumn('is_subscribed');
            }
        });
    }
};
