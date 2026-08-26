<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectExpense extends Model
{
    protected $table = 'project_expenses';

    protected $primaryKey = 'expense_id';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'phase_id',
        'amount',
        'description',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Project this expense belongs to.
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
     * Phase this expense was charged to.
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
     * User who recorded the expense.
     */
    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by',
            'user_id'
        );
    }
}