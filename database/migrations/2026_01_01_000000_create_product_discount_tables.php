<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_discount_actions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('content')->nullable();
            $table->boolean('actively')->default(false);
            $table->dateTime('start');
            $table->dateTime('end');
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('stop_sort')->nullable();
            $table->unsignedInteger('stop_priority')->nullable();
            $table->timestamps();
            $table->index(['actively', 'start', 'end']);
        });
        Schema::create('product_discount_doings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('applie');
            $table->string('product_id')->nullable();
            $table->string('type_discount');
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('condition_id');
            $table->string('value')->nullable();
            $table->string('comparison');
            $table->timestamps();
        });
        Schema::create('product_discount_action_doing', function (Blueprint $table) {
            $table->foreignId('action_id')->constrained('product_discount_actions')->cascadeOnDelete();
            $table->foreignId('doing_id')->constrained('product_discount_doings')->cascadeOnDelete();
            $table->primary(['action_id', 'doing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discount_action_doing');
        Schema::dropIfExists('product_discount_doings');
        Schema::dropIfExists('product_discount_actions');
    }
};
