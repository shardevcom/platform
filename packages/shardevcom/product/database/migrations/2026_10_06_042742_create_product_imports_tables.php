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
        Schema::create('product_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('format')->default('xlsx');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('successful_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('granted_permissions')->nullable();
            $table->json('error_summary')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
        });

        Schema::create('product_import_rows', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_id');
            $table->unsignedInteger('row_number');
            $table->json('data');
            $table->string('status')->default('pending'); // pending, success, failed
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('import_id')->references('id')->on('product_imports')->onDelete('cascade');
            $table->index(['import_id', 'status']);
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_import_rows');
        Schema::dropIfExists('product_imports');
    }
};