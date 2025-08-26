<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msg_team_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->unique();
            $table->string('sms_from')->nullable();
            $table->string('help_business_name')->nullable();
            $table->string('help_purpose')->nullable();
            $table->string('help_contact_phone')->nullable();
            $table->string('help_contact_email')->nullable();
            $table->string('help_contact_website')->nullable();
            $table->string('help_disclaimer')->nullable();
            $table->unsignedInteger('rate_batch_size')->nullable();
            $table->unsignedInteger('rate_batch_interval_seconds')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msg_team_settings');
    }
};
