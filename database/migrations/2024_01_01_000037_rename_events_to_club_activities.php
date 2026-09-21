<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('events', 'club_activities');
    }

    public function down(): void
    {
        Schema::rename('club_activities', 'events');
    }
};
