<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msg_consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('msg_guest_id')->constrained('msg_guests')->onDelete('cascade');
            $table->string('action'); // opt_in | opt_out
            $table->string('method')->nullable(); // keyword | web | form | api
            $table->string('source')->nullable(); // incoming number, form name, etc.
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['msg_guest_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msg_consent_events');
    }
};
