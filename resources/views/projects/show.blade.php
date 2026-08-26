@extends('layouts.app')

@section('title', $project->project_name)

@section('crumb')
  <a
    class="link-small"
    style="cursor:pointer;"
    href="{{ route('projects.index') }}"
  >
    Projects
  </a>
  <b>/ {{ $project->project_name }}</b>
@endsection

@section('content')

@if (session('status'))
  <div class="status-alert">
    {{ session('status') }}
  </div>
@endif

@if ($errors->any())
  <div
    class="status-alert"
    style="
      margin-bottom:18px;
      border-color:var(--danger-soft);
      color:var(--danger);
    "
  >
    <strong>Please fix the following:</strong>

    <ul style="margin:6px 0 0 18px;">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif


{{-- =========================================================
     PROJECT HEADER
========================================================= --}}

<div x-data="{ showCR: false }">

  <div class="page-head">

    <div>

      <h1>
        {{ $project->project_name }}
      </h1>

      <div class="page-sub">
        {{ $project->project_type }}
        ·
        {{ optional($project->team)->team_name }} Team
        ·
        Started
        {{ optional($project->start_date)->format('d M Y') }}
      </div>

    </div>


    <div style="display:flex; gap:8px;">

      <button
        type="button"
        class="btn btn-ghost"
        @click="showCR = !showCR"
      >
        Log Change Request
      </button>


      @if (
        auth()->user()->can('edit_projects')
        && $project->isManagedBy(auth()->user())
      )

        <a
          href="{{ route('projects.edit', $project) }}"
          class="btn btn-primary"
        >
          Edit Project
        </a>

      @endif

    </div>

  </div>


  {{-- Change request form --}}

  <div
    class="card card-pad"
    x-show="showCR"
    x-cloak
    x-transition
    style="margin-bottom:18px;"
  >

    <form
      method="POST"
      action="{{ route(
        'projects.changeRequests.store',
        $project
      ) }}"
    >

      @csrf

      <div
        class="form-field"
        style="margin-bottom:12px;"
      >

        <label for="cr_description">
          What's the change you're requesting?
        </label>

        <textarea
          id="cr_description"
          name="description"
          required
          placeholder="e.g. Extend go-live by two weeks to accommodate UAT feedback"
        ></textarea>

      </div>


      <div style="display:flex; gap:10px;">

        <button
          type="submit"
          class="btn btn-accent"
        >
          Submit request
        </button>

        <button
          type="button"
          class="btn btn-ghost"
          @click="showCR = false"
        >
          Cancel
        </button>

      </div>

    </form>

  </div>

</div>


{{-- =========================================================
     PHASE RAIL
========================================================= --}}

<div
  class="card card-pad"
  style="margin-bottom:18px;"
>

  @include(
    'partials.phase-rail',
    [
      'currentIndex' => $project->currentPhaseIndex(),
      'mini' => false
    ]
  )

</div>


{{-- =========================================================
     PROJECT TABS
========================================================= --}}

