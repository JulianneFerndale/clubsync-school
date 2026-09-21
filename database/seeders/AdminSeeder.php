<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@sccpag.edu.ph'],
            [
                'first_name'    => 'Admin',
                'last_name'     => 'ClubSync',
                'edp_number'    => '000000',
                'password'      => Hash::make('clubsync'),
                'mobile_number' => null,
                'department_id' => null,
                'course_id'     => null,
                'is_admin'      => true,
            ]
        );
    }
}
