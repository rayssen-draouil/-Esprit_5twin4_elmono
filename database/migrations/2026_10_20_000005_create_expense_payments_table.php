<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('project_expenses')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_payments');
    }
};
