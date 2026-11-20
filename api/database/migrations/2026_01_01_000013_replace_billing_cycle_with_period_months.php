<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment terms are negotiated, not chosen from a menu — four months is as
 * legitimate as three. An integer number of months expresses every term the
 * fixed enum could, plus the ones it could not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_period_months')->default(1)->after('billing_day');
        });

        // Carry across anything already recorded under the old enum.
        foreach ([
            'monthly' => 1,
            'quarterly' => 3,
            'semi_annual' => 6,
            'annual' => 12,
        ] as $cycle => $months) {
            DB::table('leases')->where('billing_cycle', $cycle)->update([
                'billing_period_months' => $months,
            ]);
        }

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->string('billing_cycle')->default('monthly')->after('billing_day');
        });

        foreach ([1 => 'monthly', 3 => 'quarterly', 6 => 'semi_annual', 12 => 'annual'] as $months => $cycle) {
            DB::table('leases')->where('billing_period_months', $months)->update(['billing_cycle' => $cycle]);
        }

        Schema::table('leases', function (Blueprint $table) {
            $table->dropColumn('billing_period_months');
        });
    }
};
