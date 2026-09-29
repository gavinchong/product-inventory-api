<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('sku', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->index();
            $table->unsignedInteger('stock')->index();
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::create('product_supplier', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'supplier_id']);
            $table->index('supplier_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
    }
};
