<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing accounts are not assigned to a workspace: the migration stops instead,
     * and the database has to be rebuilt with migrate:fresh.
     */
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException(
                'Es gibt schon Konten ohne Arbeitsbereich. Datenbank mit "php artisan migrate:fresh" neu aufbauen '
                .'und den Demo-Zugang mit "php artisan app:create-demo-user" neu anlegen.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('tenant_id')->after('id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
