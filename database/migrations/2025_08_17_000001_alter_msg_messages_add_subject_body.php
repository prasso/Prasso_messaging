<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('msg_messages')) {
            Schema::table('msg_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('msg_messages', 'subject')) {
                    $table->string('subject')->nullable()->after('type');
                }
                if (! Schema::hasColumn('msg_messages', 'body')) {
                    $table->text('body')->after('subject');
                }
                if (Schema::hasColumn('msg_messages', 'content')) {
                    $table->dropColumn('content');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('msg_messages')) {
            Schema::table('msg_messages', function (Blueprint $table) {
                if (Schema::hasColumn('msg_messages', 'body')) {
                    $table->dropColumn('body');
                }
                if (Schema::hasColumn('msg_messages', 'subject')) {
                    $table->dropColumn('subject');
                }
                if (! Schema::hasColumn('msg_messages', 'content')) {
                    $table->text('content')->nullable();
                }
            });
        }
    }
};