<div x-data="{ tab: 'tasks' }">

  <div class="tabs">

    <div
      class="tab"
      :class="{ active: tab === 'tasks' }"
      @click="tab = 'tasks'"
    >
      Tasks
    </div>


    <div
      class="tab"
      :class="{ active: tab === 'deliverables' }"
      @click="tab = 'deliverables'"
    >
      Deliverables
    </div>


    <div
      class="tab"
      :class="{ active: tab === 'budget' }"
      @click="tab = 'budget'"
    >
      Budget
    </div>


    <div
      class="tab"
      :class="{ active: tab === 'changes' }"
      @click="tab = 'changes'"
    >
      Change Requests
    </div>

  </div>


  {{-- =======================================================
       TASKS
  ======================================================== --}}

  <div x-show="tab === 'tasks'">

    @if (
      auth()->user()->can('create_tasks')
      && $project->isManagedBy(auth()->user())
    )

      <div
        x-data="{ showNewTask: false }"
        style="margin-bottom:16px;"
      >

        <div
          style="
            display:flex;
            justify-content:flex-end;
            margin-bottom:0;
          "
        >

          <button
            type="button"
            class="btn btn-accent"
            @click="showNewTask = !showNewTask"
            x-text="
              showNewTask
                ? 'Cancel'
                : '+ New Task'
            "
          ></button>

        </div>


        <div
          class="card card-pad"
          x-show="showNewTask"
          x-cloak
          x-transition
          style="margin-top:12px;"
        >

          <form
            method="POST"
            action="{{ route('tasks.store') }}"
          >

            @csrf

            <div class="form-grid">

              <div class="form-field">

                <label for="task_name">
                  Task name
                </label>

                <input
                  type="text"
                  id="task_name"
                  name="task_name"
                  required
                  autofocus
                >

              </div>


              <div class="form-field">

                <label for="phase_id">
                  Phase
                </label>

                <select
                  id="phase_id"
                  name="phase_id"
                  required
                >

                  @foreach ($project->phases as $ph)

                    <option
                      value="{{ $ph->phase_id }}"
                      {{
                        $ph->status === 'In Progress'
                          ? 'selected'
                          : ''
                      }}
                    >
                      {{ $ph->phase_name }}
                    </option>

                  @endforeach

                </select>

              </div>

            </div>


            <div class="form-grid">

              <div class="form-field">

                <label for="assigned_to">
                  Assignee
                </label>

                <select
                  id="assigned_to"
                  name="assigned_to"
                >

                  <option value="">
                    — Unassigned —
                  </option>

                  @foreach ($assignableUsers as $au)

                    <option
                      value="{{ $au['id'] }}"
                    >
                      {{ $au['name'] }}
                    </option>

                  @endforeach

                </select>

              </div>


              <div class="form-field">

                <label for="priority">
                  Priority
                </label>

                <select
                  id="priority"
                  name="priority"
                  required
                >

                  <option
                    value="Medium"
                    selected
                  >
                    Medium
                  </option>

                  <option value="High">
                    High
                  </option>

                  <option value="Low">
                    Low
                  </option>

                </select>

              </div>

            </div>


            <div class="form-field">

              <label for="end_date">
                Due date
              </label>

              <input
                type="date"
                id="end_date"
                name="end_date"
              >

            </div>


            <button
              type="submit"
              class="btn btn-accent"
            >
              Add task
            </button>

          </form>

        </div>

      </div>

    @endif


    <div class="kanban">

      @foreach (
        [
          'Pending' => 'Pending',
          'In Progress' => 'In Progress',
          'Done' => 'Done'
        ]
        as $statusKey => $statusLabel
      )

        <div>

          <div class="kcol-head">

            <h4>
              {{ $statusLabel }}
            </h4>

            <span class="kcol-count">
              {{
                $tasks
                  ->where('status', $statusKey)
                  ->count()
              }}
            </span>

          </div>


          <div class="kcol-body">

            @foreach (
              $tasks->where('status', $statusKey)
              as $t
            )

              @php
                $late =
                  $t->status !== 'Done'
                  && $t->end_date
                  && $t->end_date->isPast();
              @endphp

              <div
                class="tcard"
                onclick="openTask({{ $t->task_id }})"
              >

                <div class="id mono">
                  TASK-{{
                    str_pad(
                      $t->task_id,
                      4,
                      '0',
                      STR_PAD_LEFT
                    )
                  }}
                </div>

                <div class="name">
                  {{ $t->task_name }}
                </div>


                <div class="tcard-foot">

                  <span
                    class="priority p-{{
                      strtolower($t->priority)
                    }}"
                  >
                    {{ $t->priority }}
                  </span>


                  <div
                    style="
                      display:flex;
                      align-items:center;
                      gap:8px;
                    "
                  >

                    <span
                      class="duedate {{
                        $late ? 'late' : ''
                      }}"
                    >
                      {{ $late ? '⚠ ' : '' }}
                      {{
                        optional(
                          $t->end_date
                        )->format('d M')
                      }}
                    </span>


                    <div class="avatar">
                      {{
                        optional(
                          $t->assignee
                        )->initials()
                      }}
                    </div>

                  </div>

                </div>

              </div>

            @endforeach


            @if (
              $tasks
                ->where('status', $statusKey)
                ->isEmpty()
            )

              <div
                style="
                  text-align:center;
                  padding:20px 8px;
                  color:var(--ink-faint);
                  font-size:12px;
                "
              >
                No tasks
              </div>

            @endif

          </div>

        </div>

      @endforeach

    </div>

  </div>


  {{-- =======================================================
       DELIVERABLES
  ======================================================== --}}

  <div
    x-show="tab === 'deliverables'"
    x-cloak
  >

    <div class="card">

      <table>

        <thead>

          <tr>

            <th style="width:40%">
              Deliverable
            </th>

            <th>
              Due date
            </th>

            <th>
              Status
            </th>

          </tr>

        </thead>


        <tbody>

          @forelse (
            $project->deliverables as $d
          )

            <tr>

              <td class="cell-primary">
                {{ $d->deliverable_name }}
              </td>

              <td>
                {{
                  optional(
                    $d->due_date
                  )->format('d M Y')
                }}
              </td>

              <td>

                <span
                  class="badge {{
                    $d->status === 'Delivered'
                      ? 'b-active'
                      : 'b-planning'
                  }}"
                >

                  <span class="badge-dot"></span>

                  {{ $d->status }}

                </span>

              </td>

            </tr>

          @empty

            <tr>

              <td
                colspan="3"
                style="
                  text-align:center;
                  padding:30px;
                  color:var(--ink-faint);
                "
              >
                No deliverables have been added yet.
              </td>

            </tr>

          @endforelse

        </tbody>

      </table>

    </div>

  </div>


  {{-- =======================================================
       BUDGET
  ======================================================== --}}

  <div
    x-show="tab === 'budget'"
    x-cloak
  >

    @php

      $b = $project->budget;

      $allocated =
        (float) optional($b)->allocated_amount;

      $spent =
        (float) optional($b)->spent_amount;

      $remaining =
        max(0, $allocated - $spent);

      $util =
        $allocated > 0
          ? min(
              100,
              round(
                ($spent / $allocated) * 100
              )
            )
          : 0;

    @endphp


    {{-- =====================================================
         TOP BUDGET SUMMARY
    ====================================================== --}}

    <div
      class="grid grid-3"
      style="margin-bottom:18px;"
    >

      <div class="card card-pad">

        <div class="stat-label">
          Total allocated
        </div>

        <div class="stat-value">
          ETB {{ number_format($allocated, 2) }}
        </div>

        <div class="stat-delta">
          Project budget
        </div>

      </div>


      <div class="card card-pad">

        <div class="stat-label">
          Total spent
        </div>

        <div class="stat-value">
          ETB {{ number_format($spent, 2) }}
        </div>

        <div
          class="stat-delta"
          style="margin-top:8px;"
        >
          {{ $util }}% utilised
        </div>

      </div>


      <div class="card card-pad">

        <div class="stat-label">
          Remaining
        </div>

        <div class="stat-value">
          ETB {{ number_format($remaining, 2) }}
        </div>

        <div
          class="stat-delta"
          style="margin-top:8px;"
        >
          Available budget
        </div>

      </div>

    </div>


    {{-- =====================================================
         OVERALL PROGRESS
    ====================================================== --}}

    <div
      class="card card-pad"
      style="margin-bottom:18px;"
    >

      <div class="card-title-row">

        <h3>
          Budget utilisation
        </h3>

        <span class="mono">
          {{ $util }}%
        </span>

      </div>


      <div
        class="progressbar {{
          $util > 85
            ? 'danger'
            : ($util > 65 ? 'warn' : '')
        }}"
        style="margin-top:10px;"
      >

        <div
          style="width:{{ $util }}%;"
        ></div>

      </div>


      <div
        style="
          display:flex;
          justify-content:space-between;
          margin-top:8px;
          font-size:12px;
          color:var(--ink-soft);
        "
      >

        <span>
          ETB {{ number_format($spent, 2) }} spent
        </span>

        <span>
          ETB {{ number_format($allocated, 2) }} allocated
        </span>

      </div>

    </div>


    {{-- =====================================================
         RECORD EXPENSE
    ====================================================== --}}

    @if (auth()->user()->can('manage_budgets'))

      <div
        class="card card-pad"
        style="margin-bottom:18px;"
      >

        <div class="card-title-row">

          <div>

            <h3>
              Record Expense
            </h3>

            <div
              class="cell-sub"
              style="margin-top:4px;"
            >
              Record money actually spent on this project.
            </div>

          </div>

        </div>


        <form
          method="POST"
          action="{{ route(
            'budgets.expenses.store',
            $project
          ) }}"
        >

          @csrf


          <div class="form-grid">

            {{-- Phase --}}

            <div class="form-field">

              <label for="expense_phase">
                Phase
              </label>

              <select
                id="expense_phase"
                name="phase_id"
                required
              >

                <option value="">
                  Select phase
                </option>


                @foreach (
                  $project->phases as $ph
                )

                  @php

                    $pb = $ph->budget;

                    $phaseAllocated =
                      (float) optional(
                        $pb
                      )->allocated_amount;

                    $phaseSpent =
                      (float) optional(
                        $pb
                      )->spent_amount;

                    $phaseRemaining =
                      max(
                        0,
                        $phaseAllocated
                        - $phaseSpent
                      );

                  @endphp

                  <option
                    value="{{ $ph->phase_id }}"
                    {{
                      old('phase_id')
                      == $ph->phase_id
                        ? 'selected'
                        : ''
                    }}
                  >

                    {{ $ph->phase_name }}

                    —
                    ETB
                    {{
                      number_format(
                        $phaseRemaining,
                        2
                      )
                    }}
                    remaining

                  </option>

                @endforeach

              </select>

            </div>


            {{-- Amount --}}

            <div class="form-field">

              <label for="expense_amount">
                Amount (ETB)
              </label>

              <input
                type="number"
                id="expense_amount"
                name="amount"
                min="0.01"
                step="0.01"
                value="{{ old('amount') }}"
                required
                placeholder="25000.00"
              >

            </div>


            {{-- Date --}}

            <div class="form-field">

              <label for="expense_date">
                Expense date
              </label>

              <input
                type="date"
                id="expense_date"
                name="expense_date"
                value="{{
                  old(
                    'expense_date',
                    now()->format('Y-m-d')
                  )
                }}"
                required
              >

            </div>

          </div>


          {{-- Description --}}

          <div class="form-field">

            <label for="expense_description">
              Description
            </label>

            <input
              type="text"
              id="expense_description"
              name="description"
              maxlength="255"
              value="{{ old('description') }}"
              required
              placeholder="e.g. Purchase of network equipment"
            >

          </div>


          <div
            style="
              display:flex;
              justify-content:flex-end;
              margin-top:12px;
            "
          >

            <button
              type="submit"
              class="btn btn-accent"
            >
              Record Expense
            </button>

          </div>

        </form>

      </div>

    @endif


    {{-- =====================================================
         BUDGET BY PHASE
    ====================================================== --}}

    <div
      class="card card-pad"
      style="margin-bottom:18px;"
    >

      <div class="card-title-row">

        <h3>
          Budget by phase
        </h3>

      </div>


      @forelse (
        $project->phases as $ph
      )

        @php

          $pb = $ph->budget;

          $phaseAllocated =
            (float) optional(
              $pb
            )->allocated_amount;

          $phaseSpent =
            (float) optional(
              $pb
            )->spent_amount;

          $phaseRemaining =
            max(
              0,
              $phaseAllocated
              - $phaseSpent
            );

          $phasePct =
            $phaseAllocated > 0
              ? min(
                  100,
                  round(
                    ($phaseSpent /
                    $phaseAllocated) * 100
                  )
                )
              : 0;

        @endphp


        <div
          style="
            padding:14px 0;
            border-bottom:1px solid var(--line);
          "
        >

          <div
            style="
              display:flex;
              justify-content:space-between;
              align-items:flex-start;
              gap:12px;
              margin-bottom:8px;
            "
          >

            <div>

              <div
                style="
                  font-size:13px;
                  font-weight:600;
                "
              >
                {{ $ph->phase_name }}
              </div>

              <div
                class="cell-sub"
                style="margin-top:3px;"
              >
                Spent:
                ETB
                {{ number_format(
                  $phaseSpent,
                  2
                ) }}

                ·

                Remaining:
                ETB
                {{ number_format(
                  $phaseRemaining,
                  2
                ) }}
              </div>

            </div>


            <div
              class="mono"
              style="
                font-size:12px;
                color:var(--ink-soft);
              "
            >
              ETB
              {{ number_format(
                $phaseAllocated,
                2
              ) }}
            </div>

          </div>


          <div
            class="progressbar {{
              $phasePct > 85
                ? 'danger'
                : (
                    $phasePct > 65
                      ? 'warn'
                      : ''
                  )
            }}"
          >

            <div
              style="width:{{ $phasePct }}%;"
            ></div>

          </div>


          <div
            style="
              display:flex;
              justify-content:space-between;
              margin-top:5px;
              font-size:11px;
              color:var(--ink-faint);
            "
          >

            <span>
              {{ $phasePct }}% utilised
            </span>

            <span>
              {{ $ph->status }}
            </span>

          </div>

        </div>

      @empty

        <div
          style="
            text-align:center;
            padding:30px;
            color:var(--ink-faint);
          "
        >
          No phases found for this project.
        </div>

      @endforelse

    </div>


    {{-- =====================================================
         EXPENSE HISTORY
    ====================================================== --}}

    <div class="card">

      <div class="card-pad">

        <div class="card-title-row">

          <div>

            <h3>
              Expense History
            </h3>

            <div
              class="cell-sub"
              style="margin-top:4px;"
            >
              Every recorded project expense.
            </div>

          </div>


          @if (
            isset($project->expenses)
          )

            <span
              class="mono"
              style="
                font-size:11px;
                color:var(--ink-faint);
              "
            >
              {{
                $project->expenses->count()
              }}
              transaction(s)
            </span>

          @endif

        </div>

      </div>


      <table>

        <thead>

          <tr>

            <th>
              Date
            </th>

            <th>
              Phase
            </th>

            <th>
              Description
            </th>

            <th>
              Recorded by
            </th>

            <th
              style="text-align:right;"
            >
              Amount
            </th>

          </tr>

        </thead>


        <tbody>

          @if (
            isset($project->expenses)
            && $project->expenses->count()
          )

            @foreach (
              $project->expenses
                ->sortByDesc('expense_date')
                as $expense
            )

              <tr>

                <td>

                  {{
                    optional(
                      $expense->expense_date
                    )->format('d M Y')
                  }}

                </td>


                <td>

                  {{
                    optional(
                      $expense->phase
                    )->phase_name
                    ?? '—'
                  }}

                </td>


                <td class="cell-primary">

                  {{ $expense->description }}

                </td>


                <td>

                  {{
                    optional(
                      $expense->creator
                    )->full_name
                    ?? '—'
                  }}

                </td>


                <td
                  style="
                    text-align:right;
                    font-weight:600;
                  "
                >

                  ETB
                  {{
                    number_format(
                      $expense->amount,
                      2
                    )
                  }}

                </td>

              </tr>

            @endforeach

          @else

            <tr>

              <td
                colspan="5"
                style="
                  text-align:center;
                  padding:35px 20px;
                  color:var(--ink-faint);
                "
              >

                No expenses have been recorded yet.

              </td>

            </tr>

          @endif

        </tbody>

      </table>

    </div>

  </div>


  {{-- =======================================================
       CHANGE REQUESTS
  ======================================================== --}}

  <div
    x-show="tab === 'changes'"
    x-cloak
  >

    <div class="card">

      <table>

        <thead>

          <tr>

            <th style="width:34%">
              Request
            </th>

            <th>
              Requested by
            </th>

            <th>
              Date
            </th>

            <th>
              Status
            </th>

            <th></th>

          </tr>

        </thead>


        <tbody>

          @forelse (
            $project->changeRequests as $c
          )

            @php

              $ccls =
                [
                  'Approved' => 'b-active',
                  'Rejected' => 'b-blocked'
                ][$c->status]
                ?? 'b-risk';

            @endphp


            <tr>

              <td class="cell-primary">
                {{ $c->description }}
              </td>


              <td>
                {{
                  optional(
                    $c->requester
                  )->full_name
                }}
              </td>


              <td>
                {{
                  optional(
                    $c->requested_date
                  )->format('d M Y')
                }}
              </td>


              <td>

                <span
                  class="badge {{ $ccls }}"
                >

                  <span class="badge-dot"></span>

                  {{ $c->status }}

                </span>

              </td>


              <td style="text-align:right;">

                @if (
                  $c->status === 'Pending'
                  && auth()->user()->can(
                    'approve_change_requests'
                  )
                )

                  <form
                    method="POST"
                    action="{{
                      route(
                        'changeRequests.approve',
                        $c
                      )
                    }}"
                    style="display:inline;"
                  >

                    @csrf

                    <button
                      type="submit"
                      class="btn btn-ghost"
                      style="
                        padding:5px 11px;
                        font-size:11.5px;
                        color:var(--success);
                        border-color:var(--success-soft);
                      "
                    >
                      Approve
                    </button>

                  </form>


                  <form
                    method="POST"
                    action="{{
                      route(
                        'changeRequests.reject',
                        $c
                      )
                    }}"
                    style="display:inline;"
                  >

                    @csrf

                    <button
                      type="submit"
                      class="btn btn-ghost"
                      style="
                        padding:5px 11px;
                        font-size:11.5px;
                        color:var(--danger);
                        border-color:var(--danger-soft);
                      "
                    >
                      Reject
                    </button>

                  </form>

                @endif

              </td>

            </tr>

          @empty

            <tr>

              <td
                colspan="5"
                style="
                  text-align:center;
                  padding:30px;
                  color:var(--ink-faint);
                "
              >
                No change requests.
              </td>

            </tr>

          @endforelse

        </tbody>

      </table>

    </div>

  </div>

</div>

@endsection