<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::connection()->getDriverName() === 'pgsql' ? DB::raw('gen_random_uuid()') : null);
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('Active'); // Active, Completed, Failed
            $table->decimal('earned_amount', 15, 2)->default(0);
            $table->timestamp('taken_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_jobs');
    }
};
