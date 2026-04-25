<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_filename');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->nullable();
            $table->integer('processed_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->json('validation_errors')->nullable();
            $table->string('processed_file_path')->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
