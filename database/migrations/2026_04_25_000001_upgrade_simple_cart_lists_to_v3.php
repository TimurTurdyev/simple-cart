<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = config('simple_cart.database.table', 'simple_cart_lists');

        $columns = [
            'version' => fn (Blueprint $table) => $table->unsignedInteger('version')->default(0),
            'status' => fn (Blueprint $table) => $table->string('status', 16)->default('active'),
            'status_changed_at' => fn (Blueprint $table) => $table->timestamp('status_changed_at')->nullable(),
            'reference' => fn (Blueprint $table) => $table->string('reference')->nullable(),
            'slot' => fn (Blueprint $table) => $table->string('slot', 64)->default(''),
        ];

        foreach ($columns as $column => $define) {
            if (! Schema::hasColumn($name, $column)) {
                Schema::table($name, fn (Blueprint $table) => $define($table));
            }
        }

        if (Schema::hasIndex($name, ['owner', 'list'], 'unique')) {
            Schema::table($name, fn (Blueprint $table) => $table->dropUnique(['owner', 'list']));
        }

        if (! Schema::hasIndex($name, ['owner', 'list', 'slot'], 'unique')) {
            Schema::table($name, fn (Blueprint $table) => $table->unique(['owner', 'list', 'slot']));
        }

        if (! Schema::hasIndex($name, ['status', 'updated_at'])) {
            Schema::table($name, fn (Blueprint $table) => $table->index(['status', 'updated_at']));
        }
    }

    public function down(): void
    {
        $name = config('simple_cart.database.table', 'simple_cart_lists');

        if (Schema::hasIndex($name, ['status', 'updated_at'])) {
            Schema::table($name, fn (Blueprint $table) => $table->dropIndex(['status', 'updated_at']));
        }

        if (Schema::hasIndex($name, ['owner', 'list', 'slot'], 'unique')) {
            Schema::table($name, fn (Blueprint $table) => $table->dropUnique(['owner', 'list', 'slot']));
        }

        if (Schema::hasColumn($name, 'slot')) {
            DB::table($name)->where('slot', '!=', '')->delete();
        }

        foreach (['version', 'status', 'status_changed_at', 'reference', 'slot'] as $column) {
            if (Schema::hasColumn($name, $column)) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        if (! Schema::hasIndex($name, ['owner', 'list'], 'unique')) {
            Schema::table($name, fn (Blueprint $table) => $table->unique(['owner', 'list']));
        }
    }
};
