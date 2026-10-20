<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'validation_status')) {
                $table->string('validation_status')->default('draft')->after('status');
            }
            if (! Schema::hasColumn('projects', 'validation_comment')) {
                $table->text('validation_comment')->nullable()->after('validation_status');
            }
            if (! Schema::hasColumn('projects', 'validated_by')) {
                $table->foreignId('validated_by')->nullable()->after('validation_comment')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('projects', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'validated_at')) {
                $table->dropColumn(['validated_at', 'validated_by', 'validation_comment', 'validation_status']);
            }
        });
    }
};
