<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msg_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('msg_message_id')->constrained('msg_messages')->onDelete('cascade');
            $table->string('recipient_type'); // user | guest
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel'); // email | sms | push | inapp
            $table->string('status')->default('queued'); // queued | sent | delivered | failed | skipped
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msg_deliveries');
    }
};
