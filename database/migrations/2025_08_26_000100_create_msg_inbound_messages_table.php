<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msg_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('msg_guest_id')->nullable()->index();
            $table->string('from', 32)->index();
            $table->string('to', 32)->nullable()->index();
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->string('provider_message_id', 64)->nullable()->unique();
            $table->timestamp('received_at')->index();
            $table->json('raw')->nullable();
            $table->timestamps();

            // No foreign key to avoid coupling across apps; soft link by id
            // $table->foreign('msg_guest_id')->references('id')->on('msg_guests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msg_inbound_messages');
    }
};
