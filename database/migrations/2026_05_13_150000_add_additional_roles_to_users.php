<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an `additional_roles` JSON column to users — a secondary list of
 * roles that GRANT extra permissions on top of the user's primary `role`.
 *
 * Use cases:
 *   - View-only access scoped to a location:  ['view-khi'] or ['view-lhr']
 *   - View-only access to everything:         ['view-all']
 *   - A salesperson who is also an admin-view: ['view-all']
 *
 * The existing `role` column is unchanged so all current role checks
 * (isAdmin / isSalesHead / CheckRole middleware / etc.) keep working
 * exactly as before. Additional roles are an ADDITIVE extension.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('additional_roles')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('additional_roles');
        });
    }
};
