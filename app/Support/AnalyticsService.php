<?php

namespace App\Support;

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Phase 34 — shared analytics math for the Admin Dashboard and the
 * dedicated /admin/analytics page.
 *
 * Single home for the chart/range/system-stats logic that used to live
 * privately in DashboardController (kpiDelta, kpiSeries, parseRange,
 * buildChartSeries, seriesForRange, systemOverviewStats + helpers).
 * DashboardController keeps thin delegating wrappers with the SAME
 * private-method signatures so the phase-06d verify scripts
 * (scripts/verify_phase06d*.php — which token-scan for
 * `private function seriesForRange(...)` etc. inside DashboardController)
 * keep passing.
 *
 * AnalyticsController uses this service directly; both consumers render
 * identical wire shapes.
 */
final class AnalyticsService
{
    /**
     * 7 admin KPI snapshots (matches the stripe-style 7-card admin grid).
     */
    public function adminKpis(): array
    {
        $base = Guest::query();

        $totalGuests = (clone $base)->count();
        $pendingContacts = (clone $base)
            ->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            })->count();
        $totalCalls = (clone $base)
            ->whereNotNull('contacted_status')->where('contacted_status', '!=', '')->count();
        $visited = (clone $base)->where('visited', true)->count();
        $totalCells = ImpactCell::count();
        $totalSubmissions = ImpactSubmission::count();
        $totalUsers = User::count();

        return [
            'totalGuests'      => (int) $totalGuests,
            'pendingContacts'  => (int) $pendingContacts,
            'totalCalls'       => (int) $totalCalls,
            'visited'          => (int) $visited,
            'totalCells'       => (int) $totalCells,
            'totalSubmissions' => (int) $totalSubmissions,
            'totalUsers'       => (int) $totalUsers,
        ];
    }

    /**
     * Per-KPI delta (current 7d vs prior 7d) — same shape as adminDashboard.
     */
    public function adminKpiDeltas(): array
    {
        $now = now();
        $currStart = $now->copy()->subDays(7);
        $priorStart = $now->copy()->subDays(14);
        $base = Guest::query();

        return [
            'totalGuests'      => $this->kpiDelta((clone $base), $currStart, $now, $priorStart),
            'pendingContacts'  => $this->kpiDelta((clone $base)->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            }), $currStart, $now, $priorStart),
            'totalCalls'       => $this->kpiDelta((clone $base)->whereNotNull('contacted_status')->where('contacted_status', '!=', ''), $currStart, $now, $priorStart),
            'visited'          => $this->kpiDelta((clone $base)->where('visited', true), $currStart, $now, $priorStart),
            'totalCells'       => $this->kpiDelta(new ImpactCell, $currStart, $now, $priorStart),
            'totalSubmissions' => $this->kpiDelta(new ImpactSubmission, $currStart, $now, $priorStart),
            'totalUsers'       => $this->kpiDelta(new User, $currStart, $now, $priorStart),
        ];
    }

    /**
     * Per-KPI 14-day sparkline series — same shape as adminDashboard.
     */
    public function adminKpiSeries(): array
    {
        $now = now();
        $base = Guest::query();

        return [
            'totalGuests'      => $this->kpiSeries((clone $base), $now, 14),
            'pendingContacts'  => $this->kpiSeries((clone $base)->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            }), $now, 14),
            'totalCalls'       => $this->kpiSeries((clone $base)->whereNotNull('contacted_status')->where('contacted_status', '!=', ''), $now, 14),
            'visited'          => $this->kpiSeries((clone $base)->where('visited', true), $now, 14),
            'totalCells'       => $this->kpiSeries(new ImpactCell, $now, 14),
            'totalSubmissions' => $this->kpiSeries(new ImpactSubmission, $now, 14),
            'totalUsers'       => $this->kpiSeries(new User, $now, 14),
        ];
    }

    /**
     * KPI delta helper: rows in [currStart, now] vs [priorStart, currStart).
     */
    public function kpiDelta($query, $currStart, $now, $priorStart): array
    {
        $curr  = (clone $query)->where('created_at', '>=', $currStart)->count();
        $prior = (clone $query)->where('created_at', '>=', $priorStart)->where('created_at', '<', $currStart)->count();
        $pct = $prior > 0
            ? round((($curr - $prior) / $prior) * 100, 1)
            : ($curr > 0 ? 100.0 : 0.0);
        return ['value' => (float) $pct, 'positiveIsGood' => true];
    }

    /**
     * KPI sparkline series: length-N daily counts (oldest → newest).
     */
    public function kpiSeries($query, $now, int $days): array
    {
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)->where('created_at', '>=', $start)->get(['created_at']);
        $buckets = array_fill(0, $days, 0);
        $idx = 0;
        foreach ($rows as $r) {
            $offset = (int) floor($start->diffInDays($r->created_at));
            if ($offset >= 0 && $offset < $days) {
                $buckets[$offset]++;
            }
            $idx++;
            if ($idx > 50000) break; // defensive ceiling
        }
        return $buckets;
    }

    /**
     * Parse ?range= from a request into a typed range config.
     * (Identical contract to the old DashboardController::parseRange.)
     */
    public function parseRange(Request $request): array
    {
        $allowed = ['today', 'week', 'month', 'year', 'custom'];
        $range = (string) $request->query('range', 'week');
        if (! in_array($range, $allowed, true)) {
            $range = 'week';
        }

        $now = now();
        $from = $now->copy()->subHours(23)->startOfHour();
        $to = $now->copy()->endOfHour();
        $labels = [];
        $bucketCount = 24;
        $bucketUnit = 'hour';

        switch ($range) {
            case 'today':
                $bucketCount = 24;
                $bucketUnit = 'hour';
                $from = $now->copy()->subHours(23)->startOfHour();
                $to = $now->copy()->endOfHour();
                for ($i = 23; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subHours($i)->format('H:00');
                }
                break;

            case 'week':
                $bucketCount = 7;
                $bucketUnit = 'day';
                $from = $now->copy()->subDays(6)->startOfDay();
                $to = $now->copy()->endOfDay();
                for ($i = 6; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subDays($i)->format('M j');
                }
                break;

            case 'month':
                $bucketCount = 30;
                $bucketUnit = 'day';
                $from = $now->copy()->subDays(29)->startOfDay();
                $to = $now->copy()->endOfDay();
                for ($i = 29; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subDays($i)->format('M j');
                }
                break;

            case 'year':
                $bucketCount = 12;
                $bucketUnit = 'month';
                $from = $now->copy()->subMonths(11)->startOfMonth();
                $to = $now->copy()->endOfMonth();
                for ($i = 11; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subMonths($i)->format('M Y');
                }
                break;

            case 'custom':
                $fromStr = (string) $request->query('from', '');
                $toStr = (string) $request->query('to', '');
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                    return $this->parseRange(new Request(['range' => 'week']));
                }
                $customFrom = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $fromStr)->startOfDay();
                $customTo = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $toStr)->endOfDay();
                if ($customFrom->gt($customTo) || $customFrom->diffInDays($customTo) > 365) {
                    return $this->parseRange(new Request(['range' => 'week']));
                }
                $spanDays = (int) $customFrom->diffInDays($customTo) + 1;
                $bucketUnit = $spanDays > 60 ? 'month' : 'day';
                $bucketCount = $bucketUnit === 'month'
                    ? max(1, (int) floor($customFrom->diffInMonths($customTo)) + 1)
                    : $spanDays;
                $labels = [];
                if ($bucketUnit === 'day') {
                    $cursor = $customFrom->copy();
                    while ($cursor->lte($customTo)) {
                        $labels[] = $cursor->format('M j');
                        $cursor->addDay();
                    }
                } else {
                    $cursor = $customFrom->copy()->startOfMonth();
                    while ($cursor->lte($customTo)) {
                        $labels[] = $cursor->format('M Y');
                        $cursor->addMonth();
                    }
                }
                $from = $customFrom;
                $to = $customTo;
                break;
        }

        return [
            'key' => $range,
            'from' => $from,
            'to' => $to,
            'bucketCount' => $bucketCount,
            'bucketUnit' => $bucketUnit,
            'labels' => $labels,
        ];
    }

    /**
     * Chart payload builder: 4 cumulative metric series for a date range.
     */
    public function buildChartSeries($base, array $range): array
    {
        $from = $range['from'];
        $to = $range['to'];
        $bucketCount = $range['bucketCount'];
        $bucketUnit = $range['bucketUnit'];
        $labels = $range['labels'];
        return [
            'totalGuests'      => $this->seriesForRange((clone $base), $from, $to, $bucketCount, $bucketUnit, $labels),
            'totalCalls'       => $this->seriesForRange((clone $base)->whereNotNull('contacted_status')->where('contacted_status', '!=', ''), $from, $to, $bucketCount, $bucketUnit, $labels),
            'totalSubmissions' => $this->seriesForRange(new ImpactSubmission, $from, $to, $bucketCount, $bucketUnit, $labels),
            'totalUsers'       => $this->seriesForRange(new User, $from, $to, $bucketCount, $bucketUnit, $labels),
        ];
    }

    /**
     * Bucket-aware row counter for a date range (SQLite-compatible).
     */
    public function seriesForRange($query, $from, $to, int $bucketCount, string $bucketUnit, array $labels): array
    {
        $buckets = array_fill(0, $bucketCount, 0);
        $rows = (clone $query)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->get(['created_at']);

        if ($bucketUnit === 'hour') {
            $fromHour = (int) $this->tzFormat($from, 'YmdH');
            foreach ($rows as $r) {
                $rowHour = (int) $this->tzFormat($r->created_at, 'YmdH');
                $offset = $rowHour - $fromHour;
                if ($offset >= 0 && $offset < $bucketCount) {
                    $buckets[$offset]++;
                }
            }
        } elseif ($bucketUnit === 'day') {
            $fromDay = (int) $this->tzFormat($from, 'Ymd');
            foreach ($rows as $r) {
                $rowDay = (int) $this->tzFormat($r->created_at, 'Ymd');
                $offset = $rowDay - $fromDay;
                if ($offset >= 0 && $offset < $bucketCount) {
                    $buckets[$offset]++;
                }
            }
        } elseif ($bucketUnit === 'month') {
            $fromYm = (int) ($this->tzFormat($from, 'Y') . $this->tzFormat($from, 'm'));
            foreach ($rows as $r) {
                $rowYm = (int) ($this->tzFormat($r->created_at, 'Y') . $this->tzFormat($r->created_at, 'm'));
                $offset = $rowYm - $fromYm;
                if ($offset >= 0 && $offset < $bucketCount) {
                    $buckets[$offset]++;
                }
            }
        }
        return $buckets;
    }

    /**
     * System overview stats (DB size / storage / active users / health).
     * Same contract as the dashboard's SystemOverviewPanel props.
     */
    public function systemOverviewStats(): array
    {
        $dbSizeBytes = 0;
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                foreach (DB::select('SHOW TABLE STATUS') as $r) {
                    $dbSizeBytes += (int) ($r->Data_length ?? 0) + (int) ($r->Index_length ?? 0);
                }
            } elseif ($driver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if ($dbPath && file_exists($dbPath)) {
                    $dbSizeBytes = (int) (filesize($dbPath) ?: 0);
                }
            }
        } catch (\Throwable $e) {
            $dbSizeBytes = 0;
        }

        $storageBytes = 0;
        foreach (['app', 'framework'] as $sub) {
            $path = storage_path($sub);
            if (is_dir($path)) {
                $storageBytes += $this->dirSize($path);
            }
        }

        $activeUsers = 0;
        try {
            $activeUsers = (int) Activity::where('updated_at', '>=', now()->subDays(7))
                ->whereNotNull('causer_id')
                ->distinct('causer_id')
                ->count('causer_id');
        } catch (\Throwable $e) {
            $activeUsers = 0;
        }

        $errorEvents = 0;
        try {
            $errorEvents = (int) Activity::where('updated_at', '>=', now()->subHours(24))
                ->where(function ($q) {
                    $q->where('log_name', 'error')->orWhere('description', 'like', '%fail%');
                })
                ->count();
        } catch (\Throwable $e) {
            $errorEvents = 0;
        }

        if ($errorEvents === 0) {
            $health = ['label' => 'Healthy', 'tone' => 'success'];
        } elseif ($errorEvents < 5) {
            $health = ['label' => '1 issue', 'tone' => 'warn'];
        } else {
            $health = ['label' => $errorEvents . ' issues', 'tone' => 'danger'];
        }

        return [
            'dbSizeMb'     => round($dbSizeBytes / 1024 / 1024, 2),
            'dbSizeLabel'  => $dbSizeBytes > 0 ? $this->humanBytes($dbSizeBytes) : '—',
            'storageMb'    => round($storageBytes / 1024 / 1024, 2),
            'storageLabel' => $storageBytes > 0 ? $this->humanBytes($storageBytes) : '—',
            'activeUsers'  => $activeUsers,
            'healthLabel'  => $health['label'],
            'healthTone'   => $health['tone'],
        ];
    }

    /**
     * Pin a Carbon datetime to config('app.timezone') before formatting.
     * Public: DashboardController's private tzFormat() delegates here.
     */
    public function tzFormat(\Carbon\Carbon $c, string $format): string
    {
        return $c->copy()->setTimezone(config('app.timezone'))->format($format);
    }

    /** Recursive directory-size helper using SPL iterators (public for delegation). */
    public function dirSize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }
        $total = 0;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }
        return $total;
    }

    /** Human-readable byte suffix helper (B / KB / MB / GB) — public for delegation. */
    public function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 1) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
    }
}
