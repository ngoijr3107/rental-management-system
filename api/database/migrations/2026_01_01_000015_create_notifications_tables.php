<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the landlord and staff see in the app.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('body', 500);
            $table->string('link')->nullable();
            $table->string('severity')->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['account_owner_id', 'read_at']);
            // Stops the same reminder being raised twice for the same subject.
            $table->string('dedupe_key')->nullable();
            $table->unique(['account_owner_id', 'dedupe_key']);
        });

        // What goes out to tenants: SMS or email, queued then sent.
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');            // sms | email
            $table->string('recipient');
            $table->string('type');
            $table->text('body');
            $table->string('status')->default('queued'); // queued | sent | failed
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->timestamps();

            $table->index(['account_owner_id', 'status']);
            $table->unique(['account_owner_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
        Schema::dropIfExists('alerts');
    }
};
