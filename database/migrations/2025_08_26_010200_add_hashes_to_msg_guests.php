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
        // Drop indexes by name before dropping columns
        Schema::table('msg_guests', function (Blueprint $table) {
            if (Schema::hasColumn('msg_guests', 'phone_hash')) {
                $table->dropIndex('msg_guests_phone_hash_index');
                $table->dropColumn('phone_hash');
            }
            if (Schema::hasColumn('msg_guests', 'email_hash')) {
                $table->dropIndex('msg_guests_email_hash_index');
                $table->dropColumn('email_hash');
            }
        });
    }
};
