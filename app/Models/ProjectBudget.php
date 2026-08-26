<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectBudget extends Model
{
    protected $table = 'project_budgets';

    protected $primaryKey = 'project_budget_id';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'allocated_amount',
        'spent_amount',
        'currency',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    /**
     * Project this budget belongs to.
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
     * Calculate the percentage of the budget that has been spent.
     *
     * This method is used by:
     * - projects/index.blade.php
     * - budgets/index.blade.php
     * - projects/show.blade.php
     */
    public function utilisationPercent(): float
    {
        $allocated = (float) $this->allocated_amount;
        $spent = (float) $this->spent_amount;

        if ($allocated <= 0) {
            return 0;
        }

        return min(
            100,
            round(($spent / $allocated) * 100, 2)
        );
    }

    /**
     * Alias using the American spelling.
     */
    public function utilizationPercent(): float
    {
        return $this->utilisationPercent();
    }

    /**
     * Amount remaining in the budget.
     */
    public function remainingAmount(): float
    {
        return max(
            0,
            (float) $this->allocated_amount
            - (float) $this->spent_amount
        );
    }

    /**
     * Attribute version:
     *
     * $budget->remaining_amount
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->remainingAmount();
    }

    /**
     * Attribute version:
     *
     * $budget->utilisation_percentage
     */
    public function getUtilisationPercentageAttribute(): float
    {
        return $this->utilisationPercent();
    }
}