<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * App\User
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $firstName
 * @property string $lastName
 * @property string $email
 * @property string|null $phone
 * @property string|null $image
 * @property int $suspended
 * @property int $preferredLanguage_id
 * @property string|null $deleted_at
 * @property string|null $company
 * @property int $eula
 * @property string|null $eula_timestamp
 * @property int|null $view_cpm
 * @property int|null $company_id
 * @property string|null $resetHash
 * @property int $ytd_logins
 * @property int $ytd_queries
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCompany($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEula($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereEulaTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User wherePreferredLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereResetHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereSuspended($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereViewCpm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereYtdLogins($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\User whereYtdQueries($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

//    public function getImageAttribute()
//    {
//        return $this->image ? 'api/'.$this->image : null;
//    }
}
