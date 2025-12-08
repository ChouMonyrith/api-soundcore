<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::firstOrCreate([
            'name' => 'admin'
        ]);

        $producer = Role::firstOrCreate([
            'name' => 'producer'
        ]);

        $customer = Role::firstOrCreate([
            'name' => 'customer'
        ]);

        $permissions = [
            //Producer permissions
            'upload products',
            'edit own products',
            'delete own products',
            'view sales dashboard',
            
            //Admin permissions
            'approve products',
            'reject products',
            'delete any product',
            'manage categories',
            'manage users',
            'manage roles',
            'manage permissions',
            
            //Customer permissions
            'buy products',
            'download purchased products',
            'write reviews'   

        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission
            ]);
        }

        $admin->syncPermissions(Permission::all());

        $producer->syncPermissions([
           'upload products',
            'edit own products',
            'delete own products', 
            'view sales dashboard',
        ]);

        $customer->syncPermissions([
            'buy products',
            'download purchased products',
            'write reviews'
        ]);
    }
}
