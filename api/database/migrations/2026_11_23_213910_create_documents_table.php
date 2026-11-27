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
     * SHA-256 also stops two uploads of the same file at the same moment. A new document
     * waits for its processing; "failure_reason" is set only when it failed.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('path');
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason')->nullable();
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
