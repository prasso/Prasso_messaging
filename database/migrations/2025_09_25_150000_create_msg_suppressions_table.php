<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msg_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type'); // user | guest | member
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel'); // email | sms
            $table->string('reason')->nullable(); // unsubscribed | bounced | complaint | manual | other
            $table->string('source')->nullable(); // provider name or admin action
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['recipient_type', 'recipient_id', 'channel'], 'msg_suppressions_unique_recipient_channel');
            $table->index(['recipient_type', 'recipient_id']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msg_suppressions');
    }
};
