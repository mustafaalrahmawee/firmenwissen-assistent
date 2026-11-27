<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per page with text; "page" is the page number in the PDF, so an empty page
     * leaves a gap instead of shifting the numbers of the pages after it.
     */
    public function up(): void
    {
        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->mediumText('text');
            $table->timestamps();

            $table->unique(['document_id', 'page']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_pages');
    }
};
