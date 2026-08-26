<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $primaryKey = 'project_id';

    public $timestamps = false;

    protected $fillable = [
        'project_name',
        'description',
        'project_type',
        'team_id',
        'start_date',
        'end_date',
        'status',
        'created_by',
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
     * Team responsible for this project.
     */
    public function team()
    {
        return $this->belongsTo(
            Team::class,
            'team_id',
            'team_id'
        );
    }

    /**
     * User who created the project.
     */
    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by',
            'user_id'
        );
    }

    /**
     * Project budget.
     */
    public function budget()
    {
        return $this->hasOne(
            ProjectBudget::class,
            'project_id',
            'project_id'
        );
    }

    

    /**
     * Project phases.
     */
    public function phases()
    {
        return $this->hasMany(
            Phase::class,
            'project_id',
            'project_id'
        )->orderBy('sequence_order');
    }

    /**
     * Project expenses.
     */
    public function expenses()
    {
        return $this->hasMany(
            ProjectExpense::class,
            'project_id',
            'project_id'
        )->latest('expense_date');
    }

    /**
     * Project deliverables.
     */
    public function deliverables()
    {
        return $this->hasMany(
            ProjectDeliverable::class,
            'project_id',
            'project_id'
        );
    }

    /**
     * Project change requests.
     */
    public function changeRequests()
    {
        return $this->hasMany(
            ChangeRequest::class,
            'project_id',
            'project_id'
        );
    }

    /**
     * Project member roles.
     */
    public function memberRoles()
    {
        return $this->hasMany(
            ProjectMemberRole::class,
            'project_id',
            'project_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the given user manages this project.
     *
     * The ICT Director / system administrator can manage
     * all projects, while a team's leader manages projects
     * belonging to their team.
     */
    public function isManagedBy($user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isDirectorOrAdmin()) {
            return true;
        }

        return optional($this->team)->team_leader_id === $user->user_id;
    }

    /*
    |--------------------------------------------------------------------------
    | Phase Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Return the index of the currently active phase.
     */
    public function currentPhaseIndex(): int
    {
        $phases = $this->phases()
            ->pluck('status')
            ->values();

        foreach ($phases as $i => $status) {
            if (!in_array(
                $status,
                ['Done', 'Closed', 'Completed'],
                true
            )) {
                return $i;
            }
        }

        return max(0, $phases->count() - 1);
    }
}