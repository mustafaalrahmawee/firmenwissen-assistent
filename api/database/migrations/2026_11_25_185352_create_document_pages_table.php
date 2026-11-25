<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The text of a document, one row per page; a Markdown file is page 1. The workspace
     * stands only at the document, so pages are always found through their document.
     */
    public function up(): void
    {
        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->mediumText('text');

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
