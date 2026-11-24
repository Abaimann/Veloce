<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(RoleName::SUPER_ADMIN->value)) {
            abort(403);
        }

        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $rawEvent = $request->query('event');
        $selectedEvent = is_string($rawEvent) && in_array($rawEvent, ['created', 'updated', 'deleted'], true)
            ? $rawEvent
            : null;
        $rawSubject = $request->query('subject_type');
        $selectedSubject = is_string($rawSubject) && $rawSubject !== '' ? $rawSubject : null;

        $rawCauserId = $request->query('causer_id');
        $selectedCauserId = is_scalar($rawCauserId) && ctype_digit((string) $rawCauserId) && (int) $rawCauserId > 0
            ? (int) $rawCauserId
            : null;

        $logs = Activity::query()
            ->with('causer')
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $pattern = '%'.Str::lower($search).'%';
                $query->where(static function (Builder $inner) use ($pattern): void {
                    $inner->whereRaw('LOWER(description) LIKE ?', [$pattern])
                        ->orWhereRaw("LOWER(COALESCE(event, '')) LIKE ?", [$pattern])
                        ->orWhereRaw("LOWER(COALESCE(subject_type, '')) LIKE ?", [$pattern]);
                });
            })
            ->when($selectedEvent !== null, static fn (Builder $query): Builder => $query->where('event', $selectedEvent))
            ->when($selectedSubject !== null, static fn (Builder $query): Builder => $query->where('subject_type', $selectedSubject))
            ->when($selectedCauserId !== null, static fn (Builder $query): Builder => $query->where('causer_type', User::class)->where('causer_id', $selectedCauserId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $subjectTypes = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(static fn (string $type): array => [$type => class_basename($type)])
            ->all();

        $causers = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(static fn (User $u): array => [$u->id => "{$u->name} ({$u->email})"])
            ->all();

        return view('management.activity-logs.index', [
            'logs' => $logs,
            'subjectTypes' => $subjectTypes,
            'causers' => $causers,
            'search' => $search,
            'selectedEvent' => $selectedEvent,
            'selectedSubject' => $selectedSubject,
            'selectedCauserId' => $selectedCauserId,
            'timezone' => (string) config('attendance.timezone', 'Asia/Jakarta'),
        ]);
    }
}
