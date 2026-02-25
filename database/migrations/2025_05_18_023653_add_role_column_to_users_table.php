<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role')->nullable()->after('email');
    });
    
    // Optionally set default roles if you have existing users
    DB::statement("UPDATE users SET role = 'user'");
    // Set admins if you know their IDs
    DB::statement("UPDATE users SET role = 'admin' WHERE id IN (1, 2, 3)");
}
    /**
     * Reverse the migrations.
     */
public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('role');
    });
}
};
