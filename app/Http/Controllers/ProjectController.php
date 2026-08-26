<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Phase;
use App\Models\PhaseBudget;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\Team;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const PHASES = [
        'Initiation',
        'Planning',
        'Execution',
        'Monitoring',
        'Closure',
    ];

    private const TYPES = [
        'Software',
        'Network & Infrastructure',
        'Training & Consultancy',
    ];

    private const STATUSES = [
        'planning',
        'active',
        'risk',
        'closed',
    ];

    /*
    |--------------------------------------------------------------------------
    | Projects
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        abort_unless(
            Auth::user()->can('view_projects'),
            403
        );

        $query = Project::with([
            'team',
            'budget',
            'phases',
            'memberRoles',
        ]);

        if ($type = $request->get('type')) {
            $query->where('project_type', $type);
        }

        $projects = $query
            ->orderByDesc('project_id')
            ->paginate(15);

        return view(
            'projects.index',
            compact('projects')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Show Project
    |--------------------------------------------------------------------------
    */

    public function show(Project $project)
    {
        abort_unless(
            Auth::user()->can('view_projects'),
            403
        );

        /*
         * Load everything required by the project page,
         * including the new expense history.
         */
        $project->load([
            'team.members.user',
            'budget',
            'expenses.creator',
            'expenses.phase',
            'phases.budget',
            'phases.expenses.creator',
            'phases.tasks.assignee',
            'deliverables',
            'changeRequests.requester',
        ]);

        /*
         * Collect all tasks from all phases.
         */
        $tasks = collect($project->phases)
            ->flatMap
            ->tasks;

        /*
         * Users who can be assigned to project tasks.
         */
        $assignableUsers = collect();

        if ($project->team) {
            $assignableUsers = $project->team->members
                ->pluck('user')
                ->filter()
                ->map(function ($u) {
                    return [
                        'id' => $u->user_id,
                        'name' => $u->full_name,
                    ];
                })
                ->values();
        }

        /*
         * Determine whether the current user can record
         * project expenses.
         *
         * A project manager/team leader can spend on their
         * own project.
         *
         * Users with manage_budgets can also record expenses.
         */
        $canRecordExpenses =
            $project->isManagedBy(Auth::user())
            || Auth::user()->can('manage_budgets');

        return view(
            'projects.show',
            compact(
                'project',
                'tasks',
                'assignableUsers',
                'canRecordExpenses'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Project
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        abort_unless(
            Auth::user()->can('create_projects'),
            403
        );

        return view('projects.create', [
            'teams' => $this->eligibleTeamsFor(Auth::user()),
            'types' => self::TYPES,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Store Project
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $user = Auth::user();

        abort_unless(
            $user->can('create_projects'),
            403
        );

        $eligibleTeamIds = $this
            ->eligibleTeamsFor($user)
            ->pluck('team_id');

        $data = $request->validate([
            'project_name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'project_type' => [
                'required',
                'in:' . implode(',', self::TYPES),
            ],

            'team_id' => [
                'required',
                Rule::in($eligibleTeamIds),
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'allocated_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $project = Project::create([
            'project_name' => $data['project_name'],
            'description' => $data['description'] ?? null,
            'project_type' => $data['project_type'],
            'team_id' => $data['team_id'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'planning',
            'created_by' => $user->user_id,
        ]);

        /*
         * Create overall project budget.
         */
        ProjectBudget::create([
            'project_id' => $project->project_id,
            'allocated_amount' => $data['allocated_amount'] ?? 0,
            'spent_amount' => 0,
            'currency' => 'ETB',
        ]);

        /*
         * Create the five project phases.
         */
        foreach (self::PHASES as $i => $phaseName) {

            $phase = Phase::create([
                'project_id' => $project->project_id,
                'phase_name' => $phaseName,
                'status' => $i === 0
                    ? 'In Progress'
                    : 'Not started',
                'sequence_order' => $i,
            ]);

            /*
             * Divide the initial project budget equally
             * between the five phases.
             */
            PhaseBudget::create([
                'phase_id' => $phase->phase_id,
                'allocated_amount' => round(
                    ($data['allocated_amount'] ?? 0) / 5
                ),
                'spent_amount' => 0,
            ]);
        }

        Activity::log(
            'Created project',
            'Project',
            $project->project_id,
            $project->project_name
        );

        if (
            $project->team->team_leader_id
            && $project->team->team_leader_id !== $user->user_id
        ) {
            Activity::notify(
                $project->team->team_leader_id,
                $user->full_name
                . ' created a new project: "'
                . $project->project_name
                . '"',
                'project'
            );
        }

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project created.');
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Project
    |--------------------------------------------------------------------------
    */

    public function edit(Project $project)
    {
        $user = Auth::user();

        abort_unless(
            $user->can('edit_projects')
            && $project->isManagedBy($user),
            403
        );

        return view('projects.edit', [
            'project' => $project->load('budget'),
            'teams' => $this->eligibleTeamsFor(
                $user,
                $project
            ),
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'canEditBudget' => $user->can(
                'manage_budgets'
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Project
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        Project $project
    ) {
        $user = Auth::user();

        abort_unless(
            $user->can('edit_projects')
            && $project->isManagedBy($user),
            403
        );

        $eligibleTeamIds = $this
            ->eligibleTeamsFor($user, $project)
            ->pluck('team_id');

        $data = $request->validate([
            'project_name' => [
                'required',
                'string',
                'max:150',
            ],

            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'project_type' => [
                'required',
                'in:' . implode(',', self::TYPES),
            ],

            'team_id' => [
                'required',
                Rule::in($eligibleTeamIds),
            ],

            'status' => [
                'required',
                'in:' . implode(',', self::STATUSES),
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'allocated_amount' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ]);

        $project->update([
            'project_name' => $data['project_name'],
            'description' => $data['description'] ?? null,
            'project_type' => $data['project_type'],
            'team_id' => $data['team_id'],
            'status' => $data['status'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Project Budget
        |--------------------------------------------------------------------------
        |
        | Only users with manage_budgets can change the
        | project budget.
        |
        | The project budget is distributed across the
        | project's five phases. Existing phase spending
        | is preserved so a phase can never end up with
        | an allocated amount below its spent amount.
        |
        */
        if (
            isset($data['allocated_amount'])
            && $project->budget
            && $user->can('manage_budgets')
        ) {
            $previous = (float) $project->budget->allocated_amount;
            $newAmount = (float) $data['allocated_amount'];

            /*
             * Load all phases and their budgets.
             */
            $phases = $project->phases()
                ->with('budget')
                ->orderBy('sequence_order')
                ->get();

            /*
             * Calculate the amount already spent across
             * all phases.
             */
            $totalPhaseSpent = $phases->sum(function ($phase) {
                return (float) optional($phase->budget)
                    ->spent_amount;
            });

            /*
             * The new project budget cannot be lower than
             * money that has already been spent.
             */
            if ($newAmount < $totalPhaseSpent) {
                return back()
                    ->withErrors([
                        'allocated_amount' =>
                            'The project budget cannot be lower than '
                            . 'the amount already spent. '
                            . 'Current spending: ETB '
                            . number_format(
                                $totalPhaseSpent,
                                2
                            ),
                    ])
                    ->withInput();
            }

            DB::transaction(function () use (
                $project,
                $phases,
                $newAmount,
                $totalPhaseSpent
            ) {
                /*
                 * 1. Update the overall project budget.
                 */
                $project->budget->update([
                    'allocated_amount' => $newAmount,
                ]);

                /*
                 * 2. Amount remaining after existing spending.
                 */
                $remainingToAllocate =
                    $newAmount - $totalPhaseSpent;

                $phaseCount = $phases->count();

                if ($phaseCount === 0) {
                    return;
                }

                /*
                 * 3. Distribute the remaining budget equally
                 *    among the phases.
                 */
                $share = round(
                    $remainingToAllocate / $phaseCount,
                    2
                );

                $allocatedSoFar = 0;

                foreach ($phases as $index => $phase) {
                    $spent = (float) optional(
                        $phase->budget
                    )->spent_amount;

                    /*
                     * Give the final phase the rounding remainder
                     * so that all phase allocations add up exactly
                     * to the project budget.
                     */
                    if ($index === $phaseCount - 1) {
                        $phaseAllocated =
                            $newAmount - $allocatedSoFar;
                    } else {
                        $phaseAllocated =
                            round($spent + $share, 2);
                    }

                    $phase->budget()->updateOrCreate(
                        [
                            'phase_id' => $phase->phase_id,
                        ],
                        [
                            'allocated_amount' =>
                                $phaseAllocated,
                            'spent_amount' => $spent,
                        ]
                    );

                    $allocatedSoFar += $phaseAllocated;
                }
            });

            /*
             * Audit log.
             */
            if ($previous !== $newAmount) {
                Activity::log(
                    'Updated project budget',
                    'Project',
                    $project->project_id,
                    $project->project_name
                    . ': ETB '
                    . number_format($previous, 2)
                    . ' → ETB '
                    . number_format($newAmount, 2)
                );
            }
        }

        Activity::log(
            'Updated project',
            'Project',
            $project->project_id,
            $project->project_name
        );

        return redirect()
            ->route('projects.show', $project)
            ->with(
                'status',
                'Project updated.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Project
    |--------------------------------------------------------------------------
    */

    public function destroy(Project $project)
    {
        $user = Auth::user();

        abort_unless(
            $user->can('delete_projects')
            && $project->isManagedBy($user),
            403
        );

        $name = $project->project_name;

        Activity::log(
            'Deleted project',
            'Project',
            $project->project_id,
            $name
        );

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with(
                'status',
                "\"{$name}\" was deleted."
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Change Requests
    |--------------------------------------------------------------------------
    */

    public function storeChangeRequest(
        Request $request,
        Project $project
    ) {
        abort_unless(
            Auth::user()->can('view_projects'),
            403
        );

        $data = $request->validate([
            'description' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $cr = ChangeRequest::create([
            'project_id' => $project->project_id,
            'requested_by' => Auth::id(),
            'description' => $data['description'],
            'status' => 'Pending',
            'requested_date' => now(),
        ]);

        Activity::log(
            'Created change request',
            'ChangeRequest',
            $cr->change_request_id,
            $data['description']
        );

        if (
            optional($project->team)->team_leader_id
        ) {
            Activity::notify(
                $project->team->team_leader_id,
                Auth::user()->full_name
                . ' filed a change request on "'
                . $project->project_name
                . '"',
                'approval'
            );
        }

        return back()->with(
            'status',
            'Change request submitted.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Team Eligibility
    |--------------------------------------------------------------------------
    */

    private function eligibleTeamsFor(
        $user,
        ?Project $editingProject = null
    ) {
        if ($user->isDirectorOrAdmin()) {
            return Team::orderBy('team_name')->get();
        }

        $led = Team::where(
            'team_leader_id',
            $user->user_id
        )
            ->orderBy('team_name')
            ->get();

        if (
            $editingProject
            && $editingProject->isManagedBy($user)
            && !$led->contains(
                'team_id',
                $editingProject->team_id
            )
        ) {
            $led->push($editingProject->team);
        }

        return $led;
    }
}