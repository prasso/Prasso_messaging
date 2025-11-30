<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('msg_inbound_messages')) {
            Schema::table('msg_inbound_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('msg_inbound_messages', 'msg_delivery_id')) {
                    $table->unsignedBigInteger('msg_delivery_id')->nullable()->after('msg_guest_id')->index();
                    // Note: No foreign key constraint to avoid coupling across packages
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('msg_inbound_messages')) {
            Schema::table('msg_inbound_messages', function (Blueprint $table) {
                if (Schema::hasColumn('msg_inbound_messages', 'msg_delivery_id')) {
                    $table->dropColumn('msg_delivery_id');
                }
            });
        }
    }
};
