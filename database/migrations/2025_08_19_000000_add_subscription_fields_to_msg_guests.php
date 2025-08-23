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
        Schema::table('msg_guests', function (Blueprint $table) {
            $table->dropColumn([
                'is_subscribed',
                'last_message_at',
                'subscription_status_updated_at'
            ]);
            
            $table->dropIndex(['phone', 'is_subscribed']);
        });
    }
};
