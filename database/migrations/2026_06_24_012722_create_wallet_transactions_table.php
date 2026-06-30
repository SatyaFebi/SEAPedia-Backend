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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::connection()->getDriverName() === 'pgsql' ? DB::raw('gen_random_uuid()') : null);
            $table->foreignUuid('wallet_id')->constrained('buyer_wallets')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type'); // 'TOPUP', 'PAYMENT', 'REFUND'
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
