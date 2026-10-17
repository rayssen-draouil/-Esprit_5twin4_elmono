<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->unique()->after('role');
            }

            if (! Schema::hasColumn('users', 'telegram_linked_at')) {
                $table->timestamp('telegram_linked_at')->nullable()->after('telegram_chat_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'telegram_linked_at')) {
                $table->dropColumn('telegram_linked_at');
            }

            if (Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->dropColumn('telegram_chat_id');
            }
        });
    }
};
