@extends('layouts.app')

@section('title', 'Budgets')

@section('crumb')
    <b>Budgets</b>
@endsection

@section('content')

<div class="page-head">
    <div>
        <h1>Budgets</h1>

        <div class="page-sub">
            Monitor and manage project budgets
        </div>
    </div>
</div>

@if (session('status'))
    <div class="form-alert" style="margin-bottom:16px;">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="form-alert" style="margin-bottom:16px;">
        <ul style="margin:0; padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


@if ($projects->isEmpty())

    <div class="card card-pad">
        <div class="empty">
            <h4>No budgets available</h4>

            <p>
                Projects with budgets will appear here.
            </p>
        </div>
    </div>

@else

    <div class="card">

        <table>

            <thead>
                <tr>
                    <th style="width:25%;">
                        Project
                    </th>

                    <th>
                        Allocated
                    </th>

                    <th>
                        Spent
                    </th>

                    <th>
                        Remaining
                    </th>

                    <th style="width:190px;">
                        Utilisation
                    </th>

                    <th>
                        Currency
                    </th>

                    @can('manage_budgets')
                        <th style="width:80px;">
                            Action
                        </th>
                    @endcan
                </tr>
            </thead>

            <tbody>

                @foreach ($projects as $project)

                    @php

                        $budget = $project->budget;

                        $allocated =
                            (float) ($budget->allocated_amount ?? 0);

                        $spent =
                            (float) ($budget->spent_amount ?? 0);

                        $remaining =
                            $allocated - $spent;

                        $utilisation =
                            $allocated > 0
                                ? round(($spent / $allocated) * 100)
                                : 0;

                        $barWidth =
                            min(100, max(0, $utilisation));

                    @endphp

                    <tr>

                        <td>

                            <a
                                href="{{ route('projects.show', $project) }}"
                                class="cell-primary"
                                style="text-decoration:none;"
                            >
                                {{ $project->project_name }}
                            </a>

                            <div class="cell-sub mono">
                                PRJ-{{ str_pad($project->project_id, 3, '0', STR_PAD_LEFT) }}
                            </div>

                        </td>


                        <td>
                            ETB {{ number_format($allocated, 2) }}
                        </td>


                        <td>
                            ETB {{ number_format($spent, 2) }}
                        </td>


                        <td>

                            <span
                                style="
                                    font-weight:600;
                                    color:
                                    {{ $remaining < 0
                                        ? 'var(--danger)'
                                        : 'var(--ink)' }};
                                "
                            >
                                ETB {{ number_format($remaining, 2) }}
                            </span>

                        </td>


                        <td>

                            <div
                                class="cell-sub"
                                style="margin-bottom:4px;"
                            >
                                {{ $utilisation }}%
                            </div>

                            <div
                                class="progressbar
                                {{ $utilisation > 85
                                    ? 'danger'
                                    : ($utilisation > 65
                                        ? 'warn'
                                        : '') }}"
                            >
                                <div
                                    style="width:{{ $barWidth }}%"
                                ></div>
                            </div>

                        </td>


                        <td>
                            {{ $budget->currency ?? 'ETB' }}
                        </td>


                        @can('manage_budgets')

                            <td>

                                <a
                                    href="{{ route('projects.show', $project) }}?tab=budget"
                                    class="btn btn-ghost"
                                    style="
                                        padding:6px 10px;
                                        font-size:11px;
                                    "
                                >
                                    View
                                </a>

                            </td>

                        @endcan

                    </tr>

                @endforeach

            </tbody>

        </table>

    </div>

@endif

@endsection