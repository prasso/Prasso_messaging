<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msg_deliveries', function (Blueprint $table) {
            $table->timestamp('send_at')->nullable()->after('metadata');
            $table->index('send_at');
        });
    }

    public function down(): void
    {
        Schema::table('msg_deliveries', function (Blueprint $table) {
            $table->dropIndex(['send_at']);
            $table->dropColumn('send_at');
        });
    }
};
