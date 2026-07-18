<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('source_file_hash')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('customers_found')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('status', ['completed', 'failed'])->default('completed');
            $table->text('error')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users');
            $table->index(['uploaded_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_imports');
    }
};
