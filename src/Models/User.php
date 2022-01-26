<?php
/**
 * Laravel Theme System
 * @author İsa Eken <hello@Oasin.com.tr>
 * @license MIT
 */

namespaceOasin\Theme\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class User
 * @package Oasin\Theme\Models
 */
class User extends \App\User
{
    /**
     * @return HasOne|null
     */
    public function theme() : ?HasOne
    {
        return $this->hasOne('Oasin\Theme\Models\UserTheme', 'user_id', 'id');
    }
}
