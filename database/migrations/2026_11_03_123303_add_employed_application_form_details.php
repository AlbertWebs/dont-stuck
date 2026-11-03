<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guarantors', function (Blueprint $table): void {
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('work_phone', 32)->nullable();
            $table->string('work_station')->nullable();
            $table->string('residence')->nullable();
            $table->string('building_name')->nullable();
            $table->string('house_number')->nullable();
            $table->decimal('guarantee_amount', 14, 2)->nullable();
            $table->text('guarantee_amount_words')->nullable();
            $table->string('signature_name')->nullable();
            $table->date('signed_on')->nullable();
            $table->boolean('joint_and_several_accepted')->default(false);
        });

        Schema::table('collateral_items', function (Blueprint $table): void {
            $table->string('color')->nullable();
            $table->boolean('ownership_confirmed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collateral_items', function (Blueprint $table): void {
            $table->dropColumn(['color', 'ownership_confirmed']);
        });

        Schema::table('guarantors', function (Blueprint $table): void {
            $table->dropColumn([
                'email', 'address', 'work_phone', 'work_station', 'residence', 'building_name',
                'house_number', 'guarantee_amount', 'guarantee_amount_words', 'signature_name',
                'signed_on', 'joint_and_several_accepted',
            ]);
        });
    }
};
