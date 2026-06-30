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
        Schema::create('buyer_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::connection()->getDriverName() === 'pgsql' ? DB::raw('gen_random_uuid()') : null);
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('address_details');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_addresses');
    }
};
