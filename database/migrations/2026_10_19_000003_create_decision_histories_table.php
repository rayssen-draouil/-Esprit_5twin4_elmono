<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_histories', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type'); // 'project' or 'financement'
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('comment')->nullable();
            $table->string('action_type'); // 'submit', 'approve', 'request_changes', 'reject'
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_histories');
    }
};
