<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', fn (Blueprint $t) => $t->timestamp('recovery_until')->nullable());
        Schema::table('webhook_events', function (Blueprint $t) {
            $t->timestamp('failed_at')->nullable();
            $t->string('error_code')->nullable();
        });
        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $action) {
                DB::unprepared('CREATE TRIGGER charges_no_'.strtolower($action)." BEFORE {$action} ON generation_charges FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Generation charges are immutable'");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS charges_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS charges_no_delete');
        }
        Schema::table('generations', fn (Blueprint $t) => $t->dropColumn('recovery_until'));
        Schema::table('webhook_events', fn (Blueprint $t) => $t->dropColumn(['failed_at', 'error_code']));
    }
};
