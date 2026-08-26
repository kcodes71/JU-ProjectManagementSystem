<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseBudget extends Model
{
    protected $table = 'phase_budgets';

    protected $primaryKey = 'phase_budget_id';

    public $timestamps = false;

    protected $fillable = [
        'phase_id',
        'allocated_amount',
        'spent_amount',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Phase this budget belongs to.
     */
    public function phase()
    {
        return $this->belongsTo(
            Phase::class,
            'phase_id',
            'phase_id'
        );
    }

    /**
     * Project through the phase.
     */
    public function project()
    {
        return $this->hasOneThrough(
            Project::class,
            Phase::class,
            'phase_id',
            'project_id',
            'phase_id',
            'project_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Amount remaining in this phase budget.
     */
    public function getRemainingAmountAttribute()
    {
        return max(
            0,
            (float) $this->allocated_amount -
            (float) $this->spent_amount
        );
    }
}