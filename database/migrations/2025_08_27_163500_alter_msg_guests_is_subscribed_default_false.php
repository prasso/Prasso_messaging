<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Set DB default to false for future inserts, do not modify existing data
        // Works across common SQL dialects used by Laravel
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE msg_guests ALTER COLUMN is_subscribed SET DEFAULT 0");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE msg_guests ALTER COLUMN is_subscribed SET DEFAULT false");
        } elseif ($driver === 'sqlite') {
            // SQLite does not support altering column defaults easily; no-op for default.
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE msg_guests ALTER COLUMN is_subscribed SET DEFAULT 1");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE msg_guests ALTER COLUMN is_subscribed SET DEFAULT true");
        } elseif ($driver === 'sqlite') {
            // no-op
        }
    }
};
