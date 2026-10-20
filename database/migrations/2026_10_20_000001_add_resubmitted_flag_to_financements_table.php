<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financements', function (Blueprint $table) {
            if (! Schema::hasColumn('financements', 'resubmitted_after_changes_requested')) {
                $table->boolean('resubmitted_after_changes_requested')->default(false)->after('validated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financements', function (Blueprint $table) {
            if (Schema::hasColumn('financements', 'resubmitted_after_changes_requested')) {
                $table->dropColumn('resubmitted_after_changes_requested');
            }
        });
    }
};
