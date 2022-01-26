<?php
/**
 * Laravel Theme System
 * @author İsa Eken <hello@Oasin.com.tr>
 * @license MIT
 */

namespace Oasin\Theme\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class UserTheme
 * @package Oasin\Theme\Models
 */
class UserTheme extends Model
{
    /**
     * @var string $table
     */
    protected $table = 'user_themes';

    /**
     * @var string[] $fillable
     */
    protected $fillable = [
        'theme_id',
        'user_id',
    ];

    /**
     * @var string[] $casts
     */
    protected $casts = [
        'theme_id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * @return HasOne|null
     */
    protected function theme() : ?HasOne
    {
        return $this->hasOne('Oasin\Theme\Models\Theme', 'id', 'theme_id');
    }

    /**
     * @return HasOne|null
     */
    protected function user() : ?HasOne
    {
        return $this->hasOne('Oasin\Theme\Models\User', 'id', 'user_id');
    }
}
