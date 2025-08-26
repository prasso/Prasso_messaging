<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msg_guests', function (Blueprint $table) {
            $table->string('phone_hash', 64)->nullable()->after('phone')->index();
            $table->string('email_hash', 64)->nullable()->after('email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('msg_guests', function (Blueprint $table) {
            $table->dropIndex(['phone_hash']);
            $table->dropColumn('phone_hash');
            $table->dropIndex(['email_hash']);
            $table->dropColumn('email_hash');
        });
    }
};
