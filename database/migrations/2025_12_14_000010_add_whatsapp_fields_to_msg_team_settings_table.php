<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msg_team_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_enabled')->default(false)->after('sms_from');
            $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_enabled');
            $table->string('whatsapp_business_account_id')->nullable()->after('whatsapp_phone_number_id');
            $table->text('whatsapp_access_token')->nullable()->after('whatsapp_business_account_id');
        });
    }

    public function down(): void
    {
        
    }
};
