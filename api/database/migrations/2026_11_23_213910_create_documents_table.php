<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A workspace holds each content only once: the unique index on workspace and
     * SHA-256 also stops two uploads of the same file at the same moment.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('format', 16);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('path');
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->unique(['tenant_id', 'sha256']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
