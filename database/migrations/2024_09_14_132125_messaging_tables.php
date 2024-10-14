<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Guests Table
        Schema::create('msg_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        // Messages Table
        Schema::create('msg_messages', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // text, email, voice
            $table->text('content');
            $table->timestamps();
        });

        // Workflows Table
        Schema::create('msg_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Workflow Steps Table
        Schema::create('msg_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('msg_workflows_id')->constrained('msg_workflows')->onDelete('cascade');
            $table->foreignId('msg_messages_id')->constrained('msg_messages')->onDelete('cascade');
            $table->integer('delay_in_minutes')->default(0);
            $table->timestamps();
        });

        // Guest Messages Table (Logs sent messages)
        Schema::create('msg_guest_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('msg_guest_id')->constrained('msg_guests')->onDelete('cascade');
            $table->foreignId('msg_message_id')->constrained('msg_messages')->onDelete('cascade');
            $table->boolean('is_sent')->default(false);
            $table->timestamps();
        });

        // Contests, Surveys, Polls Table
        Schema::create('msg_engagements', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // contest, survey, poll
            $table->string('title');
            $table->text('description');
            $table->timestamps();
        });

        // Engagement Responses Table
        Schema::create('msg_engagement_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('msg_engagement_id')->constrained('msg_engagements')->onDelete('cascade');
            $table->foreignId('msg_guest_id')->constrained('msg_guests')->onDelete('cascade');
            $table->text('response');
            $table->timestamps();
        });

        // Automated Campaigns Table
        Schema::create('msg_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description');
            $table->timestamps();
        });

        // Campaign Messages Table
        Schema::create('msg_campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('msg_campaigns')->onDelete('cascade');
            $table->foreignId('message_id')->constrained('msg_messages')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       //
    }
};