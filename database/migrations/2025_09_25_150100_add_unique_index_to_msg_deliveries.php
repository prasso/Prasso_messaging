<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msg_deliveries', function (Blueprint $table) {
            // Prevent duplicate deliveries for the same message/recipient/channel
            $table->unique(['msg_message_id', 'recipient_type', 'recipient_id', 'channel'], 'msg_deliveries_unique_message_recipient_channel');
        });
    }

    public function down(): void
    {
        Schema::table('msg_deliveries', function (Blueprint $table) {
            $table->dropUnique('msg_deliveries_unique_message_recipient_channel');
        });
    }
};
