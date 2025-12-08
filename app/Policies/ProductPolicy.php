<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    // public function create(User $user): bool
    // {
    //     return;
    // }


    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Product $product): bool
    {
        // \Illuminate\Support\Facades\Log::info('ProductPolicy update check', [
        //     'user_id' => $user->id,
        //     'product_id' => $product->id,
        //     'producer_profile_id' => $product->producer_profile_id,
        //     'producer_exists' => $product->producer ? 'yes' : 'no',
        //     'producer_user_id' => $product->producer ? $product->producer->user_id : null,
        // ]);
        return $product->producer && $user->id === $product->producer->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Product $product): bool
    {
        return $product->producer && $user->id === $product->producer->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Product $product): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}
