<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Phase extends Model
{
    protected $primaryKey = 'phase_id';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'phase_name',
        'description',
        'sequence_order',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Project this phase belongs to.
     */
    public function project()
    {
        return $this->belongsTo(
            Project::class,
            'project_id',
            'project_id'
        );
    }

    /**
     * Budget assigned to this phase.
     */
    public function budget()
    {
        return $this->hasOne(
            PhaseBudget::class,
            'phase_id',
            'phase_id'
        );
    }

    /**
     * Expenses charged to this phase.
     */
    public function expenses()
    {
        return $this->hasMany(
            ProjectExpense::class,
            'phase_id',
            'phase_id'
        )->latest('expense_date');
    }

    /**
     * Tasks belonging to this phase.
     */
    public function tasks()
    {
        return $this->hasMany(
            Task::class,
            'phase_id',
            'phase_id'
        );
    }

    /**
     * Project deliverables belonging to this phase.
     */
    public function deliverables()
    {
        return $this->hasMany(
            ProjectDeliverable::class,
            'phase_id',
            'phase_id'
        );
    }
}