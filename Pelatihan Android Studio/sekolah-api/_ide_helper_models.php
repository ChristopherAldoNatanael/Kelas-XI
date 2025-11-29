<?php

/**
 * Laravel IDE Helper - Models
 * This file provides IDE auto-completion for Eloquent Model methods
 * These methods are provided by Laravel's Eloquent ORM
 */

namespace Illuminate\Database\Eloquent {

    /**
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model whereIn($column, $values, $boolean = 'and', $not = false)
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model whereDate($column, $operator, $value = null, $boolean = 'and')
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model with($relations, $callback = null)
     * @method static \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Collection|static[]|static|null find($id, $columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Model|static findOrFail($id, $columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Model|static firstOrFail($columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Model|static create(array $attributes = [])
     * @method static \Illuminate\Database\Eloquent\Model|static updateOrCreate(array $attributes, array $values = [])
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model query()
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model select($columns = ['*'])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method static \Illuminate\Database\Eloquent\Collection|static[] get($columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Collection|static[] all($columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model orderBy($column, $direction = 'asc')
     * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model limit($value)
     * @method bool save(array $options = [])
     * @method bool update(array $attributes = [], array $options = [])
     * @method bool delete()
     * @method array getAttributes()
     * @method \Laravel\Sanctum\NewAccessToken createToken(string $name, array $abilities = ['*'])
     * @property int $id
     * @property string $email
     * @property string $nama
     * @property string $role
     */
    class Model {}
}

namespace App\Models {

    /**
     * App\Models\User
     * @method static \Illuminate\Database\Eloquent\Builder|User where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static \Illuminate\Database\Eloquent\Builder|User whereIn($column, $values, $boolean = 'and', $not = false)
     * @method static User|null find($id, $columns = ['*'])
     * @method static User findOrFail($id, $columns = ['*'])
     * @method static User firstOrFail($columns = ['*'])
     * @method static User create(array $attributes = [])
     * @method static User updateOrCreate(array $attributes, array $values = [])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method bool update(array $attributes = [], array $options = [])
     * @method \Laravel\Sanctum\NewAccessToken createToken(string $name, array $abilities = ['*'])
     * @property int $id
     * @property string $email
     * @property string $nama
     * @property string $role
     * @property string $password
     * @property \Carbon\Carbon|null $last_login_at
     */
    class User extends \Illuminate\Database\Eloquent\Model {}

    /**
     * App\Models\Teacher
     * @method static \Illuminate\Database\Eloquent\Builder|Teacher where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static Teacher|null find($id, $columns = ['*'])
     * @method static Teacher findOrFail($id, $columns = ['*'])
     * @method static Teacher create(array $attributes = [])
     * @method static Teacher updateOrCreate(array $attributes, array $values = [])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method static \Illuminate\Database\Eloquent\Builder|Teacher select($columns = ['*'])
     */
    class Teacher extends \Illuminate\Database\Eloquent\Model {}

    /**
     * App\Models\Subject
     * @method static \Illuminate\Database\Eloquent\Builder|Subject where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static Subject|null find($id, $columns = ['*'])
     * @method static Subject findOrFail($id, $columns = ['*'])
     * @method static Subject create(array $attributes = [])
     * @method static Subject updateOrCreate(array $attributes, array $values = [])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method static \Illuminate\Database\Eloquent\Builder|Subject select($columns = ['*'])
     */
    class Subject extends \Illuminate\Database\Eloquent\Model {}

    /**
     * App\Models\ClassModel
     * @method static \Illuminate\Database\Eloquent\Builder|ClassModel where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static ClassModel|null find($id, $columns = ['*'])
     * @method static ClassModel findOrFail($id, $columns = ['*'])
     * @method static ClassModel create(array $attributes = [])
     * @method static ClassModel updateOrCreate(array $attributes, array $values = [])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method static \Illuminate\Database\Eloquent\Builder|ClassModel select($columns = ['*'])
     */
    class ClassModel extends \Illuminate\Database\Eloquent\Model {}

    /**
     * App\Models\Classroom
     * @method static \Illuminate\Database\Eloquent\Builder|Classroom where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static Classroom|null find($id, $columns = ['*'])
     * @method static Classroom findOrFail($id, $columns = ['*'])
     * @method static Classroom create(array $attributes = [])
     * @method static Classroom updateOrCreate(array $attributes, array $values = [])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method static \Illuminate\Database\Eloquent\Builder|Classroom select($columns = ['*'])
     */
    class Classroom extends \Illuminate\Database\Eloquent\Model {}

    /**
     * App\Models\Schedule
     * @method static \Illuminate\Database\Eloquent\Builder|Schedule where($column, $operator = null, $value = null, $boolean = 'and')
     * @method static \Illuminate\Database\Eloquent\Builder|Schedule whereIn($column, $values, $boolean = 'and', $not = false)
     * @method static \Illuminate\Database\Eloquent\Builder|Schedule whereDate($column, $operator, $value = null, $boolean = 'and')
     * @method static \Illuminate\Database\Eloquent\Builder|Schedule with($relations, $callback = null)
     * @method static Schedule|null find($id, $columns = ['*'])
     * @method static Schedule findOrFail($id, $columns = ['*'])
     * @method static Schedule create(array $attributes = [])
     * @method static Schedule updateOrCreate(array $attributes, array $values = [])
     * @method static int count($columns = '*')
     * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
     * @method static \Illuminate\Database\Eloquent\Builder|Schedule select($columns = ['*'])
     * @method static \Illuminate\Database\Eloquent\Builder|Schedule query()
     */
    class Schedule extends \Illuminate\Database\Eloquent\Model {}
}
