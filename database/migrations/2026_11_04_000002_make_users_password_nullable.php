<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * OAuth sign-in creates users without a password; the column must accept NULL.
     * Fresh installs already get a nullable column from the base migration.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `password` VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `password` VARCHAR(255) NOT NULL');
        }
    }
};
