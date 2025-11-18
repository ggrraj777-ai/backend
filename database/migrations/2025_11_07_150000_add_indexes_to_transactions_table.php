<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $self = $this;

        Schema::table('transactions', function (Blueprint $table) use ($self) {
            if (!$self->indexExists('transactions', 'transactions_created_at_idx')) {
                $table->index('created_at', 'transactions_created_at_idx');
            }

            if (!$self->indexExists('transactions', 'transactions_user_created_idx')) {
                $table->index(['user_id', 'created_at'], 'transactions_user_created_idx');
            }

            if (!$self->indexExists('transactions', 'transactions_account_idx')) {
                $table->index('account', 'transactions_account_idx');
            }

            if (!$self->indexExists('transactions', 'transactions_type_idx')) {
                $table->index('transaction_type', 'transactions_type_idx');
            }

            if (!$self->indexExists('transactions', 'transactions_trx_ref_idx')) {
                $table->index('trx_ref_id', 'transactions_trx_ref_idx');
            }
        });
    }

    public function down(): void
    {
        $self = $this;

        Schema::table('transactions', function (Blueprint $table) use ($self) {
            if ($self->indexExists('transactions', 'transactions_trx_ref_idx')) {
                $table->dropIndex('transactions_trx_ref_idx');
            }

            if ($self->indexExists('transactions', 'transactions_type_idx')) {
                $table->dropIndex('transactions_type_idx');
            }

            if ($self->indexExists('transactions', 'transactions_account_idx')) {
                $table->dropIndex('transactions_account_idx');
            }

            if ($self->indexExists('transactions', 'transactions_user_created_idx')) {
                $table->dropIndex('transactions_user_created_idx');
            }

            if ($self->indexExists('transactions', 'transactions_created_at_idx')) {
                $table->dropIndex('transactions_created_at_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = config('database.default');

        if (in_array($connection, ['sqlite', 'sqlite3'], true)) {
            $result = DB::select("PRAGMA index_list('" . str_replace("'", "''", $table) . "')");

            foreach ($result as $row) {
                $name = $row->name ?? ($row->seq ?? null);
                if (($row->name ?? null) === $index || $name === $index) {
                    return true;
                }
            }

            return false;
        }

        $schema = config('database.connections.' . $connection . '.database');

        $result = DB::select(
            'SELECT COUNT(1) as count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$schema, $table, $index]
        );

        return !empty($result) && $result[0]->count > 0;
    }
};


