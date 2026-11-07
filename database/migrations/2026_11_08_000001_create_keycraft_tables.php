<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('buyer')->index();
        });

        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['case', 'pcb', 'plate', 'stabilizer', 'switch', 'keycap'])->index();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->string('format', 20)->nullable()->index();
            $table->json('specs');
            $table->timestamps();
            $table->index('price');
        });

        Schema::create('marketplace_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();
            $table->string('marketplace');
            $table->string('url', 2048);
            $table->unsignedBigInteger('price');
            $table->dateTime('price_updated_at');
            $table->timestamps();
        });

        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('budget');
            $table->timestamps();
        });

        Schema::create('build_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('build_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['build_id', 'component_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('build_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new')->index();
            $table->unsignedBigInteger('total');
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('build_items');
        Schema::dropIfExists('builds');
        Schema::dropIfExists('marketplace_offers');
        Schema::dropIfExists('components');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
