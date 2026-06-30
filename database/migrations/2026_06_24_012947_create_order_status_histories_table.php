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
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::connection()->getDriverName() === 'pgsql' ? DB::raw('gen_random_uuid()') : null);
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status');
            $table->string('changed_by_role'); // Role apa yang ubah? ex : 'Buyer', 'Seller', 'Driver'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
