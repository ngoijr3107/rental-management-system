<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null means this user *is* the account owner.
            $table->foreignId('account_owner_id')->nullable()->after('id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('owner')->after('phone');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index(['account_owner_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_owner_id']);
            $table->dropColumn(['account_owner_id', 'role', 'is_active', 'last_login_at']);
        });
    }
};
