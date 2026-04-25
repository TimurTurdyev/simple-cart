<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('simple_cart.database.table', 'simple_cart_lists');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('owner');
            $table->string('list');
            $table->json('payload');
            $table->timestamps();

            $table->unique(['owner', 'list']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('simple_cart.database.table', 'simple_cart_lists'));
    }
};
