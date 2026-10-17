<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invitations table
        Schema::create('maintenance_assignment_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_id')->constrained('maintenances')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('techniciens')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 30)->default('pending'); // pending, accepted, declined, expired, cancelled
            $table->timestamp('invited_at')->useCurrent();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->string('email_delivery_status', 30)->default('pending'); // pending, sent, failed
            $table->string('brevo_message_id')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamps();

            $table->index(['maintenance_id', 'status']);
            $table->index(['technician_id', 'status']);
            $table->index('expires_at');
        });

        // 2. Maintenances table extensions
        Schema::table('maintenances', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenances', 'technician_assignment_locked')) {
                $table->boolean('technician_assignment_locked')->default(false)->after('technician_id');
            }
            if (! Schema::hasColumn('maintenances', 'assignment_accepted_at')) {
                $table->timestamp('assignment_accepted_at')->nullable()->after('technician_assignment_locked');
            }
            if (! Schema::hasColumn('maintenances', 'completion_submitted_at')) {
                $table->timestamp('completion_submitted_at')->nullable()->after('result');
            }
            if (! Schema::hasColumn('maintenances', 'completion_report')) {
                $table->json('completion_report')->nullable()->after('completion_submitted_at');
            }
            if (! Schema::hasColumn('maintenances', 'completion_validated_at')) {
                $table->timestamp('completion_validated_at')->nullable()->after('completion_report');
            }
            if (! Schema::hasColumn('maintenances', 'completion_validated_by')) {
                $table->foreignId('completion_validated_by')->nullable()->after('completion_validated_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('maintenances', 'manager_validation_notes')) {
                $table->text('manager_validation_notes')->nullable()->after('completion_validated_by');
            }
            if (! Schema::hasColumn('maintenances', 'manager_correction_notes')) {
                $table->text('manager_correction_notes')->nullable()->after('manager_validation_notes');
            }
        });

        // 3. Techniciens table link to User
        Schema::table('techniciens', function (Blueprint $table) {
            if (! Schema::hasColumn('techniciens', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_assignment_invitations');

        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropForeign(['completion_validated_by']);
            $table->dropColumn([
                'technician_assignment_locked',
                'assignment_accepted_at',
                'completion_submitted_at',
                'completion_report',
                'completion_validated_at',
                'completion_validated_by',
                'manager_validation_notes',
                'manager_correction_notes',
            ]);
        });

        Schema::table('techniciens', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id']);
        });
    }
};
