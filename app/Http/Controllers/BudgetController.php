<?php

namespace App\Http\Controllers;

use App\Models\PhaseBudget;
use App\Models\Project;
use App\Models\ProjectExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Budget overview
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $projects = Project::with([
            'budget',
            'phases.budget',
        ])
            ->whereHas('budget')
            ->get();

        return view(
            'budgets.index',
            compact('projects')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Record project expense
    |--------------------------------------------------------------------------
    |
    | Every expense belongs to:
    |
    | Project
    |     ↓
    | Phase
    |     ↓
    | Expense
    |
    | When an expense is recorded:
    |
    | PhaseBudget.spent_amount
    |          +
    | ProjectBudget.spent_amount
    |
    | are updated together.
    |
    */

    public function storeExpense(
        Request $request,
        Project $project
    ) {
        $user = Auth::user();

        /*
         * Only the project manager or a user with
         * budget-management permission can record expenses.
         */
        abort_unless(
            $project->isManagedBy($user)
            || $user->can('manage_budgets'),
            403
        );

        $data = $request->validate([
            'phase_id' => [
                'required',
                'exists:phases,phase_id',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'description' => [
                'required',
                'string',
                'max:255',
            ],

            'expense_date' => [
                'required',
                'date',
            ],
        ]);

        /*
         * Make sure the selected phase actually belongs
         * to this project.
         */
        $phase = $project->phases()
            ->where('phase_id', $data['phase_id'])
            ->firstOrFail();

        /*
         * Make sure both budgets exist.
         */
        $projectBudget = $project->budget;

        $phaseBudget = $phase->budget;

        abort_unless(
            $projectBudget && $phaseBudget,
            422
        );

        $amount = (float) $data['amount'];

        $projectAllocated =
            (float) $projectBudget->allocated_amount;

        $projectSpent =
            (float) $projectBudget->spent_amount;

        $projectRemaining =
            $projectAllocated - $projectSpent;

        $phaseAllocated =
            (float) $phaseBudget->allocated_amount;

        $phaseSpent =
            (float) $phaseBudget->spent_amount;

        $phaseRemaining =
            $phaseAllocated - $phaseSpent;

        /*
         * Do not allow the expense to exceed the
         * overall project budget.
         */
        if ($amount > $projectRemaining) {
            return back()
                ->withErrors([
                    'amount' =>
                        'This expense exceeds the remaining project budget. '
                        . 'Remaining: ETB '
                        . number_format(
                            max(0, $projectRemaining),
                            2
                        ),
                ])
                ->withInput();
        }

        /*
         * Do not allow the expense to exceed the
         * selected phase budget.
         */
        if ($amount > $phaseRemaining) {
            return back()
                ->withErrors([
                    'amount' =>
                        'This expense exceeds the remaining budget for '
                        . $phase->phase_name
                        . '. Remaining: ETB '
                        . number_format(
                            max(0, $phaseRemaining),
                            2
                        ),
                ])
                ->withInput();
        }

        /*
         * Everything below happens inside one database
         * transaction.
         *
         * This prevents the project budget being updated
         * while the phase budget fails, or vice versa.
         */
        DB::transaction(function () use (
            $project,
            $phase,
            $projectBudget,
            $phaseBudget,
            $data,
            $amount,
            $user
        ) {

            /*
             * 1. Create the actual expense record.
             */
            ProjectExpense::create([
                'project_id' => $project->project_id,
                'phase_id' => $phase->phase_id,
                'amount' => $amount,
                'description' => $data['description'],
                'expense_date' => $data['expense_date'],
                'created_by' => $user->user_id,
            ]);

            /*
             * 2. Increase phase spending.
             */
            $phaseBudget->increment(
                'spent_amount',
                $amount
            );

            /*
             * 3. Increase project spending.
             */
            $projectBudget->increment(
                'spent_amount',
                $amount
            );
        });

        /*
         * Activity/audit log.
         */
        if (class_exists(\App\Support\Activity::class)) {
            \App\Support\Activity::log(
                'Recorded project expense',
                'Project',
                $project->project_id,
                $project->project_name
                . ' — '
                . $phase->phase_name
                . ' — ETB '
                . number_format($amount, 2)
            );
        }

        return back()->with(
            'status',
            'Expense recorded successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Phase budget details
    |--------------------------------------------------------------------------
    */

    public function phaseBudget(
        Project $project,
        $phase
    ) {
        $user = Auth::user();

        abort_unless(
            $user->can('view_projects'),
            403
        );

        $phase = $project->phases()
            ->with([
                'budget',
                'expenses.creator',
            ])
            ->where('phase_id', $phase)
            ->firstOrFail();

        return view(
            'budgets.phase',
            compact(
                'project',
                'phase'
            )
        );
    }
}